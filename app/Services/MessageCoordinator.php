<?php

namespace App\Services;

use App\Services\Agent\AgentExecutor;
use App\Services\Messaging\MessagingRouter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessageCoordinator
{
    private const GRACE_PERIOD_MS = 2000;
    private const PHOTO_EXTRA_MS = 5000;

    public function __construct(
        private AgentExecutor $executor,
        private MessagingRouter $messaging,
    ) {}

    /**
     * Handle an echo message from the Facebook page.
     */
    public function pushEchoMessage(string $userId, string $message): void
    {
        $this->executor->processMessage($userId, $message, 0, null, true);
    }

    /**
     * Enqueue a user message with grace period batching.
     * Uses Laravel Cache + dispatch delay for batching.
     */
    public function enqueueMessage(
        string $userId,
        string $message,
        ?string $messageId = null,
        bool $hasPhoto = false,
    ): void {
        $cacheKey = "msg_queue:{$userId}";
        $lockKey = "msg_lock:{$userId}";

        // Add message to queue in cache
        $queue = Cache::get($cacheKey, []);
        $queue[] = [
            'message' => $message,
            'messageId' => $messageId,
            'hasPhoto' => $hasPhoto,
            'timestamp' => now()->timestamp,
        ];
        Cache::put($cacheKey, $queue, 120); // TTL: 2 minutes

        // Calculate grace period
        $graceMs = self::GRACE_PERIOD_MS;
        if ($hasPhoto) {
            $graceMs += self::PHOTO_EXTRA_MS;
        }

        // Cancel any existing delayed dispatch and set a new one
        $timerKey = "msg_timer:{$userId}";
        Cache::put($timerKey, now()->timestamp, 120);

        $tenantId = app()->bound(\App\Models\Tenant::class) ? app(\App\Models\Tenant::class)->id : null;

        // Dispatch the processing job with delay
        \App\Jobs\ProcessMessageQueue::dispatch($userId, $messageId, $tenantId)
            ->delay(now()->addMilliseconds($graceMs));
    }

    /**
     * Process all queued messages for a user.
     * Called by the ProcessMessageQueue job after the grace period.
     */
    public function processQueue(string $userId, ?string $messageId = null): void
    {
        $cacheKey = "msg_queue:{$userId}";
        $lockKey = "msg_processing:{$userId}";

        // Acquire lock to prevent double processing
        $lock = Cache::lock($lockKey, 120);
        if (!$lock->get()) {
            Log::info("MessageCoordinator: Queue already being processed for user {$userId}");
            return;
        }

        try {
            // Get all queued messages
            $queue = Cache::get($cacheKey, []);
            if (empty($queue)) {
                return;
            }

            // Clear the queue immediately
            Cache::forget($cacheKey);

            // Combine all messages
            $combinedMessage = implode("\n", array_map(fn($q) => $q['message'], $queue));
            $firstMessageId = $queue[0]['messageId'] ?? $messageId;

            if (empty(trim($combinedMessage))) {
                return;
            }

            // Process through the agent executor
            $response = $this->executor->processMessage(
                $userId,
                $combinedMessage,
                0,
                $firstMessageId,
            );

            // Send the response
            if (!empty(trim($response))) {
                $this->messaging->sendTextMessage($userId, $response);
            }

            // Check if new messages arrived during processing
            $newQueue = Cache::get($cacheKey, []);
            if (!empty($newQueue)) {
                $tenantId = app()->bound(\App\Models\Tenant::class) ? app(\App\Models\Tenant::class)->id : null;
                // Re-dispatch for new messages
                \App\Jobs\ProcessMessageQueue::dispatch($userId, null, $tenantId)
                    ->delay(now()->addMilliseconds(self::GRACE_PERIOD_MS));
            }
        } catch (\Exception $e) {
            Log::error("MessageCoordinator::processQueue failed for user {$userId}: {$e->getMessage()}");
        } finally {
            $lock->release();
        }
    }
}

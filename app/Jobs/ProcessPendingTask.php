<?php

namespace App\Jobs;

use App\Models\PendingTask;
use App\Services\Agent\AgentExecutor;
use App\Services\FacebookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPendingTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public int $taskId,
        public string $userId,
        public string $message,
        public ?int $tenantId = null,
    ) {}

    public function handle(AgentExecutor $executor, FacebookService $facebook): void
    {
        if ($this->tenantId) {
            $tenant = \App\Models\Tenant::find($this->tenantId);
            if ($tenant) {
                app()->instance(\App\Models\Tenant::class, $tenant);
            }
        }

        try {
            // Delete the task from DB
            PendingTask::destroy($this->taskId);

            // Resume the task
            $toolResult = $executor->resumeTask($this->userId, $this->message);

            if (!$toolResult) {
                return;
            }

            // Check for special actions
            if (str_contains($toolResult, 'SILENT_RETRY_SCHEDULED') || str_contains($toolResult, 'ROUTE_TO_HUMAN')) {
                return;
            }

            $decoded = json_decode($toolResult, true);
            if (isset($decoded['success']) && $decoded['success']) {
                // Process the next message
                $response = $executor->processMessage($this->userId, '');
                if (!empty(trim($response))) {
                    $facebook->sendTextMessage($this->userId, $response);
                }
            }
        } catch (\Exception $e) {
            Log::error("ProcessPendingTask failed for task {$this->taskId}: {$e->getMessage()}");
        }
    }
}

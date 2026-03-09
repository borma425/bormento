<?php

namespace App\Http\Controllers;

use App\Services\MessageCoordinator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private MessageCoordinator $coordinator,
    ) {}

    /**
     * GET /webhook - Facebook webhook verification.
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('agent.fb_verify_token')) {
            Log::info('Webhook verified successfully.');
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * POST /webhook - Handle Facebook events.
     */
    public function handleEvent(Request $request)
    {
        $body = $request->all();

        $object = $body['object'] ?? '';

        if ($object !== 'page' && $object !== 'instagram') {
            return response('Not Found', 404);
        }

        // Respond immediately to prevent Facebook timeout
        // We'll process in the background via queue
        $this->processEntries($body['entry'] ?? [], $object);

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * Process Facebook webhook entries.
     */
    private function processEntries(array $entries, string $object): void
    {
        foreach ($entries as $entry) {
            // Handle feed changes (comments) - skip for now
            if (isset($entry['changes'])) {
                $this->handleComments($entry['changes']);
                continue;
            }

            // Handle messaging
            if (isset($entry['messaging'])) {
                foreach ($entry['messaging'] as $event) {
                    $this->handleMessaging($event, $object);
                }
            }
        }
    }

    /**
     * Handle a single messaging event.
     */
    private function handleMessaging(array $event, string $object): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $messageData = $event['message'] ?? null;

        if (!$senderId || !$messageData) {
            return;
        }

        $isEcho = isset($messageData['is_echo']) && $messageData['is_echo'];

        // Prefix the target ID with 'ig-' if it's an Instagram webhook
        if ($object === 'instagram') {
            if ($isEcho) {
                $recipientId = "ig-{$recipientId}";
            } else {
                $senderId = "ig-{$senderId}";
            }
        }

        // Check for echo messages (from the page itself)
        if ($isEcho) {
            $this->handleEcho($event, $senderId, $recipientId);
            return;
        }

        $isInstagram = $object === 'instagram';

        // DEV mode: check testers list
        if (config('agent.mode') === 'DEV' && !$isInstagram) {
            $testers = config('agent.testers_facebook_ids', []);
            if (!empty($testers) && !in_array($senderId, $testers)) {
                Log::info("Ignoring message from non-tester: {$senderId}");
                return;
            }
        }

        // Build the message text
        $text = $messageData['text'] ?? '';
        $messageId = $messageData['mid'] ?? null;
        $hasPhoto = false;

        // Handle attachments
        $attachments = $messageData['attachments'] ?? [];
        foreach ($attachments as $attachment) {
            $type = $attachment['type'] ?? '';
            $payloadUrl = $attachment['payload']['url'] ?? '';

            if ($type === 'image' && $payloadUrl) {
                $text .= " [IMAGE_URLS: {$payloadUrl}]";
                $hasPhoto = true;
            } elseif ($type === 'audio' && $payloadUrl) {
                // Estimate audio duration from file size
                $duration = $this->estimateAudioDuration($payloadUrl);
                $text .= " [AUDIO_URL: {$payloadUrl}] [AUDIO_DURATION_SECONDS: {$duration}]";
            } elseif ($type === 'video' && $payloadUrl) {
                $text .= " [VIDEO_URL: {$payloadUrl}]";
            }
        }

        if (empty(trim($text))) {
            return;
        }

        // Send typing indicator
        app(\App\Services\Messaging\MessagingRouter::class)->sendTypingOn($senderId);

        // Enqueue the message for batched processing
        $this->coordinator->enqueueMessage($senderId, $text, $messageId, $hasPhoto);
    }

    /**
     * Handle echo messages from the page.
     */
    private function handleEcho(array $event, string $senderId, string $recipientId): void
    {
        $messageData = $event['message'] ?? [];
        $appId = $messageData['app_id'] ?? null;
        $text = $messageData['text'] ?? '';

        // Ignore self-echo (from our own app)
        if ($appId && $appId == config('agent.fb_app_id')) {
            return;
        }

        // This is from a human agent managing the page
        if (!empty($text)) {
            $this->coordinator->pushEchoMessage($recipientId, $text);
        }
    }

    /**
     * Handle comments (currently disabled).
     */
    private function handleComments(array $changes): void
    {
        if (!config('agent.auto_reply_comments_enabled')) {
            return;
        }

        foreach ($changes as $change) {
            if (($change['field'] ?? '') !== 'feed') {
                continue;
            }

            $value = $change['value'] ?? [];
            $item = $value['item'] ?? '';
            $commentId = $value['comment_id'] ?? null;

            if ($item === 'comment' && $commentId) {
                Log::info("Comment received: {$commentId}");
                // Comment handling can be added here
            }
        }
    }

    /**
     * Estimate audio duration from file size.
     */
    private function estimateAudioDuration(string $url): int
    {
        try {
            $response = \Illuminate\Support\Facades\Http::head($url);
            $contentLength = $response->header('Content-Length');
            if ($contentLength) {
                return max(1, (int) ceil($contentLength / 35000));
            }
        } catch (\Exception $e) {
            // Ignore
        }
        return 5; // Default 5 seconds
    }
}

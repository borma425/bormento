<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Routes messages to the correct gateway based on userId prefix.
 * - "html-user-*" → HTML Chat (Cache queue for polling)
 * - "ig-*" → Instagram (future)
 * - default → Facebook Messenger
 */
class MessagingRouter
{
    public function __construct(
        private \App\Services\FacebookService $facebook,
        private \App\Services\InstagramService $instagram,
    ) {}

    public function sendTextMessage(string $recipientId, string $text): void
    {
        if (str_starts_with($recipientId, 'html-user-')) {
            $this->enqueueHtml($recipientId, 'text', $text);
        } elseif (str_starts_with($recipientId, 'ig-')) {
            $this->instagram->sendTextMessage(str_replace('ig-', '', $recipientId), $text);
        } else {
            $this->facebook->sendTextMessage($recipientId, $text);
        }
    }

    public function sendImageMessage(string $recipientId, array|string $imageUrls): void
    {
        $urls = is_array($imageUrls) ? $imageUrls : [$imageUrls];

        if (str_starts_with($recipientId, 'html-user-')) {
            foreach ($urls as $url) {
                $this->enqueueHtml($recipientId, 'image', $url);
            }
        } elseif (str_starts_with($recipientId, 'ig-')) {
            $this->instagram->sendImageMessage(str_replace('ig-', '', $recipientId), $urls);
        } else {
            $this->facebook->sendImageMessage($recipientId, $urls);
        }
    }

    public function sendVideoMessage(string $recipientId, string $videoUrl): void
    {
        if (str_starts_with($recipientId, 'html-user-')) {
            $this->enqueueHtml($recipientId, 'video', $videoUrl);
        } elseif (str_starts_with($recipientId, 'ig-')) {
            $this->instagram->sendVideoMessage(str_replace('ig-', '', $recipientId), $videoUrl);
        } else {
            $this->facebook->sendVideoMessage($recipientId, $videoUrl);
        }
    }

    public function sendTypingOn(string $recipientId): void
    {
        if (str_starts_with($recipientId, 'html-user-')) {
            return;
        }

        if (str_starts_with($recipientId, 'ig-')) {
            $this->instagram->sendTypingOn(str_replace('ig-', '', $recipientId));
        } else {
            $this->facebook->sendTypingOn($recipientId);
        }
    }

    public function getUserProfileName(string $userId, ?string $messageId = null): ?string
    {
        if (str_starts_with($userId, 'html-user-')) {
            return 'User';
        }

        if (str_starts_with($userId, 'ig-')) {
            return $this->instagram->getUserProfileName(str_replace('ig-', '', $userId), $messageId);
        }

        return $this->facebook->getUserProfileName($userId, $messageId);
    }

    // ---- HTML Chat Queue ----

    private function enqueueHtml(string $recipientId, string $type, string $content): void
    {
        $cacheKey = "html_chat:{$recipientId}";
        $messages = Cache::get($cacheKey, []);
        $messages[] = ['type' => $type, 'content' => $content];
        Cache::put($cacheKey, $messages, 300);
        Log::info("[HTMLChat] Queued {$type} for {$recipientId}");
    }

    /**
     * Pop all queued messages for an HTML chat user (used by polling endpoint).
     */
    public function popHtmlMessages(string $recipientId): array
    {
        $cacheKey = "html_chat:{$recipientId}";
        $messages = Cache::get($cacheKey, []);
        Cache::forget($cacheKey);
        return $messages;
    }
}

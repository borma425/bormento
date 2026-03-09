<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Attachment;

class FacebookService
{
    private string $pageAccessToken;
    private string $graphVersion = 'v19.0';
    private string $baseUrl;

    public function __construct()
    {
        $tenant = app()->bound(\App\Models\Tenant::class) ? app(\App\Models\Tenant::class) : null;
        $this->pageAccessToken = $tenant ? ($tenant->fb_page_token ?? '') : config('agent.fb_page_access_token', '');
        $this->baseUrl = "https://graph.facebook.com/{$this->graphVersion}";
    }

    /**
     * Send a raw message payload to Facebook Graph API.
     */
    public function sendMessage(string $recipientId, array $message): void
    {
        try {
            $payload = array_merge(['recipient' => ['id' => $recipientId]], $message);

            Http::post("{$this->baseUrl}/me/messages?access_token={$this->pageAccessToken}", $payload);
        } catch (\Exception $e) {
            Log::error("FacebookService::sendMessage failed: {$e->getMessage()}");
        }
    }

    /**
     * Send a text message to a user.
     */
    public function sendTextMessage(string $recipientId, string $text): void
    {
        $this->sendMessage($recipientId, ['message' => ['text' => $text]]);
    }

    /**
     * Send image(s) to a user.
     */
    public function sendImageMessage(string $recipientId, array $imageUrls): void
    {
        foreach ($imageUrls as $url) {
            $attachmentId = $this->getOrUploadAttachment($url, 'image');

            $message = $attachmentId
                ? ['message' => ['attachment' => ['type' => 'image', 'payload' => ['attachment_id' => $attachmentId]]]]
                : ['message' => ['attachment' => ['type' => 'image', 'payload' => ['url' => $url, 'is_reusable' => true]]]];

            $this->sendMessage($recipientId, $message);
        }
    }

    /**
     * Send a video to a user.
     */
    public function sendVideoMessage(string $recipientId, string $videoUrl): void
    {
        $attachmentId = $this->getOrUploadAttachment($videoUrl, 'video');

        $message = $attachmentId
            ? ['message' => ['attachment' => ['type' => 'video', 'payload' => ['attachment_id' => $attachmentId]]]]
            : ['message' => ['attachment' => ['type' => 'video', 'payload' => ['url' => $videoUrl, 'is_reusable' => true]]]];

        $this->sendMessage($recipientId, $message);
    }

    /**
     * Send typing indicator.
     */
    public function sendTypingOn(string $recipientId): void
    {
        $this->sendMessage($recipientId, ['sender_action' => 'typing_on']);
    }

    /**
     * Send a private reply to a comment.
     */
    public function sendPrivateReply(string $commentId, string $messageText): void
    {
        try {
            Http::post("{$this->baseUrl}/{$commentId}/private_replies", [
                'message' => $messageText,
                'access_token' => $this->pageAccessToken,
            ]);
        } catch (\Exception $e) {
            Log::error("FacebookService::sendPrivateReply failed: {$e->getMessage()}");
        }
    }

    /**
     * Reply to a Facebook comment publicly.
     */
    public function replyToComment(string $commentId, string $message): void
    {
        try {
            Http::post("{$this->baseUrl}/{$commentId}/comments", [
                'message' => $message,
                'access_token' => $this->pageAccessToken,
            ]);
        } catch (\Exception $e) {
            Log::error("FacebookService::replyToComment failed: {$e->getMessage()}");
        }
    }

    /**
     * Get user profile name from a message ID.
     */
    public function getUserProfileName(string $userId, string $messageId): ?string
    {
        try {
            $response = Http::get("{$this->baseUrl}/{$messageId}", [
                'fields' => 'from',
                'access_token' => $this->pageAccessToken,
            ]);

            if ($response->successful()) {
                return $response->json('from.name');
            }
        } catch (\Exception $e) {
            Log::error("FacebookService::getUserProfileName failed: {$e->getMessage()}");
        }
        return null;
    }

    /**
     * Get or upload an attachment and cache its ID.
     */
    private function getOrUploadAttachment(string $url, string $type): ?string
    {
        // Check cache first
        $attachment = Attachment::find($url);
        if ($attachment) {
            return $attachment->attachment_id;
        }

        // Upload attachment
        try {
            $response = Http::post("{$this->baseUrl}/me/message_attachments?access_token={$this->pageAccessToken}", [
                'message' => [
                    'attachment' => [
                        'type' => $type,
                        'payload' => ['url' => $url, 'is_reusable' => true],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $attachmentId = $response->json('attachment_id');
                if ($attachmentId) {
                    Attachment::updateOrCreate(
                        ['url' => $url],
                        ['attachment_id' => $attachmentId, 'type' => $type]
                    );
                    return $attachmentId;
                }
            }
        } catch (\Exception $e) {
            Log::error("FacebookService::getOrUploadAttachment failed: {$e->getMessage()}");
        }

        return null;
    }
}

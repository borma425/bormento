<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class InstagramService
{
    private ?string $accessToken;
    private string $graphVersion = 'v19.0';
    private string $baseUrl;

    public function __construct()
    {
        $tenant = app()->bound(\App\Models\Tenant::class) ? app(\App\Models\Tenant::class) : null;
        $this->accessToken = $tenant ? ($tenant->ig_access_token ?? '') : config('agent.ig_access_token', '');
        $this->baseUrl = "https://graph.instagram.com/{$this->graphVersion}";
    }

    /**
     * Send a raw message payload to Instagram.
     */
    public function sendMessage(string $recipientId, array $message): ?string
    {
        try {
            $payload = [
                'recipient' => ['id' => $recipientId],
                'message' => $message,
            ];

            // IG API has access_token in query
            $response = Http::post("{$this->baseUrl}/me/messages?access_token={$this->accessToken}", $payload);

            if (!$response->successful()) {
                Log::error('IG Graph Send API Error:', [
                    'recipientId' => $recipientId,
                    'status' => $response->status(),
                    'error' => $response->json()
                ]);
                return null;
            }

            return $response->json('message_id');
        } catch (\Exception $e) {
            Log::error('IG Graph Send Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get or upload an attachment to the IG Graph API.
     * IG requires pre-uploading media to get an attachment_id.
     */
    private function getOrUploadAttachment(string $url, string $type): ?string
    {
        // 1. Check Cache
        $cacheKey = "ig_attachment_id:" . md5($url . $type);
        $cachedId = Cache::get($cacheKey);

        if ($cachedId) {
            Log::info("[InstagramService] Using cached attachment ID for {$type}");
            return $cachedId;
        }

        // 2. Upload if not cached
        Log::info("[InstagramService] Uploading new {$type} to IG Graph...");
        
        $response = Http::post("{$this->baseUrl}/me/message_attachments?access_token={$this->accessToken}", [
            'message' => [
                'attachment' => [
                    'type' => $type,
                    'payload' => [
                        'url' => $url,
                        'is_reusable' => true,
                    ]
                ]
            ]
        ]);

        if (!$response->successful()) {
            Log::warning("[InstagramService] Failed to upload {$type} attachment.", [
                'url' => $url,
                'error' => $response->json()
            ]);
            return null;
        }

        $newAttachmentId = $response->json('attachment_id');

        // 3. Save to Cache
        if ($newAttachmentId) {
            // Cache for 30 days (FB/IG attachments last slightly longer but 30 days is safe)
            Cache::put($cacheKey, $newAttachmentId, now()->addDays(30));
        }

        return $newAttachmentId;
    }

    public function sendTextMessage(string $recipientId, string $text): ?string
    {
        return $this->sendMessage($recipientId, ['text' => $text]);
    }

    public function sendImageMessage(string $recipientId, array $imageUrls): array
    {
        $sentMids = [];

        foreach ($imageUrls as $url) {
            try {
                $attachmentId = $this->getOrUploadAttachment($url, 'image');

                $payload = $attachmentId 
                    ? ['attachment_id' => $attachmentId] 
                    : ['url' => $url, 'is_reusable' => true];

                $mid = $this->sendMessage($recipientId, [
                    'attachment' => [
                        'type' => 'image',
                        'payload' => $payload,
                    ],
                ]);

                if ($mid) {
                    $sentMids[] = $mid;
                }
            } catch (\Exception $e) {
                Log::error("IG Graph Send Image API Error for URL {$url}: " . $e->getMessage());
                // Continue with other images
            }
        }

        return $sentMids;
    }

    public function sendVideoMessage(string $recipientId, string $videoUrl): ?string
    {
        try {
            $attachmentId = $this->getOrUploadAttachment($videoUrl, 'video');

            $payload = $attachmentId 
                ? ['attachment_id' => $attachmentId] 
                : ['url' => $videoUrl, 'is_reusable' => true];

            return $this->sendMessage($recipientId, [
                'attachment' => [
                    'type' => 'video',
                    'payload' => $payload,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("IG Graph Send Video API Error: " . $e->getMessage());
            return null;
        }
    }

    public function sendTypingOn(string $recipientId): void
    {
        $payload = [
            'recipient' => ['id' => $recipientId],
            'sender_action' => 'typing_on',
        ];
        Http::post("{$this->baseUrl}/me/messages?access_token={$this->accessToken}", $payload);
    }

    public function getUserProfileName(string $userId, ?string $messageId = null): ?string
    {
        if (!$messageId) {
            return null;
        }

        try {
            $response = Http::get("{$this->baseUrl}/{$messageId}", [
                'fields' => 'from',
                'access_token' => $this->accessToken
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['from']['name'] ?? null;
            }

            Log::warning("IG Graph Get User Profile Name Error:", $response->json() ?? []);
            return null;
        } catch (\Exception $e) {
            Log::error("IG Graph Get User Profile Name Exception: " . $e->getMessage());
            return null;
        }
    }
}

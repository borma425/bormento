<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    private string $model;
    private string $baseUrl = 'https://openrouter.ai/api/v1';

    public function __construct()
    {
        $this->model = config('agent.openrouter_model');
    }

    private function getApiKey(): string
    {
        if (app()->bound(\App\Models\Tenant::class)) {
            $tenant = app(\App\Models\Tenant::class);
            if (!empty($tenant->openai_api_key)) {
                return $tenant->openai_api_key;
            }
            throw new \RuntimeException("TENANT_UNCONFIGURED");
        }
        return config('agent.openrouter_api_key');
    }

    /**
     * Send a chat completion request with optional tools.
     *
     * @param array $messages Array of message objects [{role, content, tool_call_id?, tool_calls?}]
     * @param array $tools Array of tool definitions in OpenAI format
     * @param float $temperature
     * @return array The full response data
     */
    public function chat(array $messages, array $tools = [], float $temperature = 0): array
    {
        $apiKey = $this->getApiKey();
        
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
            'HTTP-Referer' => 'https://store-agent.local',
            'X-Title' => 'Store Agent SaaS',
        ])->timeout(120)->post("{$this->baseUrl}/chat/completions", $payload);

        if (!$response->successful()) {
            Log::error("OpenRouter chat failed: " . $response->body());
            throw new \RuntimeException("OpenRouter API error: " . $response->status() . " - " . $response->body());
        }

        return $response->json();
    }

    /**
     * Analyze media (image/audio) using a secondary LLM call.
     */
    public function analyzeMedia(string $url, string $mediaType, string $question): string
    {
        $contentParts = [];
        $contentParts[] = ['type' => 'text', 'text' => $question];

        if ($mediaType === 'image') {
            $contentParts[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $url],
            ];
        } elseif ($mediaType === 'audio') {
            $contentParts[] = [
                'type' => 'input_audio',
                'input_audio' => ['data' => $url, 'format' => 'mp3'],
            ];
        }

        $messages = [
            ['role' => 'user', 'content' => $contentParts],
        ];

        $response = $this->chat($messages);
        return $response['choices'][0]['message']['content'] ?? 'Could not analyze media.';
    }
}

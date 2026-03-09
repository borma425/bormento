<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PineconeService
{
    private string $apiKey;
    private string $indexName;
    private ?string $host = null;
    private ?string $namespace = null;

    public function __construct()
    {
        $this->apiKey = config('agent.pinecone_api_key');
        $this->indexName = config('agent.pinecone_index_name', 'clothing-store-index');
        
        $tenant = app()->bound(\App\Models\Tenant::class) ? app(\App\Models\Tenant::class) : null;
        $this->namespace = $tenant ? (string) $tenant->id : null;
    }

    /**
     * Set explicit namespace (useful for background jobs/observers).
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Get the Pinecone index host URL.
     */
    private function getHost(): string
    {
        if ($this->host) {
            return $this->host;
        }

        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
        ])->get("https://api.pinecone.io/indexes/{$this->indexName}");

        if ($response->successful()) {
            $this->host = $response->json('host');
        }

        if (!$this->host) {
            throw new \RuntimeException("Failed to resolve Pinecone host for index: {$this->indexName}");
        }

        return $this->host;
    }

    /**
     * Generate embeddings via OpenRouter.
     */
    public function embedText(string $text): array
    {
        $tenant = app()->bound(\App\Models\Tenant::class) ? app(\App\Models\Tenant::class) : null;
        if ($tenant) {
            if (empty($tenant->openai_api_key)) {
                throw new \RuntimeException("TENANT_UNCONFIGURED");
            }
            $apiKey = $tenant->openai_api_key;
        } else {
            $apiKey = config('agent.openrouter_api_key');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => 'https://clothes-store.local',
            'X-Title' => 'Store Inventory',
        ])->post('https://openrouter.ai/api/v1/embeddings', [
            'model' => 'text-embedding-3-small',
            'input' => $text,
            'dimensions' => 1024,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Embedding failed: " . $response->body());
        }

        return $response->json('data.0.embedding', []);
    }

    /**
     * Query Pinecone for similar vectors.
     *
     * @return array List of matches with id and score.
     */
    public function query(array $vector, int $topK = 5): array
    {
        $host = $this->getHost();

        $payload = [
            'vector' => $vector,
            'topK' => $topK,
            'includeMetadata' => true,
        ];

        if ($this->namespace) {
            $payload['namespace'] = $this->namespace;
        }

        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("https://{$host}/query", $payload);

        if (!$response->successful()) {
            Log::error("Pinecone query failed: " . $response->body());
            return [];
        }

        return $response->json('matches', []);
    }

    /**
     * Upsert vectors to Pinecone.
     */
    public function upsert(array $vectors): void
    {
        $host = $this->getHost();

        $payload = ['vectors' => $vectors];
        if ($this->namespace) {
            $payload['namespace'] = $this->namespace;
        }

        Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("https://{$host}/vectors/upsert", $payload);
    }

    /**
     * Delete vectors from Pinecone by ID.
     */
    public function delete(array $ids): void
    {
        $host = $this->getHost();

        $payload = ['ids' => $ids];
        if ($this->namespace) {
            $payload['namespace'] = $this->namespace;
        }

        Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("https://{$host}/vectors/delete", $payload);
    }

    /**
     * Search products by query string.
     * Returns array of product IDs with scores.
     */
    public function searchProducts(string $query): array
    {
        $vector = $this->embedText($query);
        $matches = $this->query($vector, 5);

        return array_map(function ($match) {
            return [
                'id' => $match['id'],
                'score' => $match['score'] ?? 0,
                'metadata' => $match['metadata'] ?? [],
            ];
        }, $matches);
    }
}

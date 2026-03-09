<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Contracts\ECommerceProviderInterface;

class StandardECommerceProvider implements ECommerceProviderInterface
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct(array $config = [])
    {
        $this->apiUrl = rtrim($config['api_url'] ?? config('agent.gravoni_api_url', ''), '/');
        $this->apiKey = $config['secret_key'] ?? config('agent.api_secret_key', '');
    }

    private function headers(bool $withContentType = false): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-API-Key' => $this->apiKey,
        ];
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    // ---- Products ----

    public function getProducts(): array
    {
        $response = Http::withHeaders($this->headers())->get("{$this->apiUrl}/api/products");
        if (!$response->successful()) return [];
        $json = $response->json();
        return $json['data'] ?? $json;
    }

    public function getProductById(string $id): ?array
    {
        $response = Http::withHeaders($this->headers())->get("{$this->apiUrl}/api/products/{$id}");
        if (!$response->successful()) return null;
        $json = $response->json();
        // Unwrap: {"success": true, "data": {...}}
        return $json['data'] ?? $json;
    }

    public function searchProducts(string $query): array
    {
        $response = Http::withHeaders($this->headers())->get("{$this->apiUrl}/api/products", [
            'search' => $query
        ]);
        if (!$response->successful()) return [];
        $json = $response->json();
        return $json['data'] ?? $json;
    }

    // ---- Governorates / Delivery Fees ----

    public function getGovernoratesWithFees(): array
    {
        return Cache::remember('governorate_fees', 3600, function () {
            $response = Http::withHeaders($this->headers())->get("{$this->apiUrl}/api/governorates");

            if (!$response->successful()) {
                Log::error("StandardECommerceProvider: Failed to fetch governorates: " . $response->body());
                return [];
            }

            $json = $response->json();

            // API returns: {"success": true, "data": {"البحيرة": 100, "القاهرة": 100, ...}}
            return $json['data'] ?? $json;
        });
    }

    public function getDeliveryFeesByGovernorate(string $governorateName): ?float
    {
        $governorates = $this->getGovernoratesWithFees();

        Log::info("StandardECommerceProvider: Looking up delivery fees for '{$governorateName}'", [
            'available' => array_keys($governorates),
        ]);

        // Direct key lookup (exact match)
        if (isset($governorates[$governorateName])) {
            return (float) $governorates[$governorateName];
        }

        // Case-insensitive / normalized Arabic match
        foreach ($governorates as $name => $fee) {
            if (mb_strtolower(trim($name)) === mb_strtolower(trim($governorateName))) {
                return (float) $fee;
            }
        }

        // Partial match (e.g., "البحيره" vs "البحيرة")
        foreach ($governorates as $name => $fee) {
            if (str_contains($name, $governorateName) || str_contains($governorateName, $name)) {
                return (float) $fee;
            }
        }

        Log::warning("StandardECommerceProvider: Governorate '{$governorateName}' not found.");
        return null;
    }

    // ---- Orders ----

    public function createOrder(array $orderData): array
    {
        $response = Http::withHeaders($this->headers(true))
            ->post("{$this->apiUrl}/api/orders", $orderData);

        if (!$response->successful()) {
            throw new \RuntimeException("Gravoni createOrder failed: " . $response->body());
        }

        return $response->json();
    }

    public function getOrderById(string $orderId): ?array
    {
        $response = Http::withHeaders($this->headers())->get("{$this->apiUrl}/api/orders/{$orderId}");
        return $response->successful() ? $response->json() : null;
    }

    public function updateOrder(string $orderId, array $updates): array
    {
        $response = Http::withHeaders($this->headers(true))
            ->put("{$this->apiUrl}/api/orders/{$orderId}", $updates);

        if (!$response->successful()) {
            throw new \RuntimeException("Gravoni updateOrder failed: " . $response->body());
        }

        return $response->json() ?? [];
    }
}

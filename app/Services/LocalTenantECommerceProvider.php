<?php

namespace App\Services;

use App\Contracts\ECommerceProviderInterface;
use App\Models\Product;
use App\Models\Tenant;

class LocalTenantECommerceProvider implements ECommerceProviderInterface
{
    private ?Tenant $tenant;

    public function __construct()
    {
        $this->tenant = app()->bound(Tenant::class) ? app(Tenant::class) : null;
    }

    public function getProducts(): array
    {
        if (!$this->tenant) {
            return [];
        }

        return Product::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->get()
            ->map(fn($product) => $this->formatProduct($product))
            ->toArray();
    }

    public function getProductById(string $id): ?array
    {
        if (!$this->tenant) {
            return null;
        }

        $product = Product::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->find($id);

        return $product ? $this->formatProduct($product) : null;
    }

    public function getProductBySku(string $sku): ?array
    {
        if (!$this->tenant) {
            return null;
        }

        $product = Product::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->where('sku', $sku)
            ->first();

        return $product ? $this->formatProduct($product) : null;
    }

    private function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => $product->price,
            'discounted_price' => $product->discounted_price,
            'currency' => $product->currency,
            'description' => $product->description,
            'media' => $product->media,
            'attributes' => $product->attributes,
        ];
    }

    public function searchProducts(string $query): array
    {
        if (!$this->tenant) {
            return [];
        }

        // Basic SQL LIKE search as a fallback if RAG isn't used
        return Product::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->get()
            ->map(fn($product) => $this->formatProduct($product))
            ->toArray();
    }

    public function getDeliveryFeesByGovernorate(string $governorateName): ?float
    {
        if (!$this->tenant || empty($this->tenant->shipping_zones)) {
            return 70.0; // Global fallback
        }

        // Search the tenant's dynamic JSON shipping_zones for the exact governorate match
        foreach ($this->tenant->shipping_zones as $zone) {
            if (mb_strtolower(trim($zone['governorate'])) === mb_strtolower(trim($governorateName))) {
                return (float) $zone['fee'];
            }
        }

        return 70.0; // Fallback if no matching zone found for this tenant
    }

    public function createOrder(array $orderData): array
    {
        if ($this->tenant && $this->tenant->reply_only_mode) {
            return [
                'success' => false,
                'error' => 'REPLY_ONLY_MODE_ENABLED',
                'message' => 'Store is currently in Reply-Only mode. Orders are disabled.'
            ];
        }

        return ['success' => true, 'order_id' => 'SAAS-' . rand(1000, 9999)];
    }

    public function updateOrder(string $orderId, array $updates): array
    {
        return ['success' => true, 'order_id' => $orderId];
    }

    public function getOrderById(string $orderId): ?array
    {
        return [
            'order_id' => $orderId,
            'status' => 'pending',
            'total' => 0
        ];
    }
}

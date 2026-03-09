<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Contracts\ECommerceProviderInterface;

class InventoryService
{
    private PineconeService $pinecone;
    private ECommerceProviderInterface $ecommerce;

    public function __construct(PineconeService $pinecone, ECommerceProviderInterface $ecommerce)
    {
        $this->pinecone = $pinecone;
        $this->ecommerce = $ecommerce;
    }

    /**
     * Get all inventory products.
     */
    public function getInventory(): array
    {
        return $this->ecommerce->getProducts();
    }

    /**
     * Search products using Pinecone vector search.
     */
    public function searchProducts(string $query): array
    {
        try {
            $embedding = $this->pinecone->embedText($query);
            $matches = $this->pinecone->query($embedding, 5);
            $products = [];

            foreach ($matches as $match) {
                $product = $this->ecommerce->getProductById($match['id']);
                if ($product) {
                    $products[] = $product;
                }
            }

            return $products;
        } catch (\Exception $e) {
            Log::error("InventoryService::searchProducts failed: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Get a product by ID.
     */
    public function getProductById(string $id): ?array
    {
        return $this->ecommerce->getProductById($id);
    }
}

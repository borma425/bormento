<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    private function syncToPinecone(Product $product): void
    {
        // Require tenant ID logically
        if (!$product->tenant_id) {
            return;
        }

        $pinecone = app(\App\Services\PineconeService::class);
        $pinecone->setNamespace((string) $product->tenant_id);

        if (!$product->is_active) {
            $pinecone->delete([(string) $product->id]);
            return;
        }

        // Microsoft Advanced RAG Pattern: Alignment Optimization
        // We prepare a highly optimized textual representation of the product 
        // to maximize cosine similarity matches for customer queries.
        $text = "Product: {$product->name}\n";
        $text .= "Price: {$product->price} {$product->currency}\n";
        if ($product->discounted_price) {
            $text .= "Discount Price: {$product->discounted_price} {$product->currency}\n";
        }
        $text .= "Description: {$product->description}\n";
        
        // Deep Variant Alignment for Azure RAG Pattern
        if (!empty($product->attributes) && is_array($product->attributes)) {
            $text .= "Available Variants & Stock Levels:\n";
            foreach ($product->attributes as $variant) {
                if (!is_array($variant)) continue;
                $vName = $variant['variant_name'] ?? 'Unknown Variant';
                $vStock = $variant['stock'] ?? 0;
                $vMod = $variant['price_modifier'] ?? 0;
                
                $text .= "- {$vName}: [Stock: {$vStock}]";
                if ((float)$vMod > 0) {
                    $text .= " [Extra Fee: +{$vMod} EGP]";
                }
                $text .= "\n";
            }
        }

        try {
            // Generate Embedding
            $embedding = $pinecone->embedText($text);

            // Upsert to Pinecone Vector Database
            $pinecone->upsert([
                [
                    'id' => (string) $product->id,
                    'values' => $embedding,
                    'metadata' => [
                        'name' => $product->name,
                        'price' => $product->price,
                        'discounted_price' => $product->discounted_price,
                        'attributes' => json_encode($product->attributes),
                    ]
                ]
            ]);
            \Illuminate\Support\Facades\Log::info("Synced product {$product->id} to Pinecone RAG index for Tenant {$product->tenant_id}.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to sync product {$product->id} to Pinecone: " . $e->getMessage());
        }
    }

    /**
     * Handle the Product "saved" event (covers created and updated).
     */
    public function saved(Product $product): void
    {
        $this->syncToPinecone($product);
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        if (!$product->tenant_id) {
            return;
        }

        try {
            $pinecone = app(\App\Services\PineconeService::class);
            $pinecone->setNamespace((string) $product->tenant_id);
            $pinecone->delete([(string) $product->id]);
            \Illuminate\Support\Facades\Log::info("Deleted product {$product->id} from Pinecone RAG index.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to delete product {$product->id} from Pinecone: " . $e->getMessage());
        }
    }
}

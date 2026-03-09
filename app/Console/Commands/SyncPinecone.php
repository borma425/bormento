<?php

namespace App\Console\Commands;

use App\Services\StandardECommerceProvider;
use App\Services\PineconeService;
use Illuminate\Console\Command;

class SyncPinecone extends Command
{
    protected $signature = 'agent:sync-pinecone';
    protected $description = 'Sync products from Gravoni API to Pinecone vector database';

    public function handle(StandardECommerceProvider $gravoni, PineconeService $pinecone): int
    {
        $this->info('Fetching products from Gravoni API...');
        $products = $gravoni->getProducts();

        if (empty($products)) {
            $this->error('No products found.');
            return 1;
        }

        $this->info("Found " . count($products) . " products. Syncing to Pinecone...");

        $vectors = [];
        $bar = $this->output->createProgressBar(count($products));

        foreach ($products as $product) {
            $id = (string) ($product['id'] ?? '');
            $name = $product['name'] ?? '';
            $description = $product['description'] ?? '';
            $price = $product['price'] ?? 0;
            $discountedPrice = $product['discounted_price'] ?? null;

            // Build descriptive text for embedding
            $text = "{$name}. {$description}. Price: {$price}";
            if ($discountedPrice) {
                $text .= " (Discounted: {$discountedPrice})";
            }

            // Get available sizes
            $sizes = [];
            $colors = [];
            foreach ($product['availability'] ?? [] as $avail) {
                $sizes[] = $avail['size'] ?? '';
                foreach ($avail['colors'] ?? [] as $colorStock) {
                    $colors[] = $colorStock['color'] ?? '';
                }
            }
            $sizes = array_unique(array_filter($sizes));
            $colors = array_unique(array_filter($colors));

            if (!empty($sizes)) {
                $text .= " Sizes: " . implode(', ', $sizes);
            }
            if (!empty($colors)) {
                $text .= " Colors: " . implode(', ', $colors);
            }

            try {
                $embedding = $pinecone->embedText($text);
                $vectors[] = [
                    'id' => $id,
                    'values' => $embedding,
                    'metadata' => [
                        'name' => $name,
                        'price' => $price,
                        'discounted_price' => $discountedPrice,
                    ],
                ];
            } catch (\Exception $e) {
                $this->warn("Failed to embed product {$id}: {$e->getMessage()}");
            }

            $bar->advance();

            // Batch upsert every 50 vectors
            if (count($vectors) >= 50) {
                $pinecone->upsert($vectors);
                $vectors = [];
            }
        }

        // Upsert remaining vectors
        if (!empty($vectors)) {
            $pinecone->upsert($vectors);
        }

        $bar->finish();
        $this->newLine();
        $this->info('Pinecone sync completed successfully!');

        return 0;
    }
}

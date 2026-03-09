<?php

namespace App\Contracts;

interface ECommerceProviderInterface
{
    /**
     * Get all products.
     */
    public function getProducts(): array;

    /**
     * Get a specific product by ID.
     */
    public function getProductById(string $id): ?array;

    /**
     * Search for products matching the query.
     */
    public function searchProducts(string $query): array;

    /**
     * Get the delivery fee for a specific governorate/city.
     */
    public function getDeliveryFeesByGovernorate(string $governorateName): ?float;

    /**
     * Create a new order in the e-commerce system.
     */
    public function createOrder(array $orderData): array;

    /**
     * Update an existing order.
     */
    public function updateOrder(string $orderId, array $updates): array;

    /**
     * Retrieve an order by ID.
     */
    public function getOrderById(string $orderId): ?array;
}

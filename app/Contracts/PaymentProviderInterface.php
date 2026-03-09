<?php

namespace App\Contracts;

interface PaymentProviderInterface
{
    /**
     * Generate a payment link or intent for an order.
     * 
     * @param string $orderId
     * @param float $amount
     * @param string|null $productName
     * @return array Contains 'payment_intent_id' and 'instructions'
     */
    public function createPaymentIntent(string $orderId, float $amount, ?string $productName = null): array;

    /**
     * Validate a previously created payment intent.
     * 
     * @param string $paymentIntentId
     * @param string $senderIdentifier e.g. receipt URL or wallet number
     * @return array Contains 'success' boolean and 'message'
     */
    public function validatePayment(string $paymentIntentId, string $senderIdentifier): array;
}

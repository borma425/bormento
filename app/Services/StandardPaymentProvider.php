<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Contracts\PaymentProviderInterface;

class StandardPaymentProvider implements PaymentProviderInterface
{
    private string $apiUrl;
    private string $apiKey;
    private string $appId;

    public function __construct(array $config = [])
    {
        $this->apiUrl = rtrim($config['api_url'] ?? config('agent.cashup_api_url', ''), '/');
        $this->apiKey = $config['api_key'] ?? config('agent.cashup_api_key', '');
        $this->appId = $config['app_id'] ?? config('agent.cashup_app_id', '');
    }

    /**
     * Create a payment intent.
     *
     * @return array{paymentIntentId: string, receiverNumber: string, instructions: string}
     */
    public function createPaymentIntent(string $orderId, float $amount, ?string $productName = null): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/api/v1/transactions/{$this->appId}/payment_intents", [
            'amount' => $amount,
            'order_id' => $orderId,
            'product_name' => $productName ?? 'Delivery Fees',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("CashUp createPaymentIntent failed: " . $response->body());
        }

        $data = $response->json();

        return [
            'paymentIntentId' => $data['payment_intent_id'] ?? $data['id'] ?? '',
            'receiverNumber' => $data['receiver_number'] ?? '',
            'instructions' => $data['instructions'] ?? '',
        ];
    }

    /**
     * Validate a payment.
     *
     * @return array{success: bool, status: string, message: string, amount_paid: float, sender_name: string, order_id: string, payment_method: string, extracted_sender_name?: string}
     */
    public function validatePayment(string $paymentIntentId, string $senderIdentifier): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/api/v1/transactions/payment_intents/{$paymentIntentId}/validate", [
            'sender_identifier' => $senderIdentifier,
        ]);

        $data = $response->json();

        // Handle TRANSACTION_ALREADY_PROCESSED
        if (isset($data['error']) && str_contains($data['error'], 'ALREADY_PROCESSED')) {
            return [
                'success' => true,
                'status' => 'already_processed',
                'message' => 'Transaction was already processed.',
                'amount_paid' => $data['amount_paid'] ?? 0,
                'sender_name' => $data['sender_name'] ?? '',
                'order_id' => $data['order_id'] ?? '',
                'payment_method' => $data['payment_method'] ?? 'WALLET',
            ];
        }

        if (!$response->successful()) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => $data['message'] ?? 'Validation failed.',
                'amount_paid' => 0,
                'sender_name' => '',
                'order_id' => '',
                'payment_method' => '',
            ];
        }

        return [
            'success' => true,
            'status' => $data['status'] ?? 'validated',
            'message' => $data['message'] ?? 'Payment validated successfully.',
            'amount_paid' => $data['amount_paid'] ?? 0,
            'sender_name' => $data['sender_name'] ?? '',
            'order_id' => $data['order_id'] ?? '',
            'payment_method' => $data['payment_method'] ?? 'WALLET',
            'extracted_sender_name' => $data['extracted_sender_name'] ?? null,
        ];
    }
}

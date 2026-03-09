<?php

namespace App\Services\Agent;

use App\Contracts\PaymentProviderInterface;
use App\Services\Messaging\MessagingRouter;
use App\Contracts\ECommerceProviderInterface;
use App\Services\InventoryService;
use App\Services\OpenRouterService;
use App\Services\ChatService;
use Illuminate\Support\Facades\Log;

class ToolExecutor
{
    public function __construct(
        private InventoryService $inventory,
        private ECommerceProviderInterface $ecommerce,
        private PaymentProviderInterface $payment,
        private MessagingRouter $messaging,
        private OpenRouterService $openRouter,
        private ChatService $chatService,
    ) {}

    /**
     * Execute a tool call and return the result as a string.
     */
    public function execute(string $toolName, array $args, string $userId): string
    {
        Log::info("Tool call: {$toolName}", ['args' => $args, 'userId' => $userId]);

        try {
            return match ($toolName) {
                'inventory_search' => $this->inventorySearch($args),
                'calculate_delivery_fees' => $this->calculateDeliveryFees($args),
                'create_order' => $this->createOrder($args, $userId),
                'update_order' => $this->updateOrder($args),
                'get_order_by_id' => $this->getOrderById($args),
                'create_payment' => $this->createPayment($args),
                'validate_payment' => $this->validatePayment($args),
                'route_to_agent' => $this->routeToAgent($args, $userId),
                'send_product_video' => $this->sendProductVideo($args, $userId),
                'send_product_samples' => $this->sendProductSamples($args, $userId),
                'send_message' => $this->sendMessageTool($args, $userId),
                'analyze_media' => $this->analyzeMedia($args),
                'simulate_delay' => $this->simulateDelay($args),
                default => json_encode(['error' => "Unknown tool: {$toolName}"]),
            };
        } catch (\Throwable $e) {
            Log::error("ToolExecutor::{$toolName} failed: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            return json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ---- Inventory ----

    private function inventorySearch(array $args): string
    {
        $query = $args['query'] ?? '';
        Log::info("[TOOL:inventory_search] 🔍 Searching for: \"{$query}\"");

        $products = $this->inventory->searchProducts($query);

        if (empty($products)) {
            return 'No products found matching your search. Try different keywords.';
        }

        Log::info("[TOOL:inventory_search] Found " . count($products) . " products");
        return json_encode($products);
    }

    // ---- Delivery Fees ----

    private function calculateDeliveryFees(array $args): string
    {
        $governorate = $args['governorate'] ?? '';
        Log::info("[TOOL:calculate_delivery_fees] 🟢 Triggered for: {$governorate}");

        $fee = $this->ecommerce->getDeliveryFeesByGovernorate($governorate);

        if ($fee === null) {
            return 'Governorate not recognized. Please ask the user to double check the spelling.';
        }

        Log::info("[TOOL:calculate_delivery_fees] Fees: {$fee}");
        return json_encode(['governorate' => $governorate, 'delivery_fees' => $fee]);
    }

    // ---- Orders ----

    private function createOrder(array $args, string $userId): string
    {
        Log::info("[TOOL:create_order] 🟢 Triggered", ['input' => $args]);

        // Build order data for Gravoni API
        $orderData = [
            'userId' => $args['userId'] ?? $userId,
            'customer_name' => $args['customer_name'] ?? '',
            'customer_address' => $args['customer_address'] ?? '',
            'customer_numbers' => $args['customer_numbers'] ?? [],
            'delivery_fees' => $args['delivery_fees'] ?? 0,
            'governorate' => $args['governorate'] ?? '',
            'items' => $args['items'] ?? [],
            'total_amount' => $args['total_amount'] ?? 0,
            'payment_method' => $args['payment_method'] ?? null,
            'status' => 'pending',
        ];

        // Validate required fields
        if (empty($orderData['customer_name'])) {
            return json_encode(['success' => false, 'message' => 'Customer name is required']);
        }
        if (empty($orderData['customer_address'])) {
            return json_encode(['success' => false, 'message' => 'Customer address is required']);
        }
        if (empty($orderData['customer_numbers'])) {
            return json_encode(['success' => false, 'message' => 'Customer numbers is required']);
        }
        if (empty($orderData['items'])) {
            return json_encode(['success' => false, 'message' => 'Items is required']);
        }

        // Validate delivery fees match actual
        $actualFees = $this->ecommerce->getDeliveryFeesByGovernorate($orderData['governorate']);
        if ($actualFees !== null && $orderData['delivery_fees'] != $actualFees) {
            return json_encode([
                'success' => false,
                'message' => "Delivery fees does not match. Expected: {$actualFees}, Got: {$orderData['delivery_fees']}",
            ]);
        }

        $result = $this->ecommerce->createOrder($orderData);

        // Extract order ID from response
        $orderId = $result['id'] ?? $result['order_id'] ?? $result['data']['id'] ?? null;

        Log::info("[TOOL:create_order] ✅ Success. Order ID: {$orderId}");
        return json_encode(['orderId' => $orderId, 'status' => 'pending']);
    }

    private function updateOrder(array $args): string
    {
        $id = $args['id'] ?? '';
        Log::info("[TOOL:update_order] 🟢 Triggered for order: {$id}");

        if (empty($id)) {
            return json_encode(['success' => false, 'message' => 'Order ID is required']);
        }

        // Fetch current order to merge
        $currentOrder = $this->ecommerce->getOrderById($id);
        if (!$currentOrder) {
            return json_encode(['success' => false, 'message' => "Order {$id} not found"]);
        }

        // Prevent updating sensitive fields without authorization
        if (isset($args['status']) || isset($args['payment_method'])) {
            return json_encode(['success' => false, 'message' => "Unauthorized update: you don't have permission to update status or payment_method."]);
        }

        unset($args['id']);
        $updateData = array_merge($currentOrder, $args);

        $result = $this->ecommerce->updateOrder($id, $updateData);
        if (!$result) {
            return json_encode(['success' => false, 'error' => 'Failed to update order.']);
        }

        Log::info("[TOOL:update_order] ✅ Success. Order ID: {$id}");
        return json_encode(['orderId' => $id, 'status' => $result['status'] ?? 'pending']);
    }

    private function getOrderById(array $args): string
    {
        $orderId = $args['orderId'] ?? '';
        Log::info("[TOOL:get_order_by_id] 🟢 Triggered for: {$orderId}");

        $order = $this->ecommerce->getOrderById($orderId);
        if (!$order) {
            return json_encode(['success' => false, 'error' => 'Order not found.']);
        }

        Log::info("[TOOL:get_order_by_id] ✅ Success. Order ID: {$orderId}");
        return json_encode(['orderId' => $orderId, 'status' => $order['status'] ?? 'unknown']);
    }

    // ---- Payment ----

    private function createPayment(array $args): string
    {
        $orderId = $args['orderId'] ?? '';
        $amount = (float) ($args['amount'] ?? 0);

        Log::info("[TOOL:create_payment] 🟢 Triggered with OrderID: {$orderId}, Amount: {$amount}");

        // Verify order exists
        $order = $this->ecommerce->getOrderById($orderId);
        if (!$order) {
            return 'Order not found.';
        }

        // Validate amount matches delivery fees
        $orderFees = (float) ($order['delivery_fees'] ?? 0);
        if ($orderFees > 0 && $orderFees != $amount) {
            return "Invalid amount. The delivery fees for this order are {$orderFees} EGP, but you requested {$amount} EGP.";
        }

        $result = $this->payment->createPaymentIntent($orderId, $amount, 'Fashion Items Deposit');

        Log::info("[TOOL:create_payment] ✅ Success. Intent ID: " . ($result['paymentIntentId'] ?? 'unknown'));

        return json_encode([
            'instructions' => $result['instructions'] ?? '',
            'payment_intent_id' => $result['paymentIntentId'] ?? '',
            'receiver_number' => $result['receiverNumber'] ?? '',
            'amount_requested' => $amount,
        ]);
    }

    private function validatePayment(array $args): string
    {
        $paymentIntentId = $args['payment_intent_id'] ?? '';
        $senderIdentifier = $args['sender_identifier'] ?? '';

        Log::info("[TOOL:validate_payment] 🟢 Triggered for Intent: {$paymentIntentId}");

        $result = $this->payment->validatePayment($paymentIntentId, $senderIdentifier);

        if (!empty($result['success']) && $result['success']) {
            Log::info("[TOOL:validate_payment] ✅ Success", $result);

            // Update order status to delivery_fees_paid
            $orderId = $result['order_id'] ?? '';
            if ($orderId) {
                try {
                    $this->ecommerce->updateOrder($orderId, [
                        'status' => 'delivery_fees_paid',
                        'payment_method' => $result['payment_method'] ?? '',
                    ]);
                    Log::info("[TOOL:validate_payment] Order {$orderId} status updated to delivery_fees_paid");
                } catch (\Exception $e) {
                    Log::error("[TOOL:validate_payment] Failed to update order status: {$e->getMessage()}");
                }
            }
        } else {
            Log::info("[TOOL:validate_payment] ❌ Failed", $result);
        }

        return json_encode($result);
    }

    // ---- Routing ----

    private function routeToAgent(array $args, string $userId): string
    {
        $targetAgent = $args['targetAgent'] ?? 'greeting';
        $reason = $args['reason'] ?? '';

        // Check current agent to avoid redundant routing
        $currentAgent = $this->chatService->getAgentType($userId);
        if ($currentAgent === $targetAgent) {
            Log::info("[TOOL:route_to_agent] 🔄 Skip: already on {$targetAgent}");
            return "Successfully verified you are already on the {$targetAgent} agent. No routing needed.";
        }

        Log::info("[TOOL:route_to_agent] 🔀 Routing from {$currentAgent} to {$targetAgent}. Reason: {$reason}");

        return json_encode([
            'action' => 'ROUTE_TO_AGENT',
            'targetAgent' => $targetAgent,
            'reason' => $reason,
        ]);
    }

    // ---- Messaging ----

    private function sendProductVideo(array $args, string $userId): string
    {
        $videoUrl = $args['videoUrl'] ?? '';
        Log::info("[TOOL:send_product_video] 🎥 Sending video to {$userId}");

        if ($videoUrl) {
            $this->messaging->sendVideoMessage($userId, $videoUrl);
        }
        return 'Video sent successfully.';
    }

    private function sendProductSamples(array $args, string $userId): string
    {
        $imageUrls = $args['imageUrls'] ?? [];
        Log::info("[TOOL:send_product_samples] 🖼️ Sending " . count($imageUrls) . " samples to {$userId}");

        if (!empty($imageUrls)) {
            $this->messaging->sendImageMessage($userId, $imageUrls);
        }
        return 'Images sent successfully.';
    }

    private function sendMessageTool(array $args, string $userId): string
    {
        $message = $args['message'] ?? '';
        Log::info("[TOOL:send_message] ✉️ Sending mid-turn message to {$userId}: \"{$message}\"");

        if ($message) {
            $this->messaging->sendTextMessage($userId, $message);
        }
        return 'Message sent successfully.';
    }

    // ---- Media Analysis ----

    private function analyzeMedia(array $args): string
    {
        $url = $args['url'] ?? '';
        $mediaType = $args['media_type'] ?? 'image';
        $question = $args['question'] ?? 'Describe this media.';

        Log::info("[TOOL:analyze_media] Analyzing {$mediaType} for: \"{$question}\"");

        $result = $this->openRouter->analyzeMedia($url, $mediaType, $question);
        return $result ?: 'UNABLE_TO_DETERMINE';
    }

    // ---- Delay ----

    private function simulateDelay(array $args): string
    {
        $seconds = min($args['seconds'] ?? 1, 60);
        Log::info("[TOOL:simulate_delay] Agent paused for {$seconds} seconds.");
        sleep((int) $seconds);
        return "Successfully waited for {$seconds} seconds. You may now continue processing.";
    }
}

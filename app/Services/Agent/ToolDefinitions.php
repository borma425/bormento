<?php

namespace App\Services\Agent;

/**
 * Tool definitions in OpenAI function-calling format.
 * Descriptions match the original backend-agent project EXACTLY.
 */
class ToolDefinitions
{
    /**
     * Helper to build a tool definition in OpenAI format.
     */
    private static function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ],
            ],
        ];
    }

    /**
     * Get tools for a specific agent type.
     */
    public static function forAgent(string $agentType): array
    {
        return match ($agentType) {
            'greeting' => self::greetingTools(),
            'catalog' => self::catalogTools(),
            'ordering' => self::orderingTools(),
            default => self::greetingTools(),
        };
    }

    // ---- Agent Tool Sets (matching original project exactly) ----

    private static function greetingTools(): array
    {
        return [
            self::routeToAgent(),
            self::sendMessage(),
            self::analyzeMedia(),
            self::simulateDelay(),
        ];
    }

    private static function catalogTools(): array
    {
        return [
            self::inventorySearch(),
            self::routeToAgent(),
            self::sendMessage(),
            self::analyzeMedia(),
            self::simulateDelay(),
            self::sendProductVideo(),
            self::sendProductSamples(),
        ];
    }

    private static function orderingTools(): array
    {
        $tenant = app()->bound(\App\Models\Tenant::class) ? app(\App\Models\Tenant::class) : null;
        $hasPayment = $tenant && !empty($tenant->payment_config);

        $tools = [
            self::inventorySearch(),
            self::calculateDeliveryFees(),
            self::createOrder(),
            self::updateOrder(),
            self::getOrderById(),
            self::routeToAgent(),
            self::sendMessage(),
            self::analyzeMedia(),
            self::simulateDelay(),
        ];

        if ($hasPayment) {
            $tools[] = self::createPayment();
            $tools[] = self::validatePayment();
        }

        return $tools;
    }

    // ---- Individual Tool Definitions ----

    public static function inventorySearch(): array
    {
        return self::tool(
            'inventory_search',
            'REQUIRED: Searches the store catalog for clothes. You MUST call this for any product query before answering the user. The search results now include detailed size recommendations involving chest width, DO NOT expose them directly to the user unless the user wants help with choosing the right size, height_cm, and weight_kg bounds. Use these bounds to accurately recommend sizes for the user. **Graceful Failure**: If the tool failed, DO NOT return the error message to the user, DO NOT apologize or try to collect more info about the product. Instead, return a user-friendly message like "I\'ll check on that for you and get back to you as soon as possible." **IN THE INSTRUCTED LANGUAGE**.',
            [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search query keywords',
                ],
            ],
            ['query']
        );
    }

    public static function calculateDeliveryFees(): array
    {
        return self::tool(
            'calculate_delivery_fees',
            'REQUIRED: Calculates the delivery fee based on the user\'s Egyptian governorate. You MUST call this as soon as you identify the governorate from the user\'s address to provide the final total price. **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Instead, return a user-friendly message like "I\'ll calculate the delivery fees for you and get back to you as soon as possible." **IN THE INSTRUCTED LANGUAGE AND ACCENT**',
            [
                'governorate' => [
                    'type' => 'string',
                    'description' => "The name of the Egyptian governorate (e.g., 'Cairo', 'Alexandria')",
                ],
            ],
            ['governorate']
        );
    }

    public static function createOrder(): array
    {
        return self::tool(
            'create_order',
            'Creates a pending order in the system. Always call this BEFORE create_payment. Requires all customer details. **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Instead, return a user-friendly message like "I\'ll create the order for you and get back to you as soon as possible." **IN THE INSTRUCTED LANGUAGE AND ACCENT**',
            [
                'userId' => ['type' => 'string', 'description' => "The user's unique platform ID (PSID)"],
                'customer_name' => ['type' => 'string', 'description' => "Customer's full name"],
                'customer_address' => ['type' => 'string', 'description' => "Customer's full delivery address"],
                'customer_numbers' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Array of customer phone numbers (at least one required)',
                ],
                'delivery_fees' => ['type' => 'number', 'description' => 'Delivery fees amount'],
                'governorate' => ['type' => 'string', 'description' => "The name of the Egyptian governorate"],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => ['type' => 'string', 'description' => 'Product ID'],
                            'product_name' => ['type' => 'string', 'description' => 'Product name'],
                            'quantity' => ['type' => 'integer', 'description' => 'Quantity (integer, min 1)'],
                            'price' => ['type' => 'number', 'description' => 'Product price'],
                            'size' => ['type' => 'string', 'description' => 'Product size (optional)'],
                            'color' => ['type' => 'string', 'description' => 'Product color (optional)'],
                        ],
                        'required' => ['product_id', 'product_name', 'quantity', 'price'],
                    ],
                    'description' => 'List of items in the cart',
                ],
                'total_amount' => ['type' => 'number', 'description' => 'The total amount including delivery fees'],
                'payment_method' => ['type' => 'string', 'description' => 'Payment method: InstaPay or wallet'],
            ],
            ['customer_name', 'customer_address', 'customer_numbers', 'delivery_fees', 'governorate', 'items', 'total_amount']
        );
    }

    public static function updateOrder(): array
    {
        return self::tool(
            'update_order',
            'Updates the order fields like address, name, numbers. **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Instead, return a user-friendly message like "I\'ll update the order for you and get back to you as soon as possible." **IN THE INSTRUCTED LANGUAGE AND ACCENT**',
            [
                'id' => ['type' => 'string', 'description' => 'The numeric ID of the order to update'],
                'customer_name' => ['type' => 'string', 'description' => 'The name of the customer'],
                'customer_address' => ['type' => 'string', 'description' => 'The address of the customer'],
                'customer_numbers' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'The phone numbers of the customer',
                ],
                'delivery_fees' => ['type' => 'number', 'description' => 'The delivery fees'],
                'governorate' => ['type' => 'string', 'description' => 'The governorate of the customer'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => ['type' => 'string', 'description' => 'Product ID'],
                            'product_name' => ['type' => 'string', 'description' => 'Product name'],
                            'quantity' => ['type' => 'integer', 'description' => 'Quantity'],
                            'price' => ['type' => 'number', 'description' => 'Product price'],
                            'size' => ['type' => 'string', 'description' => 'Product size'],
                            'color' => ['type' => 'string', 'description' => 'Product color'],
                        ],
                    ],
                    'description' => 'List of items in the cart',
                ],
                'total_amount' => ['type' => 'number', 'description' => 'The total amount including delivery fees'],
                'payment_method' => ['type' => 'string', 'description' => 'Payment method: InstaPay or wallet'],
            ],
            ['id']
        );
    }

    public static function getOrderById(): array
    {
        return self::tool(
            'get_order_by_id',
            'Retrieves an order by its ID. Use this when you need to get the order details. **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Instead, return a user-friendly message like "I\'ll check the order details for you and get back to you as soon as possible." **IN THE INSTRUCTED LANGUAGE AND ACCENT**',
            [
                'orderId' => ['type' => 'string', 'description' => 'The numeric ID of the order to retrieve'],
            ],
            ['orderId']
        );
    }

    public static function createPayment(): array
    {
        return self::tool(
            'create_payment',
            'Generates InstaPay/Wallet payment instructions for an existing order. Pass the amount to be paid NOW (e.g. shipping fees for COD). **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Instead, return a user-friendly message like "I\'ll generate the payment instructions for you and get back to you as soon as possible." **IN THE INSTRUCTED LANGUAGE AND ACCENT**',
            [
                'orderId' => ['type' => 'string', 'description' => 'The numeric ID of the order to generate payment for'],
                'amount' => ['type' => 'number', 'description' => 'The amount to charge the user now (EGP)'],
            ],
            ['orderId', 'amount']
        );
    }

    public static function validatePayment(): array
    {
        return self::tool(
            'validate_payment',
            'REQUIRED: Validates a payment. You MUST use the \'payment_intent_id\' that you received from calling \'create_payment\'. DO NOT GUESS OR HALLUCINATE THIS ID. **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Instead, return a user-friendly message like "I\'ll validate the payment for you and get back to you as soon as possible." **IN THE INSTRUCTED LANGUAGE AND ACCENT**',
            [
                'payment_intent_id' => ['type' => 'string', 'description' => 'The EXACT payment_intent_id returned by create_payment'],
                'sender_identifier' => ['type' => 'string', 'description' => "The screenshot URL to extract sender name (if InstaPay), OR the actual name in text (if InstaPay), OR the sender's wallet number from the user (if wallet)"],
            ],
            ['payment_intent_id', 'sender_identifier']
        );
    }

    public static function routeToAgent(): array
    {
        return self::tool(
            'route_to_agent',
            'Routes the conversation to a specialized agent. Use this when the user\'s intent changes (e.g., from general greeting to wanting to buy something). **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Instead, apologize in Arabic: "عذراً، حدثت مشكلة في تحويلك للقسم المختص."',
            [
                'targetAgent' => [
                    'type' => 'string',
                    'enum' => ['greeting', 'catalog', 'ordering', 'human'],
                    'description' => 'The type of agent to route to',
                ],
                'reason' => ['type' => 'string', 'description' => 'The reason for routing'],
            ],
            ['targetAgent', 'reason']
        );
    }

    public static function sendMessage(): array
    {
        return self::tool(
            'send_message',
            'Sends a text message to the user immediately. Use this for progress updates during multi-step processes like \'I am creating your order now...\'. DON\'T use markdown (no bold/italics). **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Don\'t take an action. This isn\'t a critical tool, so it\'s okay to fail silently.',
            [
                'message' => ['type' => 'string', 'description' => 'The text message to send to the user'],
            ],
            ['message']
        );
    }

    public static function sendProductVideo(): array
    {
        return self::tool(
            'send_product_video',
            'Sends a video to the user immediately. Use this to explicitly send a product video URL if you have one from the search results and want to show the user the product in motion. **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Don\'t take an action. This isn\'t a critical tool, so it\'s okay to fail silently.',
            [
                'videoUrl' => ['type' => 'string', 'description' => 'The URL of the product video to send'],
            ],
            ['videoUrl']
        );
    }

    public static function sendProductSamples(): array
    {
        return self::tool(
            'send_product_samples',
            'Sends an array of product images to the user immediately. Use this to explicitly send product image URLs if you have them from the search results and want to show the user what it looks like. You can send all the available samples for a specific color/size at once. **Graceful Failure**: If the tool failed, DO NOT return the error message to the user. Don\'t take an action. This isn\'t a critical tool, so it\'s okay to fail silently.',
            [
                'imageUrls' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'An array of product image URLs to send to the user',
                ],
            ],
            ['imageUrls']
        );
    }

    public static function analyzeMedia(): array
    {
        return self::tool(
            'analyze_media',
            'Use this tool to read, view, or listen to an attachment URL provided by the user. Do NOT use this for payment receipts (use validate_payment instead).',
            [
                'url' => ['type' => 'string', 'description' => 'The URL of the image or audio file.'],
                'media_type' => [
                    'type' => 'string',
                    'enum' => ['image', 'audio'],
                    'description' => 'Whether the URL is an image or audio.',
                ],
                'question' => [
                    'type' => 'string',
                    'description' => "What you specifically need to know from this media (e.g., 'What color is the shirt?', 'Transcribe this voice note').",
                ],
            ],
            ['url', 'media_type', 'question']
        );
    }

    public static function simulateDelay(): array
    {
        return self::tool(
            'simulate_delay',
            'Use this tool to pause your execution for a simulated duration. Useful for simulating time taken to listen to an audio record or typing a message. Pass the number of seconds you want to wait.',
            [
                'seconds' => ['type' => 'number', 'description' => 'The number of seconds to wait before continuing.'],
            ],
            ['seconds']
        );
    }
}

<?php

namespace App\Services\Agent;

use App\Services\ChatService;
use App\Services\Messaging\MessagingRouter;

use App\Services\OpenRouterService;
use App\Models\PendingTask;
use Illuminate\Support\Facades\Log;

class AgentExecutor
{
    private const EGYPTIAN_NAMES = [
        'أحمد', 'محمد', 'محمود', 'يوسف', 'علي', 'عمر', 'سارة', 'نورهان',
        'مريم', 'فاطمة', 'دينا', 'سلمى', 'مصطفى', 'كريم', 'طارق', 'عمرو',
        'نور', 'هبة', 'ياسين', 'إبراهيم', 'ياسمين',
    ];

    private const MAX_TOOL_ITERATIONS = 10;
    private const MAX_ROUTING_DEPTH = 2;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_BASE = 5; // seconds

    public function __construct(
        private ChatService $chatService,
        private OpenRouterService $openRouter,
        private ToolExecutor $toolExecutor,
        private MessagingRouter $messaging,
    ) {}

    /**
     * Process a user message through the agent system.
     *
     * @param string $userId
     * @param string $message
     * @param int $depth Routing depth
     * @param string|null $messageId Facebook message ID
     * @param bool $isEcho Whether this is an echo message from the page itself
     * @return string The final text response
     */
    public function processMessage(
        string $userId,
        string $message,
        int $depth = 0,
        ?string $messageId = null,
        bool $isEcho = false,
    ): string {
        // 1. Echo messages: save as AI message and return empty
        if ($isEcho) {
            $this->chatService->saveMessages($userId, [
                ['role' => 'assistant', 'content' => $message],
            ]);
            return '';
        }

        // 2. Human mode: just save, no AI processing
        $agentType = $this->chatService->getAgentType($userId);
        if ($agentType === 'human') {
            if (!empty($message)) {
                $this->chatService->saveMessages($userId, [
                    ['role' => 'user', 'content' => $message],
                ]);
            }
            return '';
        }

        // 3. Race condition guard: if there's a pending task within 5 minutes, save and skip
        if ($this->hasPendingTaskWithinMinutes($userId, 5)) {
            if (!empty($message)) {
                $this->chatService->saveMessages($userId, [
                    ['role' => 'user', 'content' => $message],
                ]);
            }
            return '';
        }

        // 4. Get agent definition and chat info
        $chatInfo = $this->chatService->getChatInfo($userId);
        $agentName = $chatInfo['agentName'];
        $userName = $chatInfo['userProfileName'];
        $chatHistory = $chatInfo['chatHistory'];

        // 5. First conversation: pick random name and get user profile
        if ($agentName === 'Store Agent' || empty($chatHistory)) {
            $agentName = self::EGYPTIAN_NAMES[array_rand(self::EGYPTIAN_NAMES)];
            $this->chatService->updateAgentName($userId, $agentName);

            if ($messageId) {
                $profileName = $this->messaging->getUserProfileName($userId, $messageId);
                if ($profileName) {
                    $userName = $profileName;
                    $this->chatService->updateUserName($userId, $userName);
                }
            }
        }

        // 6. Build system prompt
        $promptBuilder = app(\App\Services\Agent\PromptBuilder::class);
        $systemPrompt = $promptBuilder->getFullPrompt($agentType, $agentName, $userName);

        // 7. Build messages array for the LLM
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        $messages = array_merge($messages, $chatHistory);

        if (!empty($message)) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        // 8. Get tools for this agent
        $tools = ToolDefinitions::forAgent($agentType);

        // 9. Run the agent loop (tool calling loop)
        $newMessages = [];
        if (!empty($message)) {
            $newMessages[] = ['role' => 'user', 'content' => $message];
        }

        $routedTo = null;
        $finalText = '';

        try {
            $result = $this->runAgentLoop($messages, $tools, $userId, $newMessages, $routedTo);
            $finalText = $result['text'];
            $newMessages = $result['newMessages'];
            $routedTo = $result['routedTo'];
        } catch (\Exception $e) {
            Log::error("AgentExecutor error for user {$userId}: {$e->getMessage()}");

            if ($e->getMessage() === 'TENANT_UNCONFIGURED') {
                return 'عذراً، هذا المتجر لم يقم بإعداد المساعد الذكي الخاص به بعد.';
            }

            // Corrupted history recovery
            if (str_contains($e->getMessage(), 'tool_call_id') || str_contains($e->getMessage(), 'invalid')) {
                Log::warning("Clearing corrupted chat history for user {$userId}");
                $this->chatService->clearHistory($userId);
                return $this->processMessage($userId, $message, $depth, $messageId);
            }

            $finalText = 'حصل مشكلة بسيطة، ممكن تحاول تاني؟';
        }

        // 10. Save new messages
        $this->chatService->saveMessages($userId, $newMessages);

        // 11. Handle routing
        if ($routedTo === 'human') {
            // Save routing info but don't process further
            return '';
        }

        if ($routedTo && $routedTo !== 'human' && $depth < self::MAX_ROUTING_DEPTH) {
            $this->chatService->updateAgentType($userId, $routedTo);

            // Send any text from the current agent before routing
            if (!empty($finalText)) {
                $this->messaging->sendTextMessage($userId, $finalText);
                $finalText = '';
            }

            // Re-process with the new agent
            return $this->processMessage($userId, '', $depth + 1, $messageId);
        }

        return $finalText;
    }

    /**
     * Run the agent loop: call LLM, handle tool calls, repeat until text response or max iterations.
     */
    private function runAgentLoop(
        array &$messages,
        array $tools,
        string $userId,
        array &$newMessages,
        ?string &$routedTo,
    ): array {
        $finalText = '';

        for ($iteration = 0; $iteration < self::MAX_TOOL_ITERATIONS; $iteration++) {
            $response = $this->callLlmWithRetry($messages, $tools);
            $choice = $response['choices'][0] ?? null;

            if (!$choice) {
                break;
            }

            $assistantMessage = $choice['message'];
            $messages[] = $assistantMessage;
            $newMessages[] = $assistantMessage;

            // Check for tool calls
            $toolCalls = $assistantMessage['tool_calls'] ?? [];

            if (empty($toolCalls)) {
                // No tool calls — final text response
                $finalText = $assistantMessage['content'] ?? '';
                break;
            }

            // Process each tool call
            foreach ($toolCalls as $toolCall) {
                $toolName = $toolCall['function']['name'];
                $toolArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];
                $toolCallId = $toolCall['id'];

                Log::info("Tool call: {$toolName}", ['args' => $toolArgs, 'userId' => $userId]);

                // Execute the tool
                $toolResult = $this->toolExecutor->execute($toolName, $toolArgs, $userId);

                // Check for routing
                if ($toolName === 'route_to_agent') {
                    $decoded = json_decode($toolResult, true);
                    if (isset($decoded['targetAgent'])) {
                        $routedTo = $decoded['targetAgent'];
                    }
                }

                // Add tool result to messages
                $toolMessage = [
                    'role' => 'tool',
                    'content' => $toolResult,
                    'tool_call_id' => $toolCallId,
                ];

                $messages[] = $toolMessage;
                $newMessages[] = $toolMessage;
            }

            // If routed, stop the loop
            if ($routedTo) {
                $finalText = $assistantMessage['content'] ?? '';
                break;
            }
        }

        return [
            'text' => $finalText,
            'newMessages' => $newMessages,
            'routedTo' => $routedTo,
        ];
    }

    /**
     * Call the LLM with retry logic.
     */
    private function callLlmWithRetry(array $messages, array $tools): array
    {
        $attempt = 0;
        $delay = self::RETRY_DELAY_BASE;

        while ($attempt <= self::MAX_RETRIES) {
            try {
                return $this->openRouter->chat($messages, $tools);
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt > self::MAX_RETRIES) {
                    throw $e;
                }
                Log::warning("LLM call failed (attempt {$attempt}), retrying in {$delay}s: {$e->getMessage()}");
                sleep($delay);
                $delay *= 2;
            }
        }

        throw new \RuntimeException('LLM call failed after all retries.');
    }

    /**
     * Check if there's a pending task within N minutes.
     */
    private function hasPendingTaskWithinMinutes(string $userId, int $minutes): bool
    {
        return PendingTask::where('user_id', $userId)
            ->where('resume_at', '<=', now()->addMinutes($minutes))
            ->exists();
    }

    /**
     * Resume a scheduled task (for background process coordinator).
     */
    public function resumeTask(string $userId, string $taskJson): ?string
    {
        $data = json_decode($taskJson, true);

        if (!$data || !isset($data['tool_call_id'])) {
            // Process as a regular message
            return $this->processMessage($userId, $taskJson);
        }

        // Re-run the tool from chat history
        $chatInfo = $this->chatService->getChatInfo($userId);
        $chatHistory = $chatInfo['chatHistory'];

        // Find the tool call in history
        foreach (array_reverse($chatHistory) as $msg) {
            if (isset($msg['tool_calls'])) {
                foreach ($msg['tool_calls'] as $tc) {
                    if ($tc['id'] === $data['tool_call_id']) {
                        $toolName = $tc['function']['name'];
                        $toolArgs = json_decode($tc['function']['arguments'] ?? '{}', true) ?? [];

                        $result = $this->toolExecutor->execute($toolName, $toolArgs, $userId);

                        // Save the tool result
                        $this->chatService->saveMessages($userId, [
                            ['role' => 'tool', 'content' => $result, 'tool_call_id' => $data['tool_call_id']],
                        ]);

                        return $result;
                    }
                }
            }
        }

        return null;
    }
}

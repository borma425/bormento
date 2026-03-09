<?php

namespace App\Services;

use App\Models\ChatHistory;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Log;

class ChatService
{
    /**
     * Get the current agent type for a user.
     */
    public function getAgentType(string $userId): string
    {
        $session = ChatSession::find($userId);
        return $session ? $session->agent_type : 'greeting';
    }

    /**
     * Update the current agent type for a user.
     */
    public function updateAgentType(string $userId, string $agentType): void
    {
        ChatSession::updateOrCreate(
            ['user_id' => $userId],
            ['agent_type' => $agentType]
        );
    }

    /**
     * Get agent name for a user.
     */
    public function getAgentName(string $userId): string
    {
        $session = ChatSession::find($userId);
        return $session ? $session->agent_name : 'Store Agent';
    }

    /**
     * Update agent name for a user.
     */
    public function updateAgentName(string $userId, string $agentName): void
    {
        ChatSession::updateOrCreate(
            ['user_id' => $userId],
            ['agent_name' => $agentName]
        );
    }

    /**
     * Get user profile name.
     */
    public function getUserName(string $userId): string
    {
        $session = ChatSession::find($userId);
        return $session ? $session->user_name : 'User';
    }

    /**
     * Update user profile name.
     */
    public function updateUserName(string $userId, string $userName): void
    {
        ChatSession::updateOrCreate(
            ['user_id' => $userId],
            ['user_name' => $userName]
        );
    }

    /**
     * Get full chat info: agent name, user name, and chat history as OpenAI messages.
     */
    public function getChatInfo(string $userId, int $limit = 100): array
    {
        $session = ChatSession::find($userId);
        $agentName = $session ? $session->agent_name : 'Store Agent';
        $userName = $session ? $session->user_name : 'User';

        $rows = ChatHistory::where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        $messages = [];
        foreach ($rows as $row) {
            $message = $this->rowToMessage($row);
            if ($message) {
                $messages[] = $message;
            }
        }

        return [
            'agentName' => $agentName,
            'userProfileName' => $userName,
            'chatHistory' => $messages,
        ];
    }

    /**
     * Save messages to chat history.
     *
     * @param string $userId
     * @param array $messages Array of OpenAI-format messages
     */
    public function saveMessages(string $userId, array $messages): void
    {
        foreach ($messages as $msg) {
            $role = $msg['role'];
            $content = $msg['content'] ?? '';
            $toolCallId = $msg['tool_call_id'] ?? null;
            $metadata = null;

            // For assistant messages, save tool_calls in metadata
            if ($role === 'assistant' && !empty($msg['tool_calls'])) {
                $metadata = ['tool_calls' => $msg['tool_calls']];
            }

            // Map roles
            $dbRole = match ($role) {
                'user' => 'human',
                'assistant' => 'ai',
                'tool' => 'tool',
                default => $role,
            };

            ChatHistory::create([
                'user_id' => $userId,
                'role' => $dbRole,
                'content' => is_array($content) ? json_encode($content) : ($content ?? ''),
                'tool_call_id' => $toolCallId,
                'metadata' => $metadata,
            ]);
        }
    }

    /**
     * Clear chat history for a user.
     */
    public function clearHistory(string $userId): void
    {
        ChatHistory::where('user_id', $userId)->delete();
    }

    /**
     * Convert a DB row to an OpenAI-format message.
     */
    private function rowToMessage(ChatHistory $row): ?array
    {
        $role = $row->role;

        if ($role === 'human' || $role === 'user') {
            return ['role' => 'user', 'content' => $row->content];
        }

        if ($role === 'ai' || $role === 'assistant') {
            $message = ['role' => 'assistant', 'content' => $row->content];
            if ($row->metadata && isset($row->metadata['tool_calls'])) {
                $message['tool_calls'] = $row->metadata['tool_calls'];
            }
            return $message;
        }

        if ($role === 'tool') {
            return [
                'role' => 'tool',
                'content' => $row->content,
                'tool_call_id' => $row->tool_call_id ?? '',
            ];
        }

        return null;
    }
}

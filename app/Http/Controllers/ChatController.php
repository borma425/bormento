<?php

namespace App\Http\Controllers;

use App\Services\Agent\AgentExecutor;
use App\Services\Messaging\MessagingRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private AgentExecutor $executor,
        private MessagingRouter $messaging,
    ) {}

    /**
     * POST /chat - Handle a message from the HTML test interface.
     */
    public function handleChat(Request $request): JsonResponse
    {
        // Increase time limit for complex Agent Loops using tools (OpenRouter embeddings, etc.)
        set_time_limit(120);

        $request->validate([
            'session_id' => 'required|string',
            'message' => 'required|string',
        ]);

        // We MUST prepend 'html-user-' so MessagingRouter caches it instead of sending to Facebook
        $sessionId = 'html-user-' . $request->input('session_id');
        $message = $request->input('message');

        // Process the message directly
        $response = $this->executor->processMessage($sessionId, $message);

        if (!empty($response)) {
            $this->messaging->sendTextMessage($sessionId, $response);
        }

        return response()->json(['success' => true]);
    }

    /**
     * GET /chat/poll - Poll for new messages (HTML test interface).
     */
    public function poll(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return response()->json(['messages' => []]);
        }

        $prefixedSessionId = 'html-user-' . $sessionId;
        $messages = $this->messaging->popHtmlMessages($prefixedSessionId);

        return response()->json(['messages' => $messages]);
    }
}

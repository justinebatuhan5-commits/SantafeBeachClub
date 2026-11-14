<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeminiChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    protected GeminiChatbotService $chatbotService;

    public function __construct(GeminiChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    /**
     * Handle guest chat messages.
     */
    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'  => 'required|string|max:1000',
            'history'  => 'nullable|array|max:10',
            'language' => 'nullable|string|max:30',
        ]);

        $message = trim($validated['message']);
        $history = $validated['history'] ?? [];
        $language = $validated['language'] ?? null;

        $response = $this->chatbotService->sendMessage($message, $history, $language);

        return response()->json($response);
    }
}

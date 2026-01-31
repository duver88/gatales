<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenAIService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private OpenAIService $openAIService,
        private TokenService $tokenService
    ) {}

    /**
     * Get messages for a specific conversation
     */
    public function conversationMessages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        $assistant = $conversation->assistant ?? $user->getAssistant();

        // Only select necessary columns for better performance
        $messages = $conversation->messages()
            ->select(['id', 'role', 'content', 'created_at'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'tokens_balance' => $user->tokens_balance,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ],
            'assistant' => $assistant ? [
                'id' => $assistant->id,
                'name' => $assistant->assistant_display_name,
                'welcome_message' => $assistant->welcome_message,
                'avatar_url' => $assistant->avatar_url,
            ] : null,
        ]);
    }

    /**
     * Send a message to a specific conversation
     */
    public function conversationSend(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        // Check if user has free plan (no access to chat)
        if ($user->hasFreePlan()) {
            return response()->json([
                'success' => false,
                'error' => 'free_plan',
                'message' => '¡Tienes el plan gratuito! Para acceder al asistente de IA, actualiza tu suscripción.',
                'current_plan' => $user->getCurrentPlanName(),
                'upgrade_url' => config('services.hotmart.upgrade_url'),
            ], 403);
        }

        // Check if user has enough tokens (minimum 100 to start a conversation)
        if (!$this->tokenService->hasEnoughTokens($user, 100)) {
            $subscription = $user->activeSubscription;

            return response()->json([
                'success' => false,
                'error' => 'tokens_exhausted',
                'message' => '¡Tus tokens se han agotado! Puedes esperar a que se renueven el próximo mes o actualizar tu plan.',
                'tokens_balance' => $user->tokens_balance,
                'renewal_date' => $subscription?->ends_at?->toIso8601String(),
                'upgrade_url' => config('services.hotmart.upgrade_url'),
            ], 402);
        }

        try {
            // Check if this is the first message - generate title if so
            $isFirstMessage = $conversation->messages()->count() === 0;

            // Save user message first
            $userMessage = Message::create([
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $validated['message'],
                'tokens_input' => 0,
                'tokens_output' => 0,
            ]);

            // Send to OpenAI and get response
            $response = $this->openAIService->sendMessage($user, $validated['message'], $conversation);

            // Save assistant response
            $assistantMessage = Message::create([
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $response['content'],
                'tokens_input' => $response['tokens_input'],
                'tokens_output' => $response['tokens_output'],
                'openai_message_id' => $response['message_id'],
            ]);

            // Update conversation stats
            $conversation->updateTokenStats($response['tokens_input'], $response['tokens_output']);

            // Generate title if first message
            if ($isFirstMessage) {
                $conversation->generateTitle();
            }

            // Deduct tokens
            $this->tokenService->deductTokens(
                $user,
                $response['tokens_input'],
                $response['tokens_output']
            );

            // Refresh user and conversation
            $user->refresh();
            $conversation->refresh();

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $assistantMessage->id,
                    'role' => 'assistant',
                    'content' => $response['content'],
                    'created_at' => $assistantMessage->created_at->toIso8601String(),
                ],
                'tokens_used' => $response['tokens_input'] + $response['tokens_output'],
                'tokens_balance' => $user->tokens_balance,
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ],
            ]);
        } catch (\Exception $e) {
            // If there was an error, delete the user message to avoid orphan messages
            if (isset($userMessage)) {
                $userMessage->delete();
            }

            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al procesar tu mensaje. Por favor intenta de nuevo.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Send a message with streaming response (SSE)
     */
    public function conversationSendStream(Request $request, Conversation $conversation): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $user = $request->user();

        // Verify user is authenticated with valid ID
        if (!$user) {
            return $this->streamError('No autenticado', 401);
        }

        // Explicitly get and validate the user ID
        $userId = $user->id;
        if (!$userId || !is_numeric($userId) || $userId <= 0) {
            \Log::error('User ID invalid in streaming endpoint', [
                'user_id' => $userId,
                'user_exists' => (bool)$user,
                'conversation_id' => $conversation->id,
            ]);
            return $this->streamError('ID de usuario invalido', 401);
        }

        // Cast to integer to ensure proper type
        $userId = (int) $userId;

        // Verify ownership
        if ($conversation->user_id !== $userId) {
            return $this->streamError('No autorizado', 403);
        }

        // Check if user has free plan
        if ($user->hasFreePlan()) {
            return $this->streamError('free_plan', 403);
        }

        // Check if user has enough tokens
        if (!$this->tokenService->hasEnoughTokens($user, 100)) {
            return $this->streamError('tokens_exhausted', 402);
        }

        $message = $validated['message'];

        return new StreamedResponse(function () use ($user, $userId, $message, $conversation) {
            // Deshabilitar TODOS los buffers de forma agresiva
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', false);
            @ini_set('implicit_flush', true);

            // Limpiar todos los niveles de output buffering
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            // Forzar flush implícito
            ob_implicit_flush(true);

            // Enviar padding inicial para forzar que los buffers se vacíen
            echo ": " . str_repeat(' ', 4096) . "\n\n";
            flush();

            try {
                // Double-check userId is valid inside closure
                if (!$userId || $userId <= 0) {
                    $this->sendSSE('error', ['message' => 'Error de autenticacion']);
                    return;
                }

                // Check if first message
                $isFirstMessage = $conversation->messages()->count() === 0;

                // Save user message first
                $userMessage = Message::create([
                    'user_id' => $userId,
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $message,
                    'tokens_input' => 0,
                    'tokens_output' => 0,
                ]);

                // Send start event with user message ID
                $this->sendSSE('start', [
                    'user_message_id' => $userMessage->id,
                ]);

                // Stream the response
                $fullContent = '';
                $tokensInput = 0;
                $tokensOutput = 0;
                $messageId = null;

                foreach ($this->openAIService->sendMessageStreamed($user, $message, $conversation) as $chunk) {
                    if ($chunk['type'] === 'content') {
                        $fullContent .= $chunk['content'];
                        $this->sendSSE('content', [
                            'text' => $chunk['content'],
                        ]);
                    } elseif ($chunk['type'] === 'done') {
                        $tokensInput = $chunk['tokens_input'];
                        $tokensOutput = $chunk['tokens_output'];
                        $messageId = $chunk['message_id'];
                        if (isset($chunk['full_content'])) {
                            $fullContent = $chunk['full_content'];
                        }
                    }
                }

                // Save assistant message
                $assistantMessage = Message::create([
                    'user_id' => $userId,
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $fullContent,
                    'tokens_input' => $tokensInput,
                    'tokens_output' => $tokensOutput,
                    'openai_message_id' => $messageId,
                ]);

                // Update conversation stats
                $conversation->updateTokenStats($tokensInput, $tokensOutput);

                // Generate title if first message
                if ($isFirstMessage) {
                    $conversation->generateTitle();
                }

                // Deduct tokens
                $this->tokenService->deductTokens($user, $tokensInput, $tokensOutput);

                // Refresh
                $user->refresh();
                $conversation->refresh();

                // Send done event
                $this->sendSSE('done', [
                    'message_id' => $assistantMessage->id,
                    'tokens_used' => $tokensInput + $tokensOutput,
                    'tokens_balance' => $user->tokens_balance,
                    'conversation' => [
                        'id' => $conversation->id,
                        'title' => $conversation->title,
                    ],
                ]);

            } catch (\Exception $e) {
                // Delete user message on error
                if (isset($userMessage)) {
                    $userMessage->delete();
                }

                $this->sendSSE('error', [
                    'message' => 'Error al procesar el mensaje',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Send SSE event
     */
    private function sendSSE(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        // Agregar padding para forzar flush de buffers de PHP-FPM/Nginx
        echo ": " . str_repeat(' ', 256) . "\n\n";
        flush();
    }

    /**
     * Return a streaming error response
     * Note: Always use 200 status for SSE - errors are communicated through events
     * Using non-200 status causes fetch to fail before processing SSE events
     */
    private function streamError(string $error, int $status = 200): StreamedResponse
    {
        return new StreamedResponse(function () use ($error) {
            $this->sendSSE('error', ['message' => $error]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Clear messages in a specific conversation
     */
    public function conversationClear(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        try {
            // Clear OpenAI thread
            $this->openAIService->clearConversationThread($conversation);

            // Delete all messages from conversation
            $conversation->messages()->delete();

            // Reset conversation stats
            $conversation->update([
                'total_tokens_input' => 0,
                'total_tokens_output' => 0,
                'last_message_at' => null,
                'title' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Historial de chat limpiado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar el historial. Por favor intenta de nuevo.',
            ], 500);
        }
    }

    // ============================================
    // LEGACY ENDPOINTS (for backward compatibility)
    // ============================================

    /**
     * Get user's chat message history (legacy - returns last conversation or creates one)
     */
    public function messages(Request $request): JsonResponse
    {
        $user = $request->user();
        $assistant = $user->getAssistant();

        // Get the most recent conversation or create a new one
        $conversation = Conversation::forUser($user->id)
            ->notArchived()
            ->orderByDesc('last_message_at')
            ->first();

        if (!$conversation) {
            // Create a new conversation
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'assistant_id' => $assistant?->id,
                'type' => 'user_chat',
            ]);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'tokens_balance' => $user->tokens_balance,
            'conversation_id' => $conversation->id,
            'assistant' => $assistant ? [
                'id' => $assistant->id,
                'name' => $assistant->assistant_display_name,
                'welcome_message' => $assistant->welcome_message,
                'avatar_url' => $assistant->avatar_url,
            ] : null,
        ]);
    }

    /**
     * Send a message to the assistant (legacy - uses last conversation)
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $user = $request->user();
        $assistant = $user->getAssistant();

        // Get or create conversation
        $conversation = Conversation::forUser($user->id)
            ->notArchived()
            ->orderByDesc('last_message_at')
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'assistant_id' => $assistant?->id,
                'type' => 'user_chat',
            ]);
        }

        // Use the conversation-based send
        $request->merge(['message' => $validated['message']]);
        return $this->conversationSend($request, $conversation);
    }

    /**
     * Clear chat history and create new thread (legacy)
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Get the most recent conversation
            $conversation = Conversation::forUser($user->id)
                ->notArchived()
                ->orderByDesc('last_message_at')
                ->first();

            if ($conversation) {
                $this->openAIService->clearConversationThread($conversation);
                $conversation->messages()->delete();
                $conversation->update([
                    'total_tokens_input' => 0,
                    'total_tokens_output' => 0,
                    'last_message_at' => null,
                    'title' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Historial de chat limpiado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar el historial. Por favor intenta de nuevo.',
            ], 500);
        }
    }

    /**
     * Get available assistants for the user to choose from
     * Cached for 5 minutes to improve performance
     */
    public function assistants(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cache assistants list for 5 minutes
        $assistants = Cache::remember('active_assistants', 300, function () {
            return Assistant::active()
                ->orderBy('name')
                ->get()
                ->map(function ($assistant) {
                    return [
                        'id' => $assistant->id,
                        'name' => $assistant->assistant_display_name,
                        'description' => $assistant->description,
                        'is_default' => $assistant->is_default,
                        'avatar_url' => $assistant->avatar_url,
                    ];
                });
        });

        return response()->json([
            'success' => true,
            'assistants' => $assistants,
            'current_assistant_id' => $user->assistant_id,
        ]);
    }

    /**
     * Change the user's current assistant
     */
    public function changeAssistant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
        ]);

        $user = $request->user();

        // Verify assistant is active
        $assistant = Assistant::active()->find($validated['assistant_id']);
        if (!$assistant) {
            return response()->json([
                'success' => false,
                'message' => 'El asistente seleccionado no esta disponible',
            ], 400);
        }

        // Update user's assistant
        $user->update(['assistant_id' => $assistant->id]);

        // Create a new conversation with the new assistant instead of clearing all history
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'assistant_id' => $assistant->id,
            'type' => 'user_chat',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Asistente cambiado correctamente',
            'conversation_id' => $conversation->id,
            'assistant' => [
                'id' => $assistant->id,
                'name' => $assistant->assistant_display_name,
                'welcome_message' => $assistant->welcome_message,
                'avatar_url' => $assistant->avatar_url,
            ],
        ]);
    }
}

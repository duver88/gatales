<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ReleaseResourcesForStreaming;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\DeepSeekService;
use App\Services\OpenAIService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private OpenAIService $openAIService,
        private DeepSeekService $deepSeekService,
        private TokenService $tokenService
    ) {}

    /**
     * Get the appropriate AI service based on assistant's provider
     */
    private function getServiceForAssistant(?Assistant $assistant): OpenAIService|DeepSeekService
    {
        if ($assistant && $assistant->isDeepSeek()) {
            return $this->deepSeekService;
        }
        return $this->openAIService;
    }

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

            // Get the appropriate service based on assistant's provider
            $assistant = $conversation->assistant ?? $user->getAssistant();
            $provider = $assistant && $assistant->isDeepSeek() ? 'deepseek' : 'openai';
            $aiService = $this->getServiceForAssistant($assistant);

            // Save user message first
            $userMessage = Message::create([
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'provider' => $provider,
                'role' => 'user',
                'content' => $validated['message'],
                'tokens_input' => 0,
                'tokens_output' => 0,
            ]);

            // Send to AI and get response
            $response = $aiService->sendMessage($user, $validated['message'], $conversation);

            // Save assistant response
            $assistantMessage = Message::create([
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'provider' => $provider,
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

            // Deduct tokens with provider
            $this->tokenService->deductTokens(
                $user,
                $response['tokens_input'],
                $response['tokens_output'],
                $provider
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
            Log::error('User ID invalid in streaming endpoint', [
                'user_id' => $userId,
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
        $assistant = $conversation->assistant ?? $user->getAssistant();
        $isDeepSeek = $assistant && $assistant->isDeepSeek();
        $provider = $isDeepSeek ? 'deepseek' : 'openai';
        $usesResponsesApi = !$isDeepSeek && $assistant && $assistant->usesResponsesApi();

        // Cache all data needed for the streaming closure BEFORE releasing resources
        $userTokensBalance = $user->tokens_balance;
        $conversationId = $conversation->id;
        $assistantSettings = $assistant ? $assistant->toSettingsArray() : null;
        $assistantId = $assistant?->id;

        // Check if first message (do this BEFORE releasing DB connection)
        $isFirstMessage = $conversation->messages()->doesntExist();

        // Save user message BEFORE releasing resources
        $userMessage = Message::create([
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'provider' => $provider,
            'role' => 'user',
            'content' => $message,
            'tokens_input' => 0,
            'tokens_output' => 0,
        ]);
        $userMessageId = $userMessage->id;

        // CRITICAL: Release ALL resources before streaming
        // This prevents blocking other users/admin while AI generates response
        ReleaseResourcesForStreaming::releaseAll();

        return new StreamedResponse(function () use ($userId, $message, $conversationId, $assistantSettings, $assistantId, $usesResponsesApi, $isDeepSeek, $provider, $userTokensBalance, $isFirstMessage, $userMessageId) {
            // Deshabilitar timeout de PHP para streaming largo (GPT-5 + Knowledge Base puede tardar varios minutos)
            set_time_limit(0);

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

                // User message was already saved before releasing resources
                // Send start event with user message ID
                $this->sendSSE('start', [
                    'user_message_id' => $userMessageId,
                ]);

                $fullContent = '';
                $tokensInput = 0;
                $tokensOutput = 0;
                $messageId = null;

                // RECONNECT to DB to fetch fresh models for AI service
                DB::reconnect();

                // Fetch fresh models
                $user = \App\Models\User::find($userId);
                $conversation = Conversation::find($conversationId);
                $assistant = $assistantId ? Assistant::find($assistantId) : null;

                if (!$user || !$conversation) {
                    $this->sendSSE('error', ['message' => 'Datos no encontrados']);
                    return;
                }

                if ($usesResponsesApi) {
                    // Use direct cURL streaming for Responses API (real-time) - OpenAI only
                    $this->streamWithResponsesApi(
                        $user,
                        $message,
                        $assistant,
                        $conversation,
                        $fullContent,
                        $tokensInput,
                        $tokensOutput,
                        $messageId
                    );
                } else {
                    // Use Chat Completions API streaming
                    // Choose service based on provider
                    $aiService = $isDeepSeek ? $this->deepSeekService : $this->openAIService;

                    // The generator will use the DB briefly to build context, then HTTP calls don't need it
                    foreach ($aiService->sendMessageStreamed($user, $message, $conversation) as $chunk) {
                        if ($chunk['type'] === 'content') {
                            $fullContent .= $chunk['content'];
                            // Send chunk immediately with aggressive flush
                            echo "event: content\n";
                            echo "data: " . json_encode(['text' => $chunk['content']]) . "\n\n";
                            // Padding to force buffer flush in Nginx/FastCGI
                            echo ": " . str_repeat(' ', 512) . "\n\n";
                            if (ob_get_level() > 0) {
                                @ob_flush();
                            }
                            flush();
                        } elseif ($chunk['type'] === 'reasoning') {
                            // DeepSeek reasoner sends reasoning content
                            echo "event: reasoning\n";
                            echo "data: " . json_encode(['text' => $chunk['content']]) . "\n\n";
                            echo ": " . str_repeat(' ', 512) . "\n\n";
                            if (ob_get_level() > 0) {
                                @ob_flush();
                            }
                            flush();
                        } elseif ($chunk['type'] === 'done') {
                            $tokensInput = $chunk['tokens_input'];
                            $tokensOutput = $chunk['tokens_output'];
                            $messageId = $chunk['message_id'];
                            if (isset($chunk['full_content'])) {
                                $fullContent = $chunk['full_content'];
                            }
                        }
                    }
                }

                // Save assistant message (DB should still be connected or will auto-reconnect)
                $assistantMessage = Message::create([
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                    'provider' => $provider,
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
                $totalTokensToDeduct = $tokensInput + $tokensOutput;
                Log::info('Deducting tokens', [
                    'user_id' => $userId,
                    'tokens_input' => $tokensInput,
                    'tokens_output' => $tokensOutput,
                    'total' => $totalTokensToDeduct,
                    'balance_before' => $user->tokens_balance,
                    'provider' => $provider,
                ]);
                $this->tokenService->deductTokens($user, $tokensInput, $tokensOutput, $provider);

                // Refresh user to get new balance
                $user->refresh();
                $newBalance = $user->tokens_balance;

                // Refresh conversation for title update
                $conversation->refresh();

                Log::info('Tokens deducted', [
                    'user_id' => $userId,
                    'balance_after' => $newBalance,
                ]);

                // Send done event
                $this->sendSSE('done', [
                    'message_id' => $assistantMessage->id,
                    'tokens_input' => $tokensInput,
                    'tokens_output' => $tokensOutput,
                    'tokens_used' => $tokensInput + $tokensOutput,
                    'tokens_balance' => $newBalance,
                    'provider' => $provider,
                    'model' => $assistantSettings['model'] ?? 'gpt-4o-mini',
                    'conversation' => [
                        'id' => $conversation->id,
                        'title' => $conversation->title,
                        'total_tokens' => $conversation->total_tokens,
                        'total_tokens_input' => $conversation->total_tokens_input,
                        'total_tokens_output' => $conversation->total_tokens_output,
                    ],
                ]);

            } catch (\Exception $e) {
                Log::error('Streaming error', [
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                    'error' => $e->getMessage(),
                ]);

                // Try to delete user message on error
                try {
                    DB::reconnect();
                    Message::where('id', $userMessageId)->delete();
                } catch (\Throwable $deleteError) {
                    // Ignore delete errors
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
     * Stream response using Responses API with direct cURL (real-time streaming)
     */
    private function streamWithResponsesApi(
        $user,
        string $message,
        Assistant $assistant,
        Conversation $conversation,
        string &$fullContent,
        int &$tokensInput,
        int &$tokensOutput,
        ?string &$messageId
    ): void {
        $apiKey = config('openai.api_key');
        $contextLimit = (int) ($assistant->context_messages ?? 10);

        // Build input from conversation history
        $input = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit($contextLimit)
            ->get()
            ->reverse()
            ->map(fn($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->values()
            ->toArray();

        $input[] = ['role' => 'user', 'content' => $message];

        // Build request params
        $params = [
            'model' => $assistant->model,
            'instructions' => $assistant->system_prompt,
            'input' => $input,
            'max_output_tokens' => (int) $assistant->max_tokens,
            'stream' => true,
        ];

        // Add reasoning effort for GPT-5 models (ONLY when NOT using file_search)
        // The reasoning parameter may conflict with file_search tools
        // Options: none, minimal, low, medium, high, xhigh
        // minimal = fastest response, high = best quality
        $hasFileSearch = $assistant->use_knowledge_base && $assistant->openai_vector_store_id;
        if (str_starts_with($assistant->model, 'gpt-5') && !$hasFileSearch) {
            $reasoningEffort = $assistant->reasoning_effort ?? 'minimal';
            $params['reasoning'] = [
                'effort' => $reasoningEffort,
            ];
        }

        // Add file_search if knowledge base enabled
        if ($assistant->use_knowledge_base && $assistant->openai_vector_store_id) {
            $params['tools'] = [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => [$assistant->openai_vector_store_id],
                ],
            ];
        }

        // Log request for debugging
        Log::info('OpenAI Responses API streaming request', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'model' => $assistant->model,
            'reasoning_effort' => $assistant->reasoning_effort ?? 'minimal',
            'has_knowledge_base' => $assistant->use_knowledge_base,
            'vector_store_id' => $assistant->openai_vector_store_id,
            'input_messages_count' => count($input),
            'max_output_tokens' => $params['max_output_tokens'],
            'has_tools' => isset($params['tools']),
            'has_reasoning' => isset($params['reasoning']),
        ]);

        // Use object to store state
        $state = new \stdClass();
        $state->fullContent = '';
        $state->tokensInput = 0;
        $state->tokensOutput = 0;
        $state->buffer = '';
        $state->errorMessage = null;
        $state->rawResponse = '';
        $state->eventTypes = []; // Track all event types received
        $state->debugEvents = []; // Store sample of each event type for debugging
        $state->receivedDeltas = false; // Track if we received streaming deltas

        $ch = curl_init('https://api.openai.com/v1/responses');

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 900, // 15 minutes for GPT-5 with file_search
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use ($state) {
                $state->rawResponse .= $data;
                $state->buffer .= $data;

                while (($pos = strpos($state->buffer, "\n")) !== false) {
                    $line = substr($state->buffer, 0, $pos);
                    $state->buffer = substr($state->buffer, $pos + 1);
                    $line = trim($line);

                    if (empty($line) || $line === 'data: [DONE]') continue;

                    if (str_starts_with($line, 'data: ')) {
                        $jsonStr = substr($line, 6);
                        $event = json_decode($jsonStr, true);

                        if (!$event) continue;

                        // Track event types for debugging
                        if (isset($event['type'])) {
                            $eventType = $event['type'];
                            $state->eventTypes[] = $eventType;

                            // Store first occurrence of each event type for debugging
                            if (!isset($state->debugEvents[$eventType])) {
                                $state->debugEvents[$eventType] = json_encode($event, JSON_UNESCAPED_UNICODE);
                            }
                        }

                        // Check for API errors
                        if (isset($event['error'])) {
                            $state->errorMessage = $event['error']['message'] ?? 'Unknown API error';
                            Log::error('OpenAI API error in stream', ['error' => $event['error']]);
                            continue;
                        }

                        // Handle content - multiple event types for Responses API
                        $delta = null;
                        $eventType = $event['type'] ?? null;

                        // 1. Delta streaming events (preferred for real-time)
                        if ($eventType === 'response.output_text.delta' && isset($event['delta'])) {
                            $delta = $event['delta'];
                            $state->receivedDeltas = true;
                        }
                        // 1b. Also check for text delta in different formats
                        elseif ($eventType === 'response.text.delta' && isset($event['delta'])) {
                            $delta = $event['delta'];
                            $state->receivedDeltas = true;
                        }
                        // 2. Content part delta
                        elseif ($eventType === 'response.content_part.delta' && isset($event['delta']['text'])) {
                            $delta = $event['delta']['text'];
                            $state->receivedDeltas = true;
                        }
                        // 2b. Content part added - might contain initial text (only if no deltas yet)
                        elseif ($eventType === 'response.content_part.added' && isset($event['part']['text']) && !$state->receivedDeltas) {
                            $delta = $event['part']['text'];
                        }
                        // 3. Fallback: direct delta string
                        elseif (isset($event['delta']) && is_string($event['delta'])) {
                            $delta = $event['delta'];
                            $state->receivedDeltas = true;
                        }
                        // 4. Fallback handlers for when NO streaming deltas were received
                        // These extract full text from completion events
                        elseif (!$state->receivedDeltas) {
                            // Output item done - extract full text from completed item
                            if ($eventType === 'response.output_item.done' && isset($event['item'])) {
                                $item = $event['item'];
                                $itemType = $item['type'] ?? 'unknown';

                                if ($itemType !== 'file_search_call' && $itemType !== 'function_call') {
                                    if (isset($item['content']) && is_array($item['content'])) {
                                        foreach ($item['content'] as $content) {
                                            if (isset($content['text'])) {
                                                $delta = $content['text'];
                                                break;
                                            }
                                            if (isset($content['type']) && $content['type'] === 'output_text' && isset($content['text'])) {
                                                $delta = $content['text'];
                                                break;
                                            }
                                        }
                                    }
                                    if ($delta === null && isset($item['text'])) {
                                        $delta = $item['text'];
                                    }
                                    if ($delta === null && isset($item['output']) && is_array($item['output'])) {
                                        foreach ($item['output'] as $output) {
                                            if (isset($output['text'])) {
                                                $delta = $output['text'];
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            // Response output_text.done
                            elseif ($eventType === 'response.output_text.done' && isset($event['text'])) {
                                $delta = $event['text'];
                            }
                            // Content part done
                            elseif ($eventType === 'response.content_part.done' && isset($event['part']['text'])) {
                                $delta = $event['part']['text'];
                            }
                        }

                        // Log incomplete response details
                        if (($eventType === 'response.incomplete' || $eventType === 'response.completed') && isset($event['response'])) {
                            if (isset($event['response']['incomplete_details'])) {
                                Log::warning('OpenAI response incomplete', [
                                    'details' => $event['response']['incomplete_details'],
                                ]);
                            }
                            // Extract text if we didn't receive any deltas (fallback)
                            if (!$state->receivedDeltas && $delta === null && isset($event['response']['output']) && is_array($event['response']['output'])) {
                                foreach ($event['response']['output'] as $output) {
                                    if (isset($output['type']) && $output['type'] === 'message' && isset($output['content'])) {
                                        foreach ($output['content'] as $content) {
                                            if (isset($content['text'])) {
                                                $delta = $content['text'];
                                                break 2;
                                            }
                                        }
                                    }
                                    if (isset($output['text'])) {
                                        $delta = $output['text'];
                                        break;
                                    }
                                }
                            }
                        }

                        if ($delta !== null && $delta !== '') {
                            $state->fullContent .= $delta;
                            echo "event: content\n";
                            echo "data: " . json_encode(['text' => $delta]) . "\n\n";
                            echo ": " . str_repeat(' ', 256) . "\n\n";
                            flush();
                        }

                        // Handle completion (both complete and incomplete responses)
                        if ($eventType === 'response.completed' || $eventType === 'response.done' || $eventType === 'response.incomplete') {
                            if (isset($event['response']['usage'])) {
                                $state->tokensInput = $event['response']['usage']['input_tokens'] ?? 0;
                                $state->tokensOutput = $event['response']['usage']['output_tokens'] ?? 0;
                            }
                        }
                    }
                }
                return strlen($data);
            },
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Log result summary
        $uniqueEventTypes = array_unique($state->eventTypes);
        if ($httpCode !== 200 || !$success || $state->errorMessage || empty($state->fullContent)) {
            Log::warning('OpenAI Responses API streaming issue', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'http_code' => $httpCode,
                'curl_error' => $curlError ?: null,
                'api_error' => $state->errorMessage,
                'content_length' => strlen($state->fullContent),
                'event_types' => $uniqueEventTypes,
                'received_deltas' => $state->receivedDeltas,
                'model' => $assistant->model,
                'tokens' => $state->tokensInput + $state->tokensOutput,
            ]);
        } else {
            Log::info('OpenAI Responses API streaming completed', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'content_length' => strlen($state->fullContent),
                'event_types_count' => count($uniqueEventTypes),
                'received_deltas' => $state->receivedDeltas,
                'tokens_input' => $state->tokensInput,
                'tokens_output' => $state->tokensOutput,
            ]);
        }

        // Copy state back
        $fullContent = $state->fullContent;
        $tokensInput = $state->tokensInput;
        $tokensOutput = $state->tokensOutput;
        $messageId = 'resp_' . time();

        // Throw on errors
        if ($state->errorMessage) {
            throw new \Exception("OpenAI API error: " . $state->errorMessage);
        }

        if (!$success) {
            throw new \Exception("cURL error ($curlErrno): $curlError");
        }

        if ($httpCode !== 200) {
            throw new \Exception("API error: HTTP $httpCode");
        }

        // Throw if no content was received (empty response)
        if (empty($state->fullContent)) {
            Log::error('OpenAI returned empty content', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'event_types' => $uniqueEventTypes,
                'raw_response_length' => strlen($state->rawResponse),
            ]);
            throw new \Exception("OpenAI returned empty response. Event types received: " . implode(', ', $uniqueEventTypes));
        }
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

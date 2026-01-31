<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenAIAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminConversationController extends Controller
{
    public function __construct(
        private OpenAIAssistantService $assistantService
    ) {}

    /**
     * List admin's test conversations for a specific assistant
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assistant_id' => 'nullable|exists:assistants,id',
        ]);

        $admin = $request->user('admin');

        $query = Conversation::forAdmin($admin->id)
            ->with('assistant:id,name,assistant_display_name')
            ->withCount('messages')
            ->orderByDesc('last_message_at');

        if (!empty($validated['assistant_id'])) {
            $query->where('assistant_id', $validated['assistant_id']);
        }

        $conversations = $query->get()
            ->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title ?: 'Prueba sin titulo',
                    'assistant_id' => $conversation->assistant_id,
                    'assistant_name' => $conversation->assistant?->assistant_display_name,
                    'total_tokens' => $conversation->total_tokens,
                    'message_count' => $conversation->messages_count,
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'created_at' => $conversation->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Create a new test conversation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
        ]);

        $admin = $request->user('admin');
        $assistant = Assistant::find($validated['assistant_id']);

        $conversation = Conversation::create([
            'admin_id' => $admin->id,
            'assistant_id' => $assistant->id,
            'type' => 'admin_test',
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => null,
                'assistant_id' => $assistant->id,
                'assistant_name' => $assistant->assistant_display_name,
                'total_tokens' => 0,
                'message_count' => 0,
                'last_message_at' => null,
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get a test conversation with messages
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $admin = $request->user('admin');

        // Verify ownership
        if ($conversation->admin_id !== $admin->id || $conversation->type !== 'admin_test') {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
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
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'assistant_id' => $conversation->assistant_id,
                'assistant_name' => $conversation->assistant?->assistant_display_name,
                'total_tokens' => $conversation->total_tokens,
                'total_tokens_input' => $conversation->total_tokens_input,
                'total_tokens_output' => $conversation->total_tokens_output,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message to a test conversation
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $admin = $request->user('admin');

        // Verify ownership
        if ($conversation->admin_id !== $admin->id || $conversation->type !== 'admin_test') {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        $assistant = $conversation->assistant;
        if (!$assistant) {
            return response()->json([
                'success' => false,
                'message' => 'Asistente no encontrado',
            ], 404);
        }

        try {
            // Check if first message to generate title
            $isFirstMessage = $conversation->messages()->count() === 0;

            // Save user message
            Message::create([
                'user_id' => null, // Admin test, no user
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $validated['message'],
                'tokens_input' => 0,
                'tokens_output' => 0,
            ]);

            // Get assistant response
            $settings = $assistant->toSettingsArray();
            $model = $settings['model'];

            // Build context from conversation
            $context = $conversation->messages()
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($msg) {
                    return [
                        'role' => $msg->role,
                        'content' => $msg->content,
                    ];
                })
                ->toArray();

            // Check if using Responses API (knowledge base enabled)
            if ($assistant->usesResponsesApi()) {
                // Use the OpenAIAssistantService for Responses API
                $response = $this->assistantService->sendMessageForTest(
                    $validated['message'],
                    $assistant,
                    $context
                );

                $content = $response['response'];
                $tokensInput = $response['usage']['prompt_tokens'] ?? 0;
                $tokensOutput = $response['usage']['completion_tokens'] ?? 0;
                $usedKnowledgeBase = true;
            } else {
                // Use Chat Completions API
                $messages = [
                    ['role' => 'system', 'content' => $settings['system_prompt']],
                ];

                foreach ($context as $msg) {
                    $messages[] = $msg;
                }

                $messages[] = ['role' => 'user', 'content' => $validated['message']];

                $params = [
                    'model' => $model,
                    'messages' => $messages,
                ];

                $newModels = ['gpt-5', 'o1', 'o1-mini', 'o1-preview'];
                $usesNewFormat = false;
                foreach ($newModels as $newModel) {
                    if (str_starts_with($model, $newModel)) {
                        $usesNewFormat = true;
                        break;
                    }
                }

                if ($usesNewFormat) {
                    $params['max_completion_tokens'] = (int) $settings['max_tokens'];
                } else {
                    $params['max_tokens'] = (int) $settings['max_tokens'];
                    $params['temperature'] = (float) $settings['temperature'];
                }

                $response = OpenAI::chat()->create($params);
                $content = $response->choices[0]->message->content;
                $tokensInput = $response->usage->promptTokens ?? 0;
                $tokensOutput = $response->usage->completionTokens ?? 0;
                $usedKnowledgeBase = false;
            }

            // Save assistant response
            $assistantMessage = Message::create([
                'user_id' => null,
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $content,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
            ]);

            // Update conversation stats
            $conversation->updateTokenStats($tokensInput, $tokensOutput);

            // Generate title if first message
            if ($isFirstMessage) {
                $title = Str::limit($validated['message'], 50, '...');
                $conversation->update(['title' => $title]);
            }

            $conversation->refresh();

            return response()->json([
                'success' => true,
                'response' => $content,
                'message' => [
                    'id' => $assistantMessage->id,
                    'role' => 'assistant',
                    'content' => $content,
                    'created_at' => $assistantMessage->created_at->toIso8601String(),
                ],
                'usage' => [
                    'prompt_tokens' => $tokensInput,
                    'completion_tokens' => $tokensOutput,
                    'total_tokens' => $tokensInput + $tokensOutput,
                ],
                'used_knowledge_base' => $usedKnowledgeBase,
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'total_tokens' => $conversation->total_tokens,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a message with streaming response (SSE)
     */
    public function sendMessageStream(Request $request, Conversation $conversation): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $admin = $request->user('admin');

        // Verify ownership
        if ($conversation->admin_id !== $admin->id || $conversation->type !== 'admin_test') {
            return $this->streamError('No autorizado');
        }

        $assistant = $conversation->assistant;
        if (!$assistant) {
            return $this->streamError('Asistente no encontrado');
        }

        $message = $validated['message'];
        $usesResponsesApi = $assistant->usesResponsesApi();

        return new StreamedResponse(function () use ($admin, $message, $conversation, $assistant, $usesResponsesApi) {
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

            // Enviar padding inicial para forzar que los buffers de PHP-FPM/Nginx se vacíen
            // Los buffers suelen ser de 4KB/8KB, así que enviamos suficiente padding
            echo ": " . str_repeat(' ', 4096) . "\n\n";
            flush();

            try {
                $isFirstMessage = $conversation->messages()->count() === 0;

                // Save user message
                $userMessage = Message::create([
                    'user_id' => null,
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $message,
                    'tokens_input' => 0,
                    'tokens_output' => 0,
                ]);

                $this->sendSSE('start', ['user_message_id' => $userMessage->id]);

                // Build context
                $context = $conversation->messages()
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(fn($msg) => ['role' => $msg->role, 'content' => $msg->content])
                    ->toArray();

                $fullContent = '';
                $tokensInput = 0;
                $tokensOutput = 0;

                if ($usesResponsesApi) {
                    // Use Responses API with direct cURL streaming for real-time output
                    $apiKey = config('openai.api_key');

                    // Build input from context
                    $input = $context;
                    $input[] = ['role' => 'user', 'content' => $message];

                    $params = [
                        'model' => $assistant->model,
                        'instructions' => $assistant->system_prompt,
                        'input' => $input,
                        'max_output_tokens' => (int) $assistant->max_tokens,
                        'stream' => true,
                    ];

                    // Add file_search if knowledge base enabled
                    if ($assistant->use_knowledge_base && $assistant->openai_vector_store_id) {
                        $params['tools'] = [
                            [
                                'type' => 'file_search',
                                'vector_store_ids' => [$assistant->openai_vector_store_id],
                            ],
                        ];
                    }

                    $ch = curl_init('https://api.openai.com/v1/responses');
                    $buffer = '';

                    // Use object to store state (closures with references can be problematic)
                    $state = new \stdClass();
                    $state->fullContent = '';
                    $state->tokensInput = 0;
                    $state->tokensOutput = 0;
                    $state->buffer = '';

                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode($params),
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $apiKey,
                            'Content-Type: application/json',
                            'Accept: text/event-stream',
                        ],
                        CURLOPT_RETURNTRANSFER => false,
                        CURLOPT_TIMEOUT => 300,
                        CURLOPT_CONNECTTIMEOUT => 30,
                        CURLOPT_WRITEFUNCTION => function($ch, $data) use ($state) {
                            $state->buffer .= $data;

                            // Log raw data for debugging
                            \Log::debug('OpenAI SSE raw chunk', ['data' => $data]);

                            while (($pos = strpos($state->buffer, "\n")) !== false) {
                                $line = substr($state->buffer, 0, $pos);
                                $state->buffer = substr($state->buffer, $pos + 1);
                                $line = trim($line);

                                if (empty($line) || $line === 'data: [DONE]') continue;

                                if (str_starts_with($line, 'data: ')) {
                                    $jsonStr = substr($line, 6);
                                    $event = json_decode($jsonStr, true);

                                    // Log parsed event
                                    \Log::debug('OpenAI SSE event', ['event' => $event]);

                                    if (!$event) continue;

                                    // Handle content delta - check multiple possible formats
                                    $delta = null;
                                    if (isset($event['type']) && $event['type'] === 'response.output_text.delta') {
                                        $delta = $event['delta'] ?? null;
                                    }
                                    // Also check for text content in output items
                                    if (isset($event['type']) && $event['type'] === 'content_block_delta' && isset($event['delta']['text'])) {
                                        $delta = $event['delta']['text'];
                                    }
                                    // Check for direct delta content
                                    if (isset($event['delta']) && is_string($event['delta'])) {
                                        $delta = $event['delta'];
                                    }

                                    if ($delta !== null) {
                                        $state->fullContent .= $delta;
                                        \Log::info('Sending SSE chunk to client', ['delta' => $delta, 'total_len' => strlen($state->fullContent)]);
                                        echo "event: content\n";
                                        echo "data: " . json_encode(['text' => $delta]) . "\n\n";
                                        // Agregar padding para forzar flush de buffers
                                        echo ": " . str_repeat(' ', 256) . "\n\n";
                                        flush();
                                    }

                                    // Handle completion
                                    if (isset($event['type']) && $event['type'] === 'response.completed' && isset($event['response'])) {
                                        $state->tokensInput = $event['response']['usage']['input_tokens'] ?? 0;
                                        $state->tokensOutput = $event['response']['usage']['output_tokens'] ?? 0;
                                    }
                                }
                            }
                            return strlen($data);
                        },
                    ]);

                    curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    // Copy state back to local variables
                    $fullContent = $state->fullContent;
                    $tokensInput = $state->tokensInput;
                    $tokensOutput = $state->tokensOutput;

                    \Log::info('OpenAI streaming complete', [
                        'httpCode' => $httpCode,
                        'contentLength' => strlen($fullContent),
                        'tokensInput' => $tokensInput,
                        'tokensOutput' => $tokensOutput,
                    ]);

                    if ($httpCode !== 200) {
                        throw new \Exception("API error: HTTP $httpCode - $curlError");
                    }
                } else {
                    // Use Chat Completions API streaming
                    $settings = $assistant->toSettingsArray();
                    $model = $settings['model'];

                    $messages = [['role' => 'system', 'content' => $settings['system_prompt']]];
                    foreach ($context as $msg) {
                        $messages[] = $msg;
                    }
                    $messages[] = ['role' => 'user', 'content' => $message];

                    // Build params
                    $params = ['model' => $model, 'messages' => $messages, 'stream' => true, 'stream_options' => ['include_usage' => true]];

                    $newModels = ['gpt-5', 'o1', 'o1-mini', 'o1-preview'];
                    $usesNewFormat = false;
                    foreach ($newModels as $newModel) {
                        if (str_starts_with($model, $newModel)) {
                            $usesNewFormat = true;
                            break;
                        }
                    }

                    if ($usesNewFormat) {
                        $params['max_completion_tokens'] = (int) $settings['max_tokens'];
                    } else {
                        $params['max_tokens'] = (int) $settings['max_tokens'];
                        $params['temperature'] = (float) $settings['temperature'];
                    }

                    // Stream response
                    $stream = OpenAI::chat()->createStreamed($params);

                    foreach ($stream as $response) {
                        // Skip empty chunks
                        if (isset($response->choices[0]->delta->content)) {
                            $chunk = $response->choices[0]->delta->content;
                            if ($chunk !== null && $chunk !== '') {
                                $fullContent .= $chunk;
                                $this->sendSSE('content', ['text' => $chunk]);
                            }
                        }

                        if (isset($response->usage)) {
                            $tokensInput = $response->usage->promptTokens ?? 0;
                            $tokensOutput = $response->usage->completionTokens ?? 0;
                        }
                    }
                }

                // Save assistant message
                $assistantMessage = Message::create([
                    'user_id' => null,
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $fullContent,
                    'tokens_input' => $tokensInput,
                    'tokens_output' => $tokensOutput,
                ]);

                // Update stats
                $conversation->updateTokenStats($tokensInput, $tokensOutput);

                if ($isFirstMessage) {
                    $title = Str::limit($message, 50, '...');
                    $conversation->update(['title' => $title]);
                }

                $conversation->refresh();

                $this->sendSSE('done', [
                    'message_id' => $assistantMessage->id,
                    'tokens_used' => $tokensInput + $tokensOutput,
                    'conversation' => [
                        'id' => $conversation->id,
                        'title' => $conversation->title,
                        'total_tokens' => $conversation->total_tokens,
                    ],
                ]);

            } catch (\Exception $e) {
                if (isset($userMessage)) {
                    $userMessage->delete();
                }
                $this->sendSSE('error', ['message' => 'Error: ' . $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Content-Encoding' => 'none',
            'Transfer-Encoding' => 'chunked',
        ]);
    }

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
     */
    private function streamError(string $message): StreamedResponse
    {
        return new StreamedResponse(function () use ($message) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            $this->sendSSE('error', ['message' => $message]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Delete a test conversation
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $admin = $request->user('admin');

        // Verify ownership
        if ($conversation->admin_id !== $admin->id || $conversation->type !== 'admin_test') {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        // Delete OpenAI thread if exists
        if ($conversation->openai_thread_id) {
            try {
                OpenAI::threads()->delete($conversation->openai_thread_id);
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversacion de prueba eliminada',
        ]);
    }

    /**
     * Clear all test conversations for an assistant
     */
    public function clearAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
        ]);

        $admin = $request->user('admin');

        $conversations = Conversation::forAdmin($admin->id)
            ->where('assistant_id', $validated['assistant_id'])
            ->get();

        foreach ($conversations as $conversation) {
            // Delete OpenAI thread if exists
            if ($conversation->openai_thread_id) {
                try {
                    OpenAI::threads()->delete($conversation->openai_thread_id);
                } catch (\Exception $e) {
                    // Ignore errors
                }
            }

            $conversation->forceDelete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Historial de pruebas eliminado',
            'deleted_count' => $conversations->count(),
        ]);
    }
}

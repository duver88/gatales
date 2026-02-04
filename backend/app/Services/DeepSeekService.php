<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekService
{
    protected string $baseUrl = 'https://api.deepseek.com';
    protected ?string $apiKey = null;

    public function __construct()
    {
        $this->apiKey = config('services.deepseek.api_key');
    }

    /**
     * Send a message using DeepSeek Chat API
     *
     * @return array{content: string, tokens_input: int, tokens_output: int, message_id: string}
     */
    public function sendMessage(User $user, string $message, ?Conversation $conversation = null): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('DeepSeek API key not configured. Set DEEPSEEK_API_KEY in .env');
        }

        $assistant = $conversation?->assistant ?? $user->getAssistant();
        $settings = $assistant ? $assistant->toSettingsArray() : AiSetting::getAllValues();

        $messages = $this->buildMessagesArray(
            $user,
            $settings['system_prompt'] ?? 'Eres un asistente util.',
            $message,
            (int) ($settings['context_messages'] ?? 10),
            $settings['filter_unsafe_content'] ?? 'true',
            $conversation
        );

        $requestParams = $this->buildRequestParams($settings, $messages, $assistant);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($this->baseUrl . '/chat/completions', $requestParams);

        if (!$response->successful()) {
            Log::error('DeepSeek API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('DeepSeek API error: ' . $response->body());
        }

        $data = $response->json();

        $content = $data['choices'][0]['message']['content'] ?? '';
        $tokensInput = $data['usage']['prompt_tokens'] ?? 0;
        $tokensOutput = $data['usage']['completion_tokens'] ?? 0;

        return [
            'content' => $content,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'message_id' => $data['id'] ?? 'deepseek_' . time(),
        ];
    }

    /**
     * Send a message with streaming response
     * Uses a queue-based approach to yield chunks as they arrive from cURL
     *
     * @return \Generator yields chunks of text
     */
    public function sendMessageStreamed(User $user, string $message, ?Conversation $conversation = null): \Generator
    {
        if (empty($this->apiKey)) {
            throw new \Exception('DeepSeek API key not configured. Set DEEPSEEK_API_KEY in .env');
        }

        $assistant = $conversation?->assistant ?? $user->getAssistant();
        $settings = $assistant ? $assistant->toSettingsArray() : AiSetting::getAllValues();

        $messages = $this->buildMessagesArray(
            $user,
            $settings['system_prompt'] ?? 'Eres un asistente util.',
            $message,
            (int) ($settings['context_messages'] ?? 10),
            $settings['filter_unsafe_content'] ?? 'true',
            $conversation
        );

        $requestParams = $this->buildRequestParams($settings, $messages, $assistant);
        $requestParams['stream'] = true;
        // Request usage stats in final chunk
        $requestParams['stream_options'] = ['include_usage' => true];

        $fullContent = '';
        $tokensInput = 0;
        $tokensOutput = 0;
        $messageId = null;
        $buffer = '';
        $chunks = []; // Queue for chunks to yield

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestParams),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_RETURNTRANSFER => false, // Don't buffer - process in real-time
            CURLOPT_TIMEOUT => 300,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buffer, &$chunks, &$fullContent, &$messageId, &$tokensInput, &$tokensOutput) {
                $buffer .= $data;

                // Process complete lines as they arrive
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $line = trim($line);

                    if (empty($line)) continue;

                    if (str_starts_with($line, 'data: ')) {
                        $jsonStr = substr($line, 6);

                        if ($jsonStr === '[DONE]') {
                            continue;
                        }

                        $event = json_decode($jsonStr, true);
                        if (!$event) continue;

                        if (!$messageId && isset($event['id'])) {
                            $messageId = $event['id'];
                        }

                        // Content chunks
                        if (isset($event['choices'][0]['delta']['content'])) {
                            $chunk = $event['choices'][0]['delta']['content'];
                            if ($chunk !== null && $chunk !== '') {
                                $fullContent .= $chunk;
                                $chunks[] = ['type' => 'content', 'content' => $chunk];
                            }
                        }

                        // DeepSeek reasoner reasoning content
                        if (isset($event['choices'][0]['delta']['reasoning_content'])) {
                            $reasoning = $event['choices'][0]['delta']['reasoning_content'];
                            if ($reasoning !== null && $reasoning !== '') {
                                $chunks[] = ['type' => 'reasoning', 'content' => $reasoning];
                            }
                        }

                        // Usage stats (usually in final chunk)
                        if (isset($event['usage'])) {
                            $tokensInput = $event['usage']['prompt_tokens'] ?? 0;
                            $tokensOutput = $event['usage']['completion_tokens'] ?? 0;
                        }
                    }
                }

                return strlen($data);
            },
        ]);

        // Execute cURL in a non-blocking way by using curl_multi
        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);

        do {
            $status = curl_multi_exec($mh, $active);

            // Yield any accumulated chunks
            while (!empty($chunks)) {
                yield array_shift($chunks);
            }

            // Small sleep to prevent CPU spinning
            if ($active && $status === CURLM_OK) {
                curl_multi_select($mh, 0.01);
            }
        } while ($active && $status === CURLM_OK);

        // Yield any remaining chunks
        while (!empty($chunks)) {
            yield array_shift($chunks);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        curl_multi_close($mh);

        if ($httpCode !== 200 && $httpCode !== 0) {
            Log::error('DeepSeek streaming error', [
                'http_code' => $httpCode,
                'curl_error' => $curlError,
            ]);
            throw new \Exception('DeepSeek API error (HTTP ' . $httpCode . '): ' . $curlError);
        }

        yield [
            'type' => 'done',
            'full_content' => $fullContent,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'message_id' => $messageId ?? 'deepseek_' . time(),
        ];
    }

    /**
     * Build request parameters for the API
     */
    private function buildRequestParams(array $settings, array $messages, ?Assistant $assistant): array
    {
        $model = $assistant?->model ?? $settings['model'] ?? 'deepseek-chat';
        $maxTokens = (int) ($settings['max_tokens'] ?? 2000);
        $isReasoner = str_contains($model, 'reasoner');

        $params = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];

        // DeepSeek reasoner doesn't support temperature/top_p customization
        if (!$isReasoner) {
            // Temperature
            if (!empty($settings['temperature'])) {
                $params['temperature'] = (float) $settings['temperature'];
            }

            // Top P
            if (!empty($settings['top_p']) && $settings['top_p'] !== '1') {
                $params['top_p'] = (float) $settings['top_p'];
            }
        }

        // Frequency penalty (supported by both models)
        if (!empty($settings['frequency_penalty']) && $settings['frequency_penalty'] !== '0') {
            $params['frequency_penalty'] = (float) $settings['frequency_penalty'];
        }

        // Presence penalty (supported by both models)
        if (!empty($settings['presence_penalty']) && $settings['presence_penalty'] !== '0') {
            $params['presence_penalty'] = (float) $settings['presence_penalty'];
        }

        // Response format (JSON mode) - NOT supported by reasoner
        if (!$isReasoner && !empty($settings['response_format']) && $settings['response_format'] === 'json_object') {
            $params['response_format'] = ['type' => 'json_object'];
        }

        // Stop sequences
        if (!empty($settings['stop_sequences'])) {
            $stopSequences = array_map('trim', explode(',', $settings['stop_sequences']));
            $stopSequences = array_filter($stopSequences);
            if (!empty($stopSequences)) {
                $params['stop'] = array_slice($stopSequences, 0, 4);
            }
        }

        Log::debug('DeepSeek request params', [
            'model' => $model,
            'is_reasoner' => $isReasoner,
            'max_tokens' => $maxTokens,
            'has_temperature' => isset($params['temperature']),
            'has_top_p' => isset($params['top_p']),
        ]);

        return $params;
    }

    /**
     * Build the messages array for the API call
     */
    private function buildMessagesArray(
        User $user,
        string $systemPrompt,
        string $newMessage,
        int $contextLimit,
        string $filterUnsafe = 'true',
        ?Conversation $conversation = null
    ): array {
        $messages = [];

        // Add safety instructions if enabled
        $finalPrompt = $systemPrompt;
        if ($filterUnsafe === 'true') {
            $finalPrompt .= "\n\nIMPORTANTE: Evita generar contenido inapropiado, ofensivo o danino.";
        }

        // Add system message
        $messages[] = [
            'role' => 'system',
            'content' => $finalPrompt,
        ];

        // Get previous messages for context
        if ($conversation) {
            $previousMessages = $conversation->messages()
                ->orderBy('created_at', 'desc')
                ->limit($contextLimit)
                ->get()
                ->reverse()
                ->values();
        } else {
            $previousMessages = \App\Models\Message::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($contextLimit)
                ->get()
                ->reverse()
                ->values();
        }

        // Add previous messages
        foreach ($previousMessages as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        // Add the new user message
        $messages[] = [
            'role' => 'user',
            'content' => $newMessage,
        ];

        return $messages;
    }

    /**
     * Get the welcome message
     */
    public function getWelcomeMessage(?User $user = null): string
    {
        if ($user) {
            $assistant = $user->getAssistant();
            if ($assistant) {
                return $assistant->welcome_message;
            }
        }

        return AiSetting::getValue('welcome_message', '¡Hola! ¿En que puedo ayudarte hoy?');
    }

    /**
     * Check if streaming is supported
     */
    public function supportsStreaming(string $model): bool
    {
        return true; // DeepSeek supports streaming for all models
    }
}

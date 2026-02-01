<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('deepseek.api_key', '');
        $this->baseUrl = config('deepseek.base_url', 'https://api.deepseek.com');
        $this->timeout = config('deepseek.request_timeout', 120);
    }

    /**
     * Check if DeepSeek is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Check if a model is a DeepSeek model
     */
    public static function isDeepSeekModel(string $model): bool
    {
        return str_starts_with($model, 'deepseek-');
    }

    /**
     * Send a message using DeepSeek Chat API
     *
     * @return array{content: string, tokens_input: int, tokens_output: int, message_id: string, reasoning_content: string|null}
     */
    public function sendMessage(User $user, string $message, ?Conversation $conversation = null): array
    {
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

        $requestParams = $this->buildRequestParams($settings, $messages, $user);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/chat/completions', $requestParams);

        if (!$response->successful()) {
            Log::error('DeepSeek API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('DeepSeek API error: ' . $response->body());
        }

        $data = $response->json();

        $content = $data['choices'][0]['message']['content'] ?? '';
        $reasoningContent = $data['choices'][0]['message']['reasoning_content'] ?? null;
        $tokensInput = $data['usage']['prompt_tokens'] ?? 0;
        $tokensOutput = $data['usage']['completion_tokens'] ?? 0;

        return [
            'content' => $content,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'message_id' => $data['id'] ?? 'deepseek_' . time(),
            'reasoning_content' => $reasoningContent,
        ];
    }

    /**
     * Build request parameters for DeepSeek API
     */
    private function buildRequestParams(array $settings, array $messages, User $user): array
    {
        $model = $settings['model'] ?? 'deepseek-chat';
        $maxTokens = (int) ($settings['max_tokens'] ?? 2000);

        $params = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];

        // DeepSeek supports temperature (0-2)
        if (!empty($settings['temperature'])) {
            $params['temperature'] = min(2.0, max(0.0, (float) $settings['temperature']));
        }

        // Top P (nucleus sampling)
        if (!empty($settings['top_p']) && $settings['top_p'] !== '1') {
            $params['top_p'] = (float) $settings['top_p'];
        }

        // Frequency penalty (-2 to 2)
        if (!empty($settings['frequency_penalty']) && $settings['frequency_penalty'] !== '0') {
            $params['frequency_penalty'] = (float) $settings['frequency_penalty'];
        }

        // Presence penalty (-2 to 2)
        if (!empty($settings['presence_penalty']) && $settings['presence_penalty'] !== '0') {
            $params['presence_penalty'] = (float) $settings['presence_penalty'];
        }

        // Response format
        if (!empty($settings['response_format']) && $settings['response_format'] === 'json_object') {
            $params['response_format'] = ['type' => 'json_object'];
        }

        // Stop sequences
        if (!empty($settings['stop_sequences'])) {
            $stopSequences = array_map('trim', explode(',', $settings['stop_sequences']));
            $stopSequences = array_filter($stopSequences);
            if (!empty($stopSequences)) {
                $params['stop'] = array_slice($stopSequences, 0, 16); // DeepSeek supports up to 16
            }
        }

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

        $finalPrompt = $systemPrompt;
        if ($filterUnsafe === 'true') {
            $finalPrompt .= "\n\nIMPORTANTE: Evita generar contenido inapropiado, ofensivo o danino.";
        }

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
            $previousMessages = Message::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($contextLimit)
                ->get()
                ->reverse()
                ->values();
        }

        foreach ($previousMessages as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $newMessage,
        ];

        return $messages;
    }

    /**
     * Check if a model supports streaming
     */
    public function supportsStreaming(string $model): bool
    {
        // All DeepSeek models support streaming
        return true;
    }

    /**
     * Send a message with streaming response
     *
     * @return \Generator yields chunks of text
     */
    public function sendMessageStreamed(User $user, string $message, ?Conversation $conversation = null): \Generator
    {
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

        $requestParams = $this->buildRequestParams($settings, $messages, $user);
        $requestParams['stream'] = true;

        $fullContent = '';
        $reasoningContent = '';
        $tokensInput = 0;
        $tokensOutput = 0;
        $messageId = null;

        // Use cURL for streaming
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/chat/completions',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestParams),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullContent, &$reasoningContent, &$tokensInput, &$tokensOutput, &$messageId) {
                // This function will be replaced by the generator below
                return strlen($data);
            },
        ]);

        // Create a temporary file for the response
        $tempStream = fopen('php://temp', 'w+');

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($tempStream) {
            fwrite($tempStream, $data);
            return strlen($data);
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            rewind($tempStream);
            $errorBody = stream_get_contents($tempStream);
            fclose($tempStream);
            Log::error('DeepSeek streaming error', ['status' => $httpCode, 'body' => $errorBody]);
            throw new \Exception('DeepSeek API streaming error: ' . $errorBody);
        }

        // Parse SSE response
        rewind($tempStream);
        while (($line = fgets($tempStream)) !== false) {
            $line = trim($line);
            if (empty($line) || $line === 'data: [DONE]') {
                continue;
            }

            if (str_starts_with($line, 'data: ')) {
                $jsonData = substr($line, 6);
                $data = json_decode($jsonData, true);

                if ($data === null) {
                    continue;
                }

                if ($messageId === null && isset($data['id'])) {
                    $messageId = $data['id'];
                }

                // Handle regular content
                if (isset($data['choices'][0]['delta']['content'])) {
                    $chunk = $data['choices'][0]['delta']['content'];
                    if ($chunk !== null && $chunk !== '') {
                        $fullContent .= $chunk;
                        yield [
                            'type' => 'content',
                            'content' => $chunk,
                        ];
                    }
                }

                // Handle reasoning content (for deepseek-reasoner)
                if (isset($data['choices'][0]['delta']['reasoning_content'])) {
                    $reasoningChunk = $data['choices'][0]['delta']['reasoning_content'];
                    if ($reasoningChunk !== null && $reasoningChunk !== '') {
                        $reasoningContent .= $reasoningChunk;
                        yield [
                            'type' => 'reasoning',
                            'content' => $reasoningChunk,
                        ];
                    }
                }

                // Get usage from final chunk
                if (isset($data['usage'])) {
                    $tokensInput = $data['usage']['prompt_tokens'] ?? 0;
                    $tokensOutput = $data['usage']['completion_tokens'] ?? 0;
                }
            }
        }
        fclose($tempStream);

        yield [
            'type' => 'done',
            'full_content' => $fullContent,
            'reasoning_content' => $reasoningContent ?: null,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'message_id' => $messageId ?? 'deepseek_' . time(),
        ];
    }

    /**
     * Streaming with callback (for SSE responses)
     */
    public function streamWithCallback(User $user, string $message, callable $callback, ?Conversation $conversation = null): array
    {
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

        $requestParams = $this->buildRequestParams($settings, $messages, $user);
        $requestParams['stream'] = true;

        $fullContent = '';
        $reasoningContent = '';
        $tokensInput = 0;
        $tokensOutput = 0;
        $messageId = null;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/chat/completions',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestParams),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($callback, &$fullContent, &$reasoningContent, &$tokensInput, &$tokensOutput, &$messageId) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || $line === 'data: [DONE]') {
                        continue;
                    }

                    if (str_starts_with($line, 'data: ')) {
                        $jsonData = substr($line, 6);
                        $parsed = json_decode($jsonData, true);

                        if ($parsed === null) {
                            continue;
                        }

                        if ($messageId === null && isset($parsed['id'])) {
                            $messageId = $parsed['id'];
                        }

                        if (isset($parsed['choices'][0]['delta']['content'])) {
                            $chunk = $parsed['choices'][0]['delta']['content'];
                            if ($chunk !== null && $chunk !== '') {
                                $fullContent .= $chunk;
                                $callback(['type' => 'content', 'content' => $chunk]);
                            }
                        }

                        if (isset($parsed['choices'][0]['delta']['reasoning_content'])) {
                            $reasoningChunk = $parsed['choices'][0]['delta']['reasoning_content'];
                            if ($reasoningChunk !== null && $reasoningChunk !== '') {
                                $reasoningContent .= $reasoningChunk;
                                $callback(['type' => 'reasoning', 'content' => $reasoningChunk]);
                            }
                        }

                        if (isset($parsed['usage'])) {
                            $tokensInput = $parsed['usage']['prompt_tokens'] ?? 0;
                            $tokensOutput = $parsed['usage']['completion_tokens'] ?? 0;
                        }
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('DeepSeek API error: HTTP ' . $httpCode . ' - ' . $error);
        }

        return [
            'content' => $fullContent,
            'reasoning_content' => $reasoningContent ?: null,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'message_id' => $messageId ?? 'deepseek_' . time(),
        ];
    }
}

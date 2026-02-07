<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\OpenAIAssistantService;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIService
{
    protected ?OpenAIAssistantService $assistantService = null;

    /**
     * Get the OpenAI Assistant Service (lazy loading)
     */
    protected function getAssistantService(): OpenAIAssistantService
    {
        if ($this->assistantService === null) {
            $this->assistantService = new OpenAIAssistantService();
        }
        return $this->assistantService;
    }

    /**
     * Send a message using Chat Completions API or Responses API (if knowledge base enabled)
     *
     * @return array{content: string, tokens_input: int, tokens_output: int, message_id: string}
     */
    public function sendMessage(User $user, string $message, ?Conversation $conversation = null): array
    {
        // Get settings from user's assistant or fallback to global ai_settings
        $assistant = $conversation?->assistant ?? $user->getAssistant();

        // If assistant uses knowledge base (Responses API), delegate to that service
        if ($assistant && $assistant->usesResponsesApi()) {
            return $this->getAssistantService()->sendMessage($user, $message, $assistant, $conversation);
        }

        $settings = $assistant ? $assistant->toSettingsArray() : AiSetting::getAllValues();

        // Build messages array with context
        $messages = $this->buildMessagesArray(
            $user,
            $settings['system_prompt'] ?? 'Eres un asistente util.',
            $message,
            (int) ($settings['context_messages'] ?? 10),
            $settings['filter_unsafe_content'] ?? 'true',
            $conversation
        );

        // Build request parameters
        $requestParams = $this->buildRequestParams($settings, $messages, $user);

        // Call OpenAI Chat Completions API
        $response = OpenAI::chat()->create($requestParams);

        // Extract response
        $content = $response->choices[0]->message->content;
        $tokensInput = $response->usage->promptTokens ?? 0;
        $tokensOutput = $response->usage->completionTokens ?? 0;

        return [
            'content' => $content,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'message_id' => $response->id,
        ];
    }

    /**
     * Build request parameters for the API
     */
    private function buildRequestParams(array $settings, array $messages, User $user): array
    {
        $model = $settings['model'] ?? 'gpt-4o-mini';
        $maxTokens = (int) ($settings['max_tokens'] ?? 2000);

        $params = [
            'model' => $model,
            'messages' => $messages,
        ];

        // Newer models (GPT-5, o1 series) use max_completion_tokens instead of max_tokens
        // They also don't support temperature or sampling parameters (top_p, frequency_penalty, presence_penalty)
        $isNewModel = $this->usesMaxCompletionTokens($model);

        if ($isNewModel) {
            $params['max_completion_tokens'] = $maxTokens;
            // GPT-5 and o1 models don't support custom temperature or sampling parameters
            // Don't send these parameters to avoid API errors
        } else {
            $params['temperature'] = (float) ($settings['temperature'] ?? 0.7);
            $params['max_tokens'] = $maxTokens;

            // Sampling parameters only for older models (GPT-4, GPT-3.5, etc.)
            // Top P (nucleus sampling)
            if (!empty($settings['top_p']) && $settings['top_p'] !== '1') {
                $params['top_p'] = (float) $settings['top_p'];
            }

            // Frequency penalty
            if (!empty($settings['frequency_penalty']) && $settings['frequency_penalty'] !== '0') {
                $params['frequency_penalty'] = (float) $settings['frequency_penalty'];
            }

            // Presence penalty
            if (!empty($settings['presence_penalty']) && $settings['presence_penalty'] !== '0') {
                $params['presence_penalty'] = (float) $settings['presence_penalty'];
            }
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
                $params['stop'] = array_slice($stopSequences, 0, 4); // Max 4 stop sequences
            }
        }

        // Seed for reproducibility
        if (!empty($settings['seed']) && is_numeric($settings['seed'])) {
            $params['seed'] = (int) $settings['seed'];
        }

        // Number of completions
        if (!empty($settings['n_completions']) && $settings['n_completions'] > 1) {
            $params['n'] = (int) $settings['n_completions'];
        }

        // Logprobs
        if (!empty($settings['logprobs']) && $settings['logprobs'] === 'true') {
            $params['logprobs'] = true;
        }

        // User identifier for OpenAI monitoring
        if (($settings['include_user_id'] ?? 'true') === 'true') {
            $params['user'] = 'user_' . $user->id;
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

        // Add system message (same as admin flow - no modifications)
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        // Get previous messages for context - from conversation if provided, else from user
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
     * Clear conversation thread (for conversations)
     */
    public function clearConversationThread(Conversation $conversation): void
    {
        if ($conversation->openai_thread_id) {
            $this->getAssistantService()->clearConversationThread($conversation);
        }
    }

    /**
     * Clear user's chat history (legacy - clears all user threads)
     */
    public function clearHistory(User $user): void
    {
        // If user has a thread, try to delete it from OpenAI
        if ($user->openai_thread_id) {
            $this->getAssistantService()->clearThread($user);
        } else {
            $user->update(['openai_thread_id' => null]);
        }
    }

    /**
     * Get the welcome message for a user
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
     * Get the assistant name for a user
     */
    public function getAssistantName(?User $user = null): string
    {
        if ($user) {
            $assistant = $user->getAssistant();
            if ($assistant) {
                return $assistant->assistant_display_name;
            }
        }

        return AiSetting::getValue('assistant_name', 'Asistente');
    }

    /**
     * Check if the model uses max_completion_tokens instead of max_tokens
     */
    private function usesMaxCompletionTokens(string $model): bool
    {
        $newModels = ['gpt-5', 'o1', 'o1-mini', 'o1-preview'];
        foreach ($newModels as $newModel) {
            if (str_starts_with($model, $newModel)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the model is an o1 model (doesn't support temperature)
     */
    private function isO1Model(string $model): bool
    {
        return str_starts_with($model, 'o1');
    }

    /**
     * Check if a model supports streaming
     */
    public function supportsStreaming(string $model): bool
    {
        // o1 models don't support streaming well
        return !str_starts_with($model, 'o1');
    }

    /**
     * Send a message with streaming response
     *
     * @return \Generator yields chunks of text
     */
    public function sendMessageStreamed(User $user, string $message, ?Conversation $conversation = null): \Generator
    {
        $assistant = $conversation?->assistant ?? $user->getAssistant();

        // If using Responses API (knowledge base), use streaming from that service
        if ($assistant && $assistant->usesResponsesApi()) {
            yield from $this->getAssistantService()->sendMessageStreamed($user, $message, $assistant, $conversation);
            return;
        }

        $settings = $assistant ? $assistant->toSettingsArray() : AiSetting::getAllValues();
        $model = $settings['model'] ?? 'gpt-4o-mini';

        // If model doesn't support streaming, fall back to regular
        if (!$this->supportsStreaming($model)) {
            $response = $this->sendMessage($user, $message, $conversation);
            yield [
                'type' => 'content',
                'content' => $response['content'],
            ];
            yield [
                'type' => 'done',
                'tokens_input' => $response['tokens_input'],
                'tokens_output' => $response['tokens_output'],
                'message_id' => $response['message_id'],
            ];
            return;
        }

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
        $requestParams['stream_options'] = ['include_usage' => true];

        $stream = OpenAI::chat()->createStreamed($requestParams);

        $fullContent = '';
        $tokensInput = 0;
        $tokensOutput = 0;
        $messageId = null;

        foreach ($stream as $response) {
            // Get message ID from first response
            if ($messageId === null && isset($response->id)) {
                $messageId = $response->id;
            }

            // Get content delta (skip empty chunks)
            if (isset($response->choices[0]->delta->content)) {
                $chunk = $response->choices[0]->delta->content;
                if ($chunk !== null && $chunk !== '') {
                    $fullContent .= $chunk;
                    yield [
                        'type' => 'content',
                        'content' => $chunk,
                    ];
                }
            }

            // Get usage from final chunk
            if (isset($response->usage)) {
                $tokensInput = $response->usage->promptTokens ?? 0;
                $tokensOutput = $response->usage->completionTokens ?? 0;
            }
        }

        yield [
            'type' => 'done',
            'full_content' => $fullContent,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'message_id' => $messageId ?? 'stream_' . time(),
        ];
    }

    /**
     * Get the messages array for streaming (public for controller access)
     */
    public function buildMessagesForStreaming(
        User $user,
        string $message,
        ?Conversation $conversation = null
    ): array {
        $assistant = $conversation?->assistant ?? $user->getAssistant();
        $settings = $assistant ? $assistant->toSettingsArray() : AiSetting::getAllValues();

        return [
            'messages' => $this->buildMessagesArray(
                $user,
                $settings['system_prompt'] ?? 'Eres un asistente util.',
                $message,
                (int) ($settings['context_messages'] ?? 10),
                $settings['filter_unsafe_content'] ?? 'true',
                $conversation
            ),
            'settings' => $settings,
            'assistant' => $assistant,
        ];
    }
}

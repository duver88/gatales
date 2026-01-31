<?php

namespace App\Services;

use App\Models\Assistant;
use App\Models\AssistantFile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIAssistantService
{
    /**
     * Supported file types for OpenAI file_search
     */
    private const SUPPORTED_TYPES = [
        'application/pdf',
        'text/plain',
        'text/markdown',
        'text/html',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/csv',
        'application/json',
    ];

    private const MAX_FILE_SIZE = 512 * 1024 * 1024; // 512 MB

    /**
     * Send a message using Responses API (supports GPT-5 with file_search)
     *
     * @return array{content: string, tokens_input: int, tokens_output: int, message_id: string}
     */
    public function sendMessage(User $user, string $message, Assistant $assistant, ?Conversation $conversation = null): array
    {
        // Build the input messages array (conversation history + new message)
        $input = $this->buildInputMessages($user, $message, $assistant, $conversation);

        // Build request params
        $params = [
            'model' => $assistant->model,
            'instructions' => $assistant->system_prompt,
            'input' => $input,
            'max_output_tokens' => (int) $assistant->max_tokens,
        ];

        // Add temperature if supported
        if ($this->supportsTemperature($assistant->model)) {
            $params['temperature'] = (float) $assistant->temperature;
        }

        // Add file_search tool if knowledge base is enabled
        if ($assistant->use_knowledge_base && $assistant->openai_vector_store_id) {
            $params['tools'] = [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => [$assistant->openai_vector_store_id],
                ],
            ];
        }

        try {
            // Call the Responses API
            $response = $this->callResponsesApi($params);

            // Extract response content
            $content = $this->extractResponseContent($response);
            $tokensInput = $response['usage']['input_tokens'] ?? 0;
            $tokensOutput = $response['usage']['output_tokens'] ?? 0;

            return [
                'content' => $content,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'message_id' => $response['id'] ?? 'resp_' . time(),
            ];
        } catch (\Exception $e) {
            Log::error('Error in Responses API: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'assistant_id' => $assistant->id,
                'conversation_id' => $conversation?->id,
                'model' => $assistant->model,
            ]);
            throw $e;
        }
    }

    /**
     * Send a message with streaming using Responses API
     *
     * @return \Generator
     */
    public function sendMessageStreamed(User $user, string $message, Assistant $assistant, ?Conversation $conversation = null): \Generator
    {
        $input = $this->buildInputMessages($user, $message, $assistant, $conversation);

        // Build request params
        $params = [
            'model' => $assistant->model,
            'instructions' => $assistant->system_prompt,
            'input' => $input,
            'max_output_tokens' => (int) $assistant->max_tokens,
            'stream' => true,
        ];

        // Add temperature if supported
        if ($this->supportsTemperature($assistant->model)) {
            $params['temperature'] = (float) $assistant->temperature;
        }

        // Add file_search tool if knowledge base is enabled
        if ($assistant->use_knowledge_base && $assistant->openai_vector_store_id) {
            $params['tools'] = [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => [$assistant->openai_vector_store_id],
                ],
            ];
        }

        try {
            $fullContent = '';
            $tokensInput = 0;
            $tokensOutput = 0;
            $messageId = null;

            // Stream from Responses API
            foreach ($this->streamResponsesApi($params) as $chunk) {
                if ($chunk['type'] === 'content') {
                    $fullContent .= $chunk['content'];
                    yield $chunk;
                } elseif ($chunk['type'] === 'done') {
                    $tokensInput = $chunk['tokens_input'] ?? 0;
                    $tokensOutput = $chunk['tokens_output'] ?? 0;
                    $messageId = $chunk['message_id'] ?? null;
                }
            }

            yield [
                'type' => 'done',
                'full_content' => $fullContent,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'message_id' => $messageId ?? 'resp_' . time(),
            ];
        } catch (\Exception $e) {
            Log::error('Error in Responses API streaming: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'assistant_id' => $assistant->id,
            ]);
            throw $e;
        }
    }

    /**
     * Build input messages array for Responses API
     */
    private function buildInputMessages(User $user, string $message, Assistant $assistant, ?Conversation $conversation = null): array
    {
        $input = [];
        $contextLimit = (int) ($assistant->context_messages ?? 10);

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

        // Add previous messages
        foreach ($previousMessages as $msg) {
            $input[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        // Add the new user message
        $input[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $input;
    }

    /**
     * Call the OpenAI Responses API
     */
    private function callResponsesApi(array $params): array
    {
        $apiKey = config('openai.api_key');

        // Remove null values
        $params = array_filter($params, fn($v) => $v !== null);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.openai.com/v1/responses', $params);

        if (!$response->successful()) {
            $error = $response->json();
            throw new \RuntimeException(
                $error['error']['message'] ?? 'Error calling Responses API: ' . $response->status()
            );
        }

        return $response->json();
    }

    /**
     * Stream from the OpenAI Responses API using cURL (more compatible with hosting environments)
     */
    private function streamResponsesApi(array $params): \Generator
    {
        $apiKey = config('openai.api_key');

        // Remove null values
        $params = array_filter($params, fn($v) => $v !== null);

        // Use cURL for better compatibility with hosting environments
        $ch = curl_init('https://api.openai.com/v1/responses');

        $chunks = [];
        $tokensInput = 0;
        $tokensOutput = 0;
        $messageId = null;
        $buffer = '';
        $errorMessage = null;

        // Set up cURL options with a write callback for streaming
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$chunks, &$buffer, &$tokensInput, &$tokensOutput, &$messageId, &$errorMessage) {
                $buffer .= $data;

                // Process complete lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    $line = trim($line);
                    if (empty($line) || $line === 'data: [DONE]') {
                        continue;
                    }

                    if (str_starts_with($line, 'data: ')) {
                        $json = substr($line, 6);
                        $event = json_decode($json, true);

                        if (!$event) continue;

                        // Check for error
                        if (isset($event['error'])) {
                            $errorMessage = $event['error']['message'] ?? 'Unknown API error';
                            continue;
                        }

                        // Handle different event types from Responses API
                        if (isset($event['type'])) {
                            switch ($event['type']) {
                                case 'response.output_text.delta':
                                    if (isset($event['delta'])) {
                                        $chunks[] = ['type' => 'content', 'content' => $event['delta']];
                                    }
                                    break;

                                case 'response.completed':
                                    if (isset($event['response'])) {
                                        $messageId = $event['response']['id'] ?? null;
                                        $tokensInput = $event['response']['usage']['input_tokens'] ?? 0;
                                        $tokensOutput = $event['response']['usage']['output_tokens'] ?? 0;
                                    }
                                    break;
                            }
                        }

                        // Also check for content delta in different format
                        if (isset($event['delta']['content'])) {
                            $chunks[] = ['type' => 'content', 'content' => $event['delta']['content']];
                        }

                        // Check for usage info
                        if (isset($event['usage'])) {
                            $tokensInput = $event['usage']['input_tokens'] ?? $tokensInput;
                            $tokensOutput = $event['usage']['output_tokens'] ?? $tokensOutput;
                        }

                        if (isset($event['id']) && !$messageId) {
                            $messageId = $event['id'];
                        }
                    }
                }

                return strlen($data);
            },
        ]);

        // Execute cURL request
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($errorMessage) {
            throw new \RuntimeException($errorMessage);
        }

        if (!$success) {
            throw new \RuntimeException(
                $curlError ?: "Error calling Responses API: connection failed"
            );
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException(
                "Error calling Responses API: HTTP $httpCode"
            );
        }

        // Yield all collected chunks
        foreach ($chunks as $chunk) {
            yield $chunk;
        }

        yield [
            'type' => 'done',
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'message_id' => $messageId,
        ];
    }

    /**
     * Extract text content from Responses API response
     */
    private function extractResponseContent(array $response): string
    {
        $content = '';

        // The output array contains the response items
        if (isset($response['output'])) {
            foreach ($response['output'] as $item) {
                if ($item['type'] === 'message' && isset($item['content'])) {
                    foreach ($item['content'] as $contentBlock) {
                        if ($contentBlock['type'] === 'output_text') {
                            $content .= $contentBlock['text'] ?? '';
                        } elseif ($contentBlock['type'] === 'text') {
                            $content .= $contentBlock['text'] ?? '';
                        }
                    }
                }
            }
        }

        // Fallback: check for direct content
        if (empty($content) && isset($response['content'])) {
            foreach ($response['content'] as $contentBlock) {
                if (isset($contentBlock['text'])) {
                    $content .= $contentBlock['text'];
                }
            }
        }

        return $content;
    }

    /**
     * Check if model supports temperature parameter
     */
    private function supportsTemperature(string $model): bool
    {
        // GPT-5 and o1 models don't support custom temperature
        $noTempModels = ['gpt-5', 'o1', 'o1-mini', 'o1-preview'];
        foreach ($noTempModels as $noTempModel) {
            if (str_starts_with($model, $noTempModel)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Create a vector store for the assistant
     */
    public function createVectorStore(Assistant $assistant): string
    {
        try {
            $response = OpenAI::vectorStores()->create([
                'name' => "vs_{$assistant->slug}",
            ]);

            $assistant->update(['openai_vector_store_id' => $response->id]);

            return $response->id;
        } catch (\Exception $e) {
            Log::error('Error creating vector store: ' . $e->getMessage(), [
                'assistant_id' => $assistant->id,
            ]);
            throw $e;
        }
    }

    /**
     * Upload a file to OpenAI and attach to vector store
     */
    public function uploadFile(Assistant $assistant, UploadedFile $file): AssistantFile
    {
        // Validate file
        if (!in_array($file->getMimeType(), self::SUPPORTED_TYPES)) {
            throw new \InvalidArgumentException(
                'Tipo de archivo no soportado. Usa PDF, DOCX, TXT, MD, HTML, XLS, XLSX, PPT, PPTX, CSV o JSON.'
            );
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('El archivo excede el tamaño máximo de 512 MB.');
        }

        // Ensure we have a vector store
        if (!$assistant->openai_vector_store_id) {
            $this->createVectorStore($assistant);
            $assistant->refresh();
        }

        // Store file locally first
        $storagePath = $file->store('assistant_files/' . $assistant->id, 'local');

        // Create local record
        $assistantFile = AssistantFile::create([
            'assistant_id' => $assistant->id,
            'openai_file_id' => '',
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'processing',
            'storage_path' => $storagePath,
        ]);

        try {
            // Upload to OpenAI
            $localPath = Storage::disk('local')->path($storagePath);

            $openAIFile = OpenAI::files()->upload([
                'file' => fopen($localPath, 'r'),
                'purpose' => 'assistants',
            ]);

            $assistantFile->update(['openai_file_id' => $openAIFile->id]);

            // Add file to vector store
            OpenAI::vectorStores()->files()->create(
                $assistant->openai_vector_store_id,
                ['file_id' => $openAIFile->id]
            );

            // Mark as ready
            $assistantFile->update(['status' => 'ready']);

            return $assistantFile;
        } catch (\Exception $e) {
            Log::error('Error uploading file to OpenAI: ' . $e->getMessage(), [
                'assistant_id' => $assistant->id,
                'file_name' => $file->getClientOriginalName(),
            ]);

            $assistantFile->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a file from OpenAI and local storage
     */
    public function deleteFile(AssistantFile $file): void
    {
        $assistant = $file->assistant;

        try {
            // Remove from vector store first
            if ($assistant->openai_vector_store_id && $file->openai_file_id) {
                try {
                    OpenAI::vectorStores()->files()->delete(
                        $assistant->openai_vector_store_id,
                        $file->openai_file_id
                    );
                } catch (\Exception $e) {
                    Log::warning('Could not delete file from vector store: ' . $e->getMessage());
                }
            }

            // Delete from OpenAI files
            if ($file->openai_file_id) {
                try {
                    OpenAI::files()->delete($file->openai_file_id);
                } catch (\Exception $e) {
                    Log::warning('Could not delete OpenAI file: ' . $e->getMessage());
                }
            }

            // Delete local file
            if ($file->storage_path) {
                Storage::disk('local')->delete($file->storage_path);
            }

            // Delete database record
            $file->delete();
        } catch (\Exception $e) {
            Log::error('Error deleting file: ' . $e->getMessage(), [
                'file_id' => $file->id,
            ]);
            throw $e;
        }
    }

    /**
     * Delete the vector store (no more OpenAI assistant to delete with Responses API)
     */
    public function deleteAssistant(Assistant $assistant): void
    {
        try {
            // Delete all files first
            foreach ($assistant->files as $file) {
                $this->deleteFile($file);
            }

            // Delete vector store
            if ($assistant->openai_vector_store_id) {
                try {
                    OpenAI::vectorStores()->delete($assistant->openai_vector_store_id);
                } catch (\Exception $e) {
                    Log::warning('Could not delete vector store: ' . $e->getMessage());
                }
            }

            // Clear OpenAI IDs (no more openai_assistant_id needed with Responses API)
            $assistant->update([
                'openai_assistant_id' => null,
                'openai_vector_store_id' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting assistant resources: ' . $e->getMessage(), [
                'assistant_id' => $assistant->id,
            ]);
            throw $e;
        }
    }

    /**
     * Sync assistant - with Responses API, we just need the vector store
     * No need to create an OpenAI Assistant object anymore
     */
    public function syncAssistant(Assistant $assistant): void
    {
        // With Responses API, we don't need to create an OpenAI Assistant
        // We just need to ensure the vector store exists if knowledge base is enabled
        if ($assistant->use_knowledge_base && !$assistant->openai_vector_store_id) {
            $this->createVectorStore($assistant);
        }
    }

    /**
     * Clear conversation thread - no longer needed with Responses API (stateless)
     */
    public function clearConversationThread(Conversation $conversation): void
    {
        // With Responses API, there are no threads to clear
        // Just clear the thread ID if it exists (legacy cleanup)
        if ($conversation->openai_thread_id) {
            $conversation->update(['openai_thread_id' => null]);
        }
    }

    /**
     * Clear user's thread - no longer needed with Responses API
     */
    public function clearThread(User $user): void
    {
        // With Responses API, there are no threads
        // Just clear the thread ID if it exists (legacy cleanup)
        if ($user->openai_thread_id) {
            $user->update(['openai_thread_id' => null]);
        }
    }

    /**
     * Get file status from vector store
     */
    public function getFileStatus(Assistant $assistant, string $openaiFileId): string
    {
        if (!$assistant->openai_vector_store_id) {
            return 'unknown';
        }

        try {
            $file = OpenAI::vectorStores()->files()->retrieve(
                $assistant->openai_vector_store_id,
                $openaiFileId
            );

            return $file->status ?? 'unknown';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    /**
     * Get supported file types
     */
    public static function getSupportedTypes(): array
    {
        return [
            'pdf' => 'PDF',
            'docx' => 'Word Document',
            'doc' => 'Word Document (legacy)',
            'txt' => 'Text File',
            'md' => 'Markdown',
            'html' => 'HTML',
            'xlsx' => 'Excel',
            'xls' => 'Excel (legacy)',
            'pptx' => 'PowerPoint',
            'ppt' => 'PowerPoint (legacy)',
            'csv' => 'CSV',
            'json' => 'JSON',
        ];
    }

    /**
     * Get max file size in MB
     */
    public static function getMaxFileSizeMB(): int
    {
        return 512;
    }

    /**
     * Send a message for testing (admin panel)
     */
    public function sendMessageForTest(string $message, Assistant $assistant, array $context = []): array
    {
        // Build input from context + new message
        $input = [];
        foreach ($context as $msg) {
            $input[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }
        $input[] = [
            'role' => 'user',
            'content' => $message,
        ];

        // Build request params
        $params = [
            'model' => $assistant->model,
            'instructions' => $assistant->system_prompt,
            'input' => $input,
            'max_output_tokens' => (int) $assistant->max_tokens,
        ];

        // Add temperature if supported
        if ($this->supportsTemperature($assistant->model)) {
            $params['temperature'] = (float) $assistant->temperature;
        }

        // Add file_search tool if knowledge base is enabled
        if ($assistant->use_knowledge_base && $assistant->openai_vector_store_id) {
            $params['tools'] = [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => [$assistant->openai_vector_store_id],
                ],
            ];
        }

        try {
            $response = $this->callResponsesApi($params);

            $content = $this->extractResponseContent($response);
            $tokensInput = $response['usage']['input_tokens'] ?? 0;
            $tokensOutput = $response['usage']['output_tokens'] ?? 0;

            return [
                'response' => $content,
                'usage' => [
                    'prompt_tokens' => $tokensInput,
                    'completion_tokens' => $tokensOutput,
                    'total_tokens' => $tokensInput + $tokensOutput,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error in test message: ' . $e->getMessage(), [
                'assistant_id' => $assistant->id,
            ]);
            throw $e;
        }
    }

    /**
     * Send a message for testing with streaming (admin panel)
     */
    public function sendMessageStreamedForTest(string $message, Assistant $assistant, array $context = []): \Generator
    {
        // Build input from context + new message
        $input = [];
        foreach ($context as $msg) {
            $input[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }
        $input[] = [
            'role' => 'user',
            'content' => $message,
        ];

        // Build request params
        $params = [
            'model' => $assistant->model,
            'instructions' => $assistant->system_prompt,
            'input' => $input,
            'max_output_tokens' => (int) $assistant->max_tokens,
            'stream' => true,
        ];

        // Add temperature if supported
        if ($this->supportsTemperature($assistant->model)) {
            $params['temperature'] = (float) $assistant->temperature;
        }

        // Add file_search tool if knowledge base is enabled
        if ($assistant->use_knowledge_base && $assistant->openai_vector_store_id) {
            $params['tools'] = [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => [$assistant->openai_vector_store_id],
                ],
            ];
        }

        try {
            $fullContent = '';
            $tokensInput = 0;
            $tokensOutput = 0;
            $messageId = null;

            foreach ($this->streamResponsesApi($params) as $chunk) {
                if ($chunk['type'] === 'content') {
                    $fullContent .= $chunk['content'];
                    yield $chunk;
                } elseif ($chunk['type'] === 'done') {
                    $tokensInput = $chunk['tokens_input'] ?? 0;
                    $tokensOutput = $chunk['tokens_output'] ?? 0;
                    $messageId = $chunk['message_id'] ?? null;
                }
            }

            yield [
                'type' => 'done',
                'full_content' => $fullContent,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'message_id' => $messageId ?? 'resp_' . time(),
            ];
        } catch (\Exception $e) {
            Log::error('Error in test message streaming: ' . $e->getMessage(), [
                'assistant_id' => $assistant->id,
            ]);
            throw $e;
        }
    }
}

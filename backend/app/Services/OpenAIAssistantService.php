<?php

namespace App\Services;

use App\Models\Assistant;
use App\Models\AssistantFile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIAssistantService
{
    /**
     * Supported file types for OpenAI Assistants API file_search
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
     * Create or update OpenAI Assistant for the local assistant
     */
    public function syncAssistant(Assistant $assistant): void
    {
        $tools = [];
        $toolResources = [];

        // If knowledge base is enabled, add file_search tool
        if ($assistant->use_knowledge_base) {
            $tools[] = ['type' => 'file_search'];

            // If we have a vector store, attach it
            if ($assistant->openai_vector_store_id) {
                $toolResources['file_search'] = [
                    'vector_store_ids' => [$assistant->openai_vector_store_id],
                ];
            }
        }

        $assistantData = [
            'name' => $assistant->name,
            'description' => $assistant->description ?? '',
            'instructions' => $assistant->system_prompt,
            'model' => $assistant->model,
            'tools' => $tools,
        ];

        if (!empty($toolResources)) {
            $assistantData['tool_resources'] = $toolResources;
        }

        try {
            if ($assistant->openai_assistant_id) {
                // Update existing assistant
                OpenAI::assistants()->modify($assistant->openai_assistant_id, $assistantData);
            } else {
                // Create new assistant
                $response = OpenAI::assistants()->create($assistantData);
                $assistant->update(['openai_assistant_id' => $response->id]);
            }
        } catch (\Exception $e) {
            Log::error('Error syncing OpenAI Assistant: ' . $e->getMessage(), [
                'assistant_id' => $assistant->id,
            ]);
            throw $e;
        }
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

            // If assistant already exists, update it with the vector store
            if ($assistant->openai_assistant_id) {
                $this->syncAssistant($assistant);
            }

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

        // Ensure we have an OpenAI assistant
        if (!$assistant->openai_assistant_id) {
            $this->syncAssistant($assistant);
            $assistant->refresh();
        }

        // Store file locally first
        $storagePath = $file->store('assistant_files/' . $assistant->id, 'local');

        // Create local record
        $assistantFile = AssistantFile::create([
            'assistant_id' => $assistant->id,
            'openai_file_id' => '', // Will be updated after upload
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

            // Mark as ready (vector store processing happens async)
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
                    // Vector store file might already be deleted
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
     * Delete the OpenAI assistant and vector store
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

            // Delete assistant
            if ($assistant->openai_assistant_id) {
                try {
                    OpenAI::assistants()->delete($assistant->openai_assistant_id);
                } catch (\Exception $e) {
                    Log::warning('Could not delete OpenAI assistant: ' . $e->getMessage());
                }
            }

            // Clear OpenAI IDs
            $assistant->update([
                'openai_assistant_id' => null,
                'openai_vector_store_id' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting OpenAI assistant: ' . $e->getMessage(), [
                'assistant_id' => $assistant->id,
            ]);
            throw $e;
        }
    }

    /**
     * Send a message using Assistants API (for assistants with knowledge base)
     */
    public function sendMessage(User $user, string $message, Assistant $assistant, ?Conversation $conversation = null): array
    {
        // Get or create thread - from conversation if provided, else from user (legacy)
        if ($conversation) {
            $threadId = $conversation->openai_thread_id;
            if (!$threadId) {
                $thread = OpenAI::threads()->create([]);
                $threadId = $thread->id;
                $conversation->update(['openai_thread_id' => $threadId]);
            }
        } else {
            // Legacy: use user's thread
            $threadId = $user->openai_thread_id;
            if (!$threadId) {
                $thread = OpenAI::threads()->create([]);
                $threadId = $thread->id;
                $user->update(['openai_thread_id' => $threadId]);
            }
        }

        try {
            // Add user message to thread
            OpenAI::threads()->messages()->create($threadId, [
                'role' => 'user',
                'content' => $message,
            ]);

            // Run the assistant
            $runParams = [
                'assistant_id' => $assistant->openai_assistant_id,
                'max_completion_tokens' => (int) $assistant->max_tokens,
            ];

            // o1 and GPT-5 models don't support custom temperature (only default=1)
            if (!str_starts_with($assistant->model, 'o1') && !str_starts_with($assistant->model, 'gpt-5')) {
                $runParams['temperature'] = (float) $assistant->temperature;
            }

            $run = OpenAI::threads()->runs()->create($threadId, $runParams);

            // Wait for completion (with timeout)
            $maxAttempts = 60; // 60 seconds timeout
            $attempts = 0;

            while (in_array($run->status, ['queued', 'in_progress', 'requires_action'])) {
                if ($attempts >= $maxAttempts) {
                    throw new \RuntimeException('La respuesta del asistente tardó demasiado.');
                }

                sleep(1);
                $run = OpenAI::threads()->runs()->retrieve($threadId, $run->id);
                $attempts++;
            }

            if ($run->status === 'failed') {
                throw new \RuntimeException('El asistente no pudo procesar la solicitud: ' . ($run->lastError?->message ?? 'Error desconocido'));
            }

            if ($run->status !== 'completed') {
                throw new \RuntimeException('Estado inesperado del asistente: ' . $run->status);
            }

            // Get the assistant's response
            $messages = OpenAI::threads()->messages()->list($threadId, [
                'limit' => 1,
                'order' => 'desc',
            ]);

            $content = '';
            if (!empty($messages->data)) {
                $lastMessage = $messages->data[0];
                foreach ($lastMessage->content as $contentBlock) {
                    if ($contentBlock->type === 'text') {
                        $content .= $contentBlock->text->value;
                    }
                }
            }

            // Get token usage from run
            $tokensInput = $run->usage?->promptTokens ?? 0;
            $tokensOutput = $run->usage?->completionTokens ?? 0;

            return [
                'content' => $content,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'message_id' => $run->id,
            ];
        } catch (\Exception $e) {
            Log::error('Error in Assistants API: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'assistant_id' => $assistant->id,
                'conversation_id' => $conversation?->id,
            ]);

            // If thread is corrupted, create a new one
            if (str_contains($e->getMessage(), 'thread') || str_contains($e->getMessage(), 'Thread')) {
                if ($conversation) {
                    $conversation->update(['openai_thread_id' => null]);
                } else {
                    $user->update(['openai_thread_id' => null]);
                }
            }

            throw $e;
        }
    }

    /**
     * Clear conversation thread
     */
    public function clearConversationThread(Conversation $conversation): void
    {
        if ($conversation->openai_thread_id) {
            try {
                OpenAI::threads()->delete($conversation->openai_thread_id);
            } catch (\Exception $e) {
                Log::warning('Could not delete conversation thread: ' . $e->getMessage());
            }

            $conversation->update(['openai_thread_id' => null]);
        }
    }

    /**
     * Clear user's thread (legacy - for when they switch assistants or clear history)
     */
    public function clearThread(User $user): void
    {
        if ($user->openai_thread_id) {
            try {
                OpenAI::threads()->delete($user->openai_thread_id);
            } catch (\Exception $e) {
                Log::warning('Could not delete thread: ' . $e->getMessage());
            }

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
     * Send a message for testing (admin panel) - creates a temporary thread
     */
    public function sendMessageForTest(string $message, Assistant $assistant, array $context = []): array
    {
        // Create a temporary thread for testing
        $thread = OpenAI::threads()->create([]);
        $threadId = $thread->id;

        try {
            // Add context messages first (previous conversation)
            foreach ($context as $msg) {
                OpenAI::threads()->messages()->create($threadId, [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ]);
            }

            // Add current user message
            OpenAI::threads()->messages()->create($threadId, [
                'role' => 'user',
                'content' => $message,
            ]);

            // Run the assistant
            $runParams = [
                'assistant_id' => $assistant->openai_assistant_id,
                'max_completion_tokens' => (int) $assistant->max_tokens,
            ];

            // o1 and GPT-5 models don't support custom temperature (only default=1)
            if (!str_starts_with($assistant->model, 'o1') && !str_starts_with($assistant->model, 'gpt-5')) {
                $runParams['temperature'] = (float) $assistant->temperature;
            }

            $run = OpenAI::threads()->runs()->create($threadId, $runParams);

            // Wait for completion (with timeout)
            $maxAttempts = 60;
            $attempts = 0;

            while (in_array($run->status, ['queued', 'in_progress', 'requires_action'])) {
                if ($attempts >= $maxAttempts) {
                    throw new \RuntimeException('La respuesta del asistente tardó demasiado.');
                }

                sleep(1);
                $run = OpenAI::threads()->runs()->retrieve($threadId, $run->id);
                $attempts++;
            }

            if ($run->status === 'failed') {
                throw new \RuntimeException('El asistente no pudo procesar la solicitud: ' . ($run->lastError?->message ?? 'Error desconocido'));
            }

            if ($run->status !== 'completed') {
                throw new \RuntimeException('Estado inesperado del asistente: ' . $run->status);
            }

            // Get the assistant's response
            $messages = OpenAI::threads()->messages()->list($threadId, [
                'limit' => 1,
                'order' => 'desc',
            ]);

            $content = '';
            if (!empty($messages->data)) {
                $lastMessage = $messages->data[0];
                foreach ($lastMessage->content as $contentBlock) {
                    if ($contentBlock->type === 'text') {
                        $content .= $contentBlock->text->value;
                    }
                }
            }

            // Get token usage
            $tokensInput = $run->usage?->promptTokens ?? 0;
            $tokensOutput = $run->usage?->completionTokens ?? 0;

            // Delete temporary thread
            try {
                OpenAI::threads()->delete($threadId);
            } catch (\Exception $e) {
                Log::warning('Could not delete test thread: ' . $e->getMessage());
            }

            return [
                'response' => $content,
                'usage' => [
                    'prompt_tokens' => $tokensInput,
                    'completion_tokens' => $tokensOutput,
                    'total_tokens' => $tokensInput + $tokensOutput,
                ],
            ];
        } catch (\Exception $e) {
            // Clean up thread on error
            try {
                OpenAI::threads()->delete($threadId);
            } catch (\Exception $cleanupError) {
                Log::warning('Could not delete test thread on error: ' . $cleanupError->getMessage());
            }

            throw $e;
        }
    }
}

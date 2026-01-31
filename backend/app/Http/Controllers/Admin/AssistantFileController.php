<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assistant;
use App\Models\AssistantFile;
use App\Services\OpenAIAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantFileController extends Controller
{
    public function __construct(
        protected OpenAIAssistantService $assistantService
    ) {}

    /**
     * List files for an assistant
     */
    public function index(Assistant $assistant): JsonResponse
    {
        $files = $assistant->files()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'file_size' => $file->file_size,
                    'formatted_size' => $file->formatted_size,
                    'status' => $file->status,
                    'error_message' => $file->error_message,
                    'extension' => $file->extension,
                    'icon' => $file->icon,
                    'created_at' => $file->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'files' => $files,
            'stats' => [
                'total' => $files->count(),
                'ready' => $files->where('status', 'ready')->count(),
                'processing' => $files->where('status', 'processing')->count(),
                'failed' => $files->where('status', 'failed')->count(),
                'total_size' => $files->sum('file_size'),
            ],
            'supported_types' => OpenAIAssistantService::getSupportedTypes(),
            'max_file_size_mb' => OpenAIAssistantService::getMaxFileSizeMB(),
        ]);
    }

    /**
     * Upload a file to assistant's knowledge base
     */
    public function store(Request $request, Assistant $assistant): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:524288', // 512 MB in KB
        ]);

        try {
            $file = $this->assistantService->uploadFile($assistant, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Archivo subido correctamente',
                'file' => [
                    'id' => $file->id,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'file_size' => $file->file_size,
                    'formatted_size' => $file->formatted_size,
                    'status' => $file->status,
                    'extension' => $file->extension,
                    'icon' => $file->icon,
                    'created_at' => $file->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a file from assistant's knowledge base
     */
    public function destroy(Assistant $assistant, AssistantFile $file): JsonResponse
    {
        // Verify file belongs to assistant
        if ($file->assistant_id !== $assistant->id) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo no pertenece a este asistente',
            ], 404);
        }

        try {
            $this->assistantService->deleteFile($file);

            return response()->json([
                'success' => true,
                'message' => 'Archivo eliminado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enable knowledge base for an assistant
     */
    public function enableKnowledgeBase(Assistant $assistant): JsonResponse
    {
        try {
            // Enable knowledge base flag and disable reasoning (they conflict)
            // GPT-5 reasoning_effort is not compatible with file_search tools
            $updateData = ['use_knowledge_base' => true];
            $reasoningDisabled = false;

            // If this is a GPT-5 model, set reasoning_effort to 'none' to avoid conflict
            if (str_starts_with($assistant->model, 'gpt-5') && $assistant->reasoning_effort !== 'none') {
                $updateData['reasoning_effort'] = 'none';
                $reasoningDisabled = true;
            }

            $assistant->update($updateData);

            // Create or update OpenAI assistant with file_search
            $this->assistantService->syncAssistant($assistant);

            $message = 'Base de conocimientos habilitada';
            if ($reasoningDisabled) {
                $message .= ' (Reasoning Effort desactivado automaticamente)';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'assistant' => $assistant->fresh(),
                'reasoning_disabled' => $reasoningDisabled,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al habilitar la base de conocimientos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable knowledge base for an assistant
     */
    public function disableKnowledgeBase(Assistant $assistant): JsonResponse
    {
        try {
            // This will delete all files and OpenAI resources
            $this->assistantService->deleteAssistant($assistant);

            // Disable knowledge base flag
            $assistant->update(['use_knowledge_base' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Base de conocimientos deshabilitada',
                'assistant' => $assistant->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al deshabilitar la base de conocimientos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync assistant configuration with OpenAI
     */
    public function sync(Assistant $assistant): JsonResponse
    {
        if (!$assistant->use_knowledge_base) {
            return response()->json([
                'success' => false,
                'message' => 'La base de conocimientos no está habilitada',
            ], 400);
        }

        try {
            $this->assistantService->syncAssistant($assistant);

            return response()->json([
                'success' => true,
                'message' => 'Configuración sincronizada con OpenAI',
                'assistant' => $assistant->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar: ' . $e->getMessage(),
            ], 500);
        }
    }
}

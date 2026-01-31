<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class AssistantController extends Controller
{
    /**
     * List all assistants
     */
    public function index(): JsonResponse
    {
        $assistants = Assistant::withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'assistants' => $assistants,
            'available_models' => Assistant::getAvailableModels(),
            'reasoning_effort_options' => Assistant::getReasoningEffortOptions(),
        ]);
    }

    /**
     * Show a single assistant
     */
    public function show(Assistant $assistant): JsonResponse
    {
        $assistant->loadCount('users');

        return response()->json([
            'success' => true,
            'assistant' => $assistant,
        ]);
    }

    /**
     * Create a new assistant
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'model' => 'required|string',
            'reasoning_effort' => 'string|in:none,minimal,low,medium,high,xhigh',
            'system_prompt' => 'required|string',
            'temperature' => 'numeric|min:0|max:2',
            'max_tokens' => 'integer|min:100|max:16000',
            'top_p' => 'numeric|min:0|max:1',
            'frequency_penalty' => 'numeric|min:-2|max:2',
            'presence_penalty' => 'numeric|min:-2|max:2',
            'response_format' => 'string|in:text,json_object',
            'stop_sequences' => 'nullable|string',
            'seed' => 'nullable|integer',
            'n_completions' => 'integer|min:1|max:5',
            'logprobs' => 'boolean',
            'stream' => 'boolean',
            'assistant_display_name' => 'required|string|max:255',
            'welcome_message' => 'required|string',
            'context_messages' => 'integer|min:1|max:50',
            'filter_unsafe_content' => 'boolean',
            'include_user_id' => 'boolean',
            'avatar_url' => 'nullable|string|max:500',
        ]);

        // Generate unique slug
        $validated['slug'] = Str::slug($validated['name']);
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (Assistant::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        $assistant = Assistant::create($validated);

        // Clear assistants cache
        Cache::forget('active_assistants');

        return response()->json([
            'success' => true,
            'message' => 'Asistente creado correctamente',
            'assistant' => $assistant,
        ], 201);
    }

    /**
     * Update an assistant
     */
    public function update(Request $request, Assistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'model' => 'sometimes|string',
            'reasoning_effort' => 'sometimes|string|in:none,minimal,low,medium,high,xhigh',
            'system_prompt' => 'sometimes|string',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'max_tokens' => 'sometimes|integer|min:100|max:16000',
            'top_p' => 'sometimes|numeric|min:0|max:1',
            'frequency_penalty' => 'sometimes|numeric|min:-2|max:2',
            'presence_penalty' => 'sometimes|numeric|min:-2|max:2',
            'response_format' => 'sometimes|string|in:text,json_object',
            'stop_sequences' => 'nullable|string',
            'seed' => 'nullable|integer',
            'n_completions' => 'sometimes|integer|min:1|max:5',
            'logprobs' => 'sometimes|boolean',
            'stream' => 'sometimes|boolean',
            'assistant_display_name' => 'sometimes|string|max:255',
            'welcome_message' => 'sometimes|string',
            'context_messages' => 'sometimes|integer|min:1|max:50',
            'filter_unsafe_content' => 'sometimes|boolean',
            'include_user_id' => 'sometimes|boolean',
            'avatar_url' => 'nullable|string|max:500',
        ]);

        $assistant->update($validated);

        // Clear assistants cache
        Cache::forget('active_assistants');

        return response()->json([
            'success' => true,
            'message' => 'Asistente actualizado correctamente',
            'assistant' => $assistant->fresh(),
        ]);
    }

    /**
     * Delete an assistant
     */
    public function destroy(Assistant $assistant): JsonResponse
    {
        // Don't allow deleting the default assistant
        if ($assistant->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el asistente por defecto',
            ], 400);
        }

        // Reassign users to default assistant
        $default = Assistant::getDefault();
        if ($default) {
            $assistant->users()->update(['assistant_id' => $default->id]);
        } else {
            $assistant->users()->update(['assistant_id' => null]);
        }

        $assistant->delete();

        // Clear assistants cache
        Cache::forget('active_assistants');

        return response()->json([
            'success' => true,
            'message' => 'Asistente eliminado correctamente',
        ]);
    }

    /**
     * Set an assistant as default
     */
    public function setDefault(Assistant $assistant): JsonResponse
    {
        if (!$assistant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede establecer un asistente inactivo como predeterminado',
            ], 400);
        }

        Assistant::setDefault($assistant->id);

        // Clear assistants cache
        Cache::forget('active_assistants');

        return response()->json([
            'success' => true,
            'message' => 'Asistente establecido como predeterminado',
        ]);
    }

    /**
     * Duplicate an assistant
     */
    public function duplicate(Assistant $assistant): JsonResponse
    {
        $newAssistant = $assistant->replicate();
        $newAssistant->name = $assistant->name . ' (Copia)';
        $newAssistant->slug = $assistant->slug . '-copy-' . time();
        $newAssistant->is_default = false;
        $newAssistant->save();

        return response()->json([
            'success' => true,
            'message' => 'Asistente duplicado correctamente',
            'assistant' => $newAssistant,
        ], 201);
    }

    /**
     * Test an assistant configuration with conversation support
     */
    public function test(Request $request, Assistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'context' => 'nullable|array',
            'context.*.role' => 'required_with:context|string|in:user,assistant',
            'context.*.content' => 'required_with:context|string',
        ]);

        $testMessage = $validated['message'];
        $context = $validated['context'] ?? [];

        try {
            $settings = $assistant->toSettingsArray();

            // Build messages array
            $messages = [
                ['role' => 'system', 'content' => $settings['system_prompt']],
            ];

            // Add context messages (previous conversation)
            foreach ($context as $msg) {
                $messages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }

            // Add current user message
            $messages[] = ['role' => 'user', 'content' => $testMessage];

            // Check if using Assistants API (knowledge base)
            if ($assistant->usesAssistantsApi()) {
                $assistantService = app(\App\Services\OpenAIAssistantService::class);
                $result = $assistantService->sendMessageForTest($testMessage, $assistant, $context);

                return response()->json([
                    'success' => true,
                    'response' => $result['response'],
                    'usage' => $result['usage'] ?? null,
                    'model' => $settings['model'],
                    'used_knowledge_base' => true,
                ]);
            }

            // Regular Chat Completions API
            $model = $settings['model'];
            $params = [
                'model' => $model,
                'messages' => $messages,
                'top_p' => (float) ($settings['top_p'] ?? 1),
                'frequency_penalty' => (float) ($settings['frequency_penalty'] ?? 0),
                'presence_penalty' => (float) ($settings['presence_penalty'] ?? 0),
            ];

            // Newer models (GPT-5, o1 series) use max_completion_tokens instead of max_tokens
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
                // o1 and GPT-5 models don't support custom temperature (only default=1)
            } else {
                $params['max_tokens'] = (int) $settings['max_tokens'];
                $params['temperature'] = (float) $settings['temperature'];
            }

            $response = OpenAI::chat()->create($params);

            return response()->json([
                'success' => true,
                'response' => $response->choices[0]->message->content,
                'usage' => [
                    'prompt_tokens' => $response->usage->promptTokens ?? 0,
                    'completion_tokens' => $response->usage->completionTokens ?? 0,
                    'total_tokens' => $response->usage->totalTokens ?? 0,
                ],
                'model' => $settings['model'],
                'used_knowledge_base' => false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}

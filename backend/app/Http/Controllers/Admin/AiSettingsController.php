<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSettingsController extends Controller
{
    /**
     * Get all AI settings
     */
    public function index(): JsonResponse
    {
        $settings = AiSetting::all()->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $setting->value,
                'type' => $setting->type,
                'label' => $setting->label,
                'description' => $setting->description,
                'options' => $setting->options,
            ];
        });

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Update AI settings
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $item) {
            AiSetting::setValue($item['key'], $item['value']);
        }

        // Clear cache
        AiSetting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Configuración guardada correctamente',
        ]);
    }

    /**
     * Test the AI configuration
     */
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:500',
        ]);

        $testMessage = $validated['message'] ?? 'Hola, ¿cómo estás?';

        try {
            $settings = AiSetting::getAllValues();
            $model = $settings['model'] ?? 'gpt-4o-mini';

            $params = [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $settings['system_prompt'] ?? 'Eres un asistente útil.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $testMessage,
                    ],
                ],
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
                $params['max_completion_tokens'] = (int) ($settings['max_tokens'] ?? 2000);
                // o1 and GPT-5 models don't support custom temperature (only default=1)
            } else {
                $params['max_tokens'] = (int) ($settings['max_tokens'] ?? 2000);
                $params['temperature'] = (float) ($settings['temperature'] ?? 0.7);
            }

            $response = \OpenAI\Laravel\Facades\OpenAI::chat()->create($params);

            $content = $response->choices[0]->message->content;
            $tokensUsed = $response->usage->totalTokens ?? 0;

            return response()->json([
                'success' => true,
                'response' => $content,
                'tokens_used' => $tokensUsed,
                'model' => $settings['model'] ?? 'gpt-4o-mini',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al probar la configuración: ' . $e->getMessage(),
            ], 500);
        }
    }
}

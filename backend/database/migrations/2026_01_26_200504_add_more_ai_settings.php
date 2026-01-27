<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            // Sampling parameters
            [
                'key' => 'top_p',
                'value' => '1',
                'type' => 'number',
                'label' => 'Top P (Nucleus Sampling)',
                'description' => 'Alternativa a temperatura. Considera tokens con probabilidad acumulada top_p. (0.1 = solo top 10% tokens). Recomendado: 1',
                'options' => json_encode(['min' => 0, 'max' => 1, 'step' => 0.05]),
            ],
            [
                'key' => 'frequency_penalty',
                'value' => '0',
                'type' => 'number',
                'label' => 'Penalizacion de Frecuencia',
                'description' => 'Penaliza tokens que aparecen frecuentemente. Reduce repeticion. (-2 a 2, recomendado: 0 a 0.5)',
                'options' => json_encode(['min' => -2, 'max' => 2, 'step' => 0.1]),
            ],
            [
                'key' => 'presence_penalty',
                'value' => '0',
                'type' => 'number',
                'label' => 'Penalizacion de Presencia',
                'description' => 'Penaliza tokens que ya aparecieron. Aumenta variedad de temas. (-2 a 2, recomendado: 0 a 0.5)',
                'options' => json_encode(['min' => -2, 'max' => 2, 'step' => 0.1]),
            ],

            // Response format
            [
                'key' => 'response_format',
                'value' => 'text',
                'type' => 'select',
                'label' => 'Formato de Respuesta',
                'description' => 'Formato en que responde el modelo. JSON requiere instrucciones especificas en el prompt.',
                'options' => json_encode([
                    'text' => 'Texto (Normal)',
                    'json_object' => 'JSON (Estructurado)',
                ]),
            ],

            // Stop sequences
            [
                'key' => 'stop_sequences',
                'value' => '',
                'type' => 'text',
                'label' => 'Secuencias de Parada',
                'description' => 'Palabras o frases donde el modelo deja de generar. Separadas por coma. Ejemplo: "FIN,###,STOP"',
                'options' => null,
            ],

            // Seed for reproducibility
            [
                'key' => 'seed',
                'value' => '',
                'type' => 'string',
                'label' => 'Seed (Semilla)',
                'description' => 'Numero para obtener respuestas reproducibles. Dejar vacio para aleatorio.',
                'options' => null,
            ],

            // Streaming (for future implementation)
            [
                'key' => 'stream',
                'value' => 'false',
                'type' => 'select',
                'label' => 'Streaming',
                'description' => 'Envia la respuesta palabra por palabra (efecto de escritura). Requiere implementacion especial.',
                'options' => json_encode([
                    'false' => 'Desactivado (Respuesta completa)',
                    'true' => 'Activado (Palabra por palabra)',
                ]),
            ],

            // Logprobs
            [
                'key' => 'logprobs',
                'value' => 'false',
                'type' => 'select',
                'label' => 'Log Probabilities',
                'description' => 'Devuelve probabilidades de cada token. Util para analisis avanzado.',
                'options' => json_encode([
                    'false' => 'Desactivado',
                    'true' => 'Activado',
                ]),
            ],

            // User identifier
            [
                'key' => 'include_user_id',
                'value' => 'true',
                'type' => 'select',
                'label' => 'Incluir ID de Usuario',
                'description' => 'Envia el ID del usuario a OpenAI para monitoreo de abuso.',
                'options' => json_encode([
                    'true' => 'Si (Recomendado)',
                    'false' => 'No',
                ]),
            ],

            // N - number of completions
            [
                'key' => 'n_completions',
                'value' => '1',
                'type' => 'number',
                'label' => 'Numero de Respuestas',
                'description' => 'Cantidad de respuestas alternativas a generar. Mas = mas tokens consumidos.',
                'options' => json_encode(['min' => 1, 'max' => 5, 'step' => 1]),
            ],

            // Safety settings
            [
                'key' => 'filter_unsafe_content',
                'value' => 'true',
                'type' => 'select',
                'label' => 'Filtrar Contenido Inseguro',
                'description' => 'Indica al modelo que evite contenido inapropiado.',
                'options' => json_encode([
                    'true' => 'Activado (Recomendado)',
                    'false' => 'Desactivado',
                ]),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('ai_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ai_settings')->whereIn('key', [
            'top_p',
            'frequency_penalty',
            'presence_penalty',
            'response_format',
            'stop_sequences',
            'seed',
            'stream',
            'logprobs',
            'include_user_id',
            'n_completions',
            'filter_unsafe_content',
        ])->delete();
    }
};

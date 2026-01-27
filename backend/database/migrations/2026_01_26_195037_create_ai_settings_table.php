<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, text, number, boolean, select
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // For select type
            $table->timestamps();
        });

        // Insert default settings
        $this->seedDefaultSettings();
    }

    private function seedDefaultSettings(): void
    {
        $settings = [
            [
                'key' => 'model',
                'value' => 'gpt-4o-mini',
                'type' => 'select',
                'label' => 'Modelo de IA',
                'description' => 'Modelo de OpenAI a utilizar',
                'options' => json_encode([
                    'gpt-4o-mini' => 'GPT-4o Mini (Recomendado - Económico)',
                    'gpt-4o' => 'GPT-4o (Mejor calidad)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Muy económico)',
                ]),
            ],
            [
                'key' => 'system_prompt',
                'value' => "Eres un asistente experto en crear guiones para videos de YouTube, TikTok, Instagram Reels y otras plataformas.\n\nTu especialidad es ayudar a creadores de contenido a:\n- Crear guiones completos y estructurados\n- Desarrollar hooks irresistibles que capten la atención en los primeros 3 segundos\n- Escribir llamados a la acción (CTA) efectivos\n- Adaptar el tono y duración según la plataforma\n\nFORMATO DE RESPUESTA:\nCuando te pidan un guion, estructura así:\n\n🎬 HOOK (0-3 segundos)\n[Frase impactante para captar atención]\n\n📝 DESARROLLO\n[Contenido principal del video]\n\n✅ CTA (Call to Action)\n[Llamado a la acción final]\n\nREGLAS:\n- Siempre responde en español\n- Sé conciso y directo\n- Usa lenguaje conversacional y cercano\n- Adapta la duración al formato (TikTok: corto, YouTube: más extenso)\n- Si no especifican plataforma, pregunta antes de crear el guion",
                'type' => 'text',
                'label' => 'Instrucciones del Sistema',
                'description' => 'Las instrucciones que definen el comportamiento del asistente',
                'options' => null,
            ],
            [
                'key' => 'temperature',
                'value' => '0.7',
                'type' => 'number',
                'label' => 'Temperatura',
                'description' => 'Controla la creatividad (0 = determinístico, 1 = muy creativo). Recomendado: 0.7',
                'options' => json_encode(['min' => 0, 'max' => 1, 'step' => 0.1]),
            ],
            [
                'key' => 'max_tokens',
                'value' => '2000',
                'type' => 'number',
                'label' => 'Máximo de Tokens por Respuesta',
                'description' => 'Límite de tokens que puede usar el asistente en cada respuesta',
                'options' => json_encode(['min' => 100, 'max' => 4000, 'step' => 100]),
            ],
            [
                'key' => 'assistant_name',
                'value' => 'Gatales',
                'type' => 'string',
                'label' => 'Nombre del Asistente',
                'description' => 'El nombre que se muestra en el chat',
                'options' => null,
            ],
            [
                'key' => 'welcome_message',
                'value' => '¡Hola! Soy tu asistente de guiones. ¿Sobre qué tema te gustaría crear un video hoy?',
                'type' => 'text',
                'label' => 'Mensaje de Bienvenida',
                'description' => 'Mensaje que se muestra cuando el usuario abre el chat por primera vez',
                'options' => null,
            ],
            [
                'key' => 'context_messages',
                'value' => '10',
                'type' => 'number',
                'label' => 'Mensajes de Contexto',
                'description' => 'Cantidad de mensajes anteriores a incluir como contexto (más = más tokens)',
                'options' => json_encode(['min' => 1, 'max' => 50, 'step' => 1]),
            ],
        ];

        foreach ($settings as $setting) {
            \DB::table('ai_settings')->insert(array_merge($setting, [
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
        Schema::dropIfExists('ai_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assistants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            // OpenAI Configuration
            $table->string('model')->default('gpt-4o-mini');
            $table->text('system_prompt');
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->integer('max_tokens')->default(2000);
            $table->decimal('top_p', 3, 2)->default(1.00);
            $table->decimal('frequency_penalty', 3, 2)->default(0.00);
            $table->decimal('presence_penalty', 3, 2)->default(0.00);
            $table->string('response_format')->default('text');
            $table->text('stop_sequences')->nullable();
            $table->integer('seed')->nullable();
            $table->integer('n_completions')->default(1);
            $table->boolean('logprobs')->default(false);
            $table->boolean('stream')->default(false);

            // User-facing settings
            $table->string('assistant_display_name');
            $table->text('welcome_message');
            $table->integer('context_messages')->default(10);
            $table->boolean('filter_unsafe_content')->default(true);
            $table->boolean('include_user_id')->default(true);

            // Optional customization
            $table->string('avatar_url', 500)->nullable();

            $table->timestamps();

            $table->index('is_active');
            $table->index('is_default');
        });

        // Add assistant_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('assistant_id')->nullable()->after('openai_thread_id')
                ->constrained('assistants')->nullOnDelete();
        });

        // Create default assistant from existing ai_settings
        $this->createDefaultAssistant();
    }

    /**
     * Create the default assistant from existing ai_settings values.
     */
    private function createDefaultAssistant(): void
    {
        // Get all current ai_settings
        $settings = DB::table('ai_settings')->pluck('value', 'key')->toArray();

        // Create default assistant
        DB::table('assistants')->insert([
            'name' => 'Gatales',
            'slug' => 'gatales',
            'description' => 'Asistente original de Gatales para creacion de guiones de video',
            'is_active' => true,
            'is_default' => true,

            // OpenAI Configuration
            'model' => $settings['model'] ?? 'gpt-4o-mini',
            'system_prompt' => $settings['system_prompt'] ?? 'Eres un asistente de IA.',
            'temperature' => (float) ($settings['temperature'] ?? 0.7),
            'max_tokens' => (int) ($settings['max_tokens'] ?? 2000),
            'top_p' => (float) ($settings['top_p'] ?? 1.0),
            'frequency_penalty' => (float) ($settings['frequency_penalty'] ?? 0.0),
            'presence_penalty' => (float) ($settings['presence_penalty'] ?? 0.0),
            'response_format' => $settings['response_format'] ?? 'text',
            'stop_sequences' => $settings['stop_sequences'] ?? null,
            'seed' => !empty($settings['seed']) ? (int) $settings['seed'] : null,
            'n_completions' => (int) ($settings['n_completions'] ?? 1),
            'logprobs' => ($settings['logprobs'] ?? 'false') === 'true',
            'stream' => ($settings['stream'] ?? 'false') === 'true',

            // User-facing settings
            'assistant_display_name' => $settings['assistant_name'] ?? 'Gatales',
            'welcome_message' => $settings['welcome_message'] ?? 'Hola! Como puedo ayudarte hoy?',
            'context_messages' => (int) ($settings['context_messages'] ?? 10),
            'filter_unsafe_content' => ($settings['filter_unsafe_content'] ?? 'true') === 'true',
            'include_user_id' => ($settings['include_user_id'] ?? 'true') === 'true',

            'avatar_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['assistant_id']);
            $table->dropColumn('assistant_id');
        });

        Schema::dropIfExists('assistants');
    }
};

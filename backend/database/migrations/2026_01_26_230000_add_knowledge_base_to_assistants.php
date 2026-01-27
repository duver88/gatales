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
        // Add OpenAI Assistants API fields to assistants table
        Schema::table('assistants', function (Blueprint $table) {
            $table->string('openai_assistant_id')->nullable()->after('avatar_url');
            $table->string('openai_vector_store_id')->nullable()->after('openai_assistant_id');
            $table->boolean('use_knowledge_base')->default(false)->after('openai_vector_store_id');
        });

        // Create table for storing file references
        Schema::create('assistant_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();

            // OpenAI file reference
            $table->string('openai_file_id');

            // File metadata
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->string('status')->default('processing'); // processing, ready, failed
            $table->text('error_message')->nullable();

            // Local storage (optional, for backup/display)
            $table->string('storage_path')->nullable();

            $table->timestamps();

            $table->index('assistant_id');
            $table->index('openai_file_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_files');

        Schema::table('assistants', function (Blueprint $table) {
            $table->dropColumn(['openai_assistant_id', 'openai_vector_store_id', 'use_knowledge_base']);
        });
    }
};

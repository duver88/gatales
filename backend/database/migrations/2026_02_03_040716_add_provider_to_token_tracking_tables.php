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
        // Add provider to token_usage table
        Schema::table('token_usage', function (Blueprint $table) {
            $table->string('provider', 20)->default('openai')->after('user_id');
            $table->index('provider');
            $table->index(['provider', 'date']);
        });

        // Add provider to messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->string('provider', 20)->default('openai')->after('conversation_id');
        });

        // Create admin_token_usage table for tracking admin test usage
        Schema::create('admin_token_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->foreignId('assistant_id')->nullable()->constrained('assistants')->onDelete('set null');
            $table->string('provider', 20)->default('openai');
            $table->integer('tokens_input')->default(0);
            $table->integer('tokens_output')->default(0);
            $table->date('date');
            $table->timestamp('created_at')->useCurrent();

            $table->index('admin_id');
            $table->index('provider');
            $table->index('date');
            $table->index(['admin_id', 'date']);
            $table->index(['provider', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('token_usage', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropIndex(['provider', 'date']);
            $table->dropColumn('provider');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('provider');
        });

        Schema::dropIfExists('admin_token_usage');
    }
};

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
        // Create conversations table
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('cascade');
            $table->foreignId('assistant_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title')->nullable();
            $table->string('openai_thread_id')->nullable();
            $table->enum('type', ['user_chat', 'admin_test'])->default('user_chat');
            $table->integer('total_tokens_input')->default(0);
            $table->integer('total_tokens_output')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'type', 'deleted_at']);
            $table->index(['admin_id', 'type', 'deleted_at']);
            $table->index('last_message_at');
            $table->index('archived_at');
        });

        // Add conversation_id to messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('user_id')
                ->constrained()->onDelete('cascade');
            $table->index('conversation_id');
        });

        // Migrate existing messages to conversations
        $this->migrateExistingMessages();
    }

    /**
     * Migrate existing messages to conversations.
     */
    private function migrateExistingMessages(): void
    {
        // Get all users with messages
        $usersWithMessages = DB::table('messages')
            ->select('user_id')
            ->distinct()
            ->get();

        foreach ($usersWithMessages as $userData) {
            // Get user info
            $user = DB::table('users')->where('id', $userData->user_id)->first();
            if (!$user) {
                continue;
            }

            // Get first message for title
            $firstMessage = DB::table('messages')
                ->where('user_id', $userData->user_id)
                ->where('role', 'user')
                ->orderBy('created_at', 'asc')
                ->first();

            // Calculate token stats
            $tokenStats = DB::table('messages')
                ->where('user_id', $userData->user_id)
                ->selectRaw('SUM(tokens_input) as total_input, SUM(tokens_output) as total_output, MAX(created_at) as last_message')
                ->first();

            // Generate title from first message (max 50 chars)
            $title = 'Conversacion anterior';
            if ($firstMessage) {
                $title = mb_substr($firstMessage->content, 0, 50);
                if (mb_strlen($firstMessage->content) > 50) {
                    $title .= '...';
                }
            }

            // Create conversation
            $conversationId = DB::table('conversations')->insertGetId([
                'user_id' => $userData->user_id,
                'admin_id' => null,
                'assistant_id' => $user->assistant_id,
                'title' => $title,
                'openai_thread_id' => $user->openai_thread_id,
                'type' => 'user_chat',
                'total_tokens_input' => $tokenStats->total_input ?? 0,
                'total_tokens_output' => $tokenStats->total_output ?? 0,
                'last_message_at' => $tokenStats->last_message,
                'archived_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update all messages to belong to this conversation
            DB::table('messages')
                ->where('user_id', $userData->user_id)
                ->whereNull('conversation_id')
                ->update(['conversation_id' => $conversationId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn('conversation_id');
        });

        Schema::dropIfExists('conversations');
    }
};

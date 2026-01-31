<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds indexes for commonly queried columns to improve performance.
     */
    public function up(): void
    {
        // Conversations table indexes
        Schema::table('conversations', function (Blueprint $table) {
            // Index for user's non-archived conversations sorted by last message
            $table->index(['user_id', 'archived_at', 'last_message_at'], 'conversations_user_active_idx');
            // Index for type filtering
            $table->index(['type', 'user_id'], 'conversations_type_user_idx');
        });

        // Messages table indexes
        Schema::table('messages', function (Blueprint $table) {
            // Index for conversation messages sorted by date
            $table->index(['conversation_id', 'created_at'], 'messages_conversation_date_idx');
            // Index for user's messages
            $table->index(['user_id', 'created_at'], 'messages_user_date_idx');
        });

        // Subscriptions table indexes
        Schema::table('subscriptions', function (Blueprint $table) {
            // Index for active subscriptions
            $table->index(['user_id', 'status', 'ends_at'], 'subscriptions_user_active_idx');
        });

        // Token usage table indexes
        Schema::table('token_usage', function (Blueprint $table) {
            // Index for user's token usage sorted by date
            $table->index(['user_id', 'created_at'], 'token_usage_user_date_idx');
        });

        // Webhook logs table indexes
        Schema::table('webhook_logs', function (Blueprint $table) {
            // Index for filtering by event type and date
            $table->index(['event_type', 'created_at'], 'webhook_logs_type_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_user_active_idx');
            $table->dropIndex('conversations_type_user_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_date_idx');
            $table->dropIndex('messages_user_date_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_user_active_idx');
        });

        Schema::table('token_usage', function (Blueprint $table) {
            $table->dropIndex('token_usage_user_date_idx');
        });

        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropIndex('webhook_logs_type_date_idx');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Optimized indexes for 2000+ users base.
     */
    public function up(): void
    {
        // Add composite index for monthly aggregations by user (used in getUsersUsageBreakdown)
        Schema::table('token_usage', function (Blueprint $table) {
            // Index for: WHERE date BETWEEN x AND y GROUP BY user_id ORDER BY total
            $table->index(['date', 'user_id'], 'token_usage_date_user_idx');
        });

        // Add composite index for admin_token_usage monthly queries
        Schema::table('admin_token_usage', function (Blueprint $table) {
            $table->index(['date', 'admin_id'], 'admin_token_usage_date_admin_idx');
        });

        // Add composite index for conversation queries (user filtering + type + archived status)
        Schema::table('conversations', function (Blueprint $table) {
            // Index for: WHERE user_id = x AND type = y AND archived_at IS NULL
            $table->index(['user_id', 'type', 'archived_at'], 'conversations_user_type_archived_idx');
        });

        // Add composite index for fast message loading by conversation
        Schema::table('messages', function (Blueprint $table) {
            // Index for: WHERE conversation_id = x ORDER BY created_at
            $table->index(['conversation_id', 'created_at'], 'messages_conversation_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('token_usage', function (Blueprint $table) {
            $table->dropIndex('token_usage_date_user_idx');
        });

        Schema::table('admin_token_usage', function (Blueprint $table) {
            $table->dropIndex('admin_token_usage_date_admin_idx');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_user_type_archived_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_created_idx');
        });
    }
};

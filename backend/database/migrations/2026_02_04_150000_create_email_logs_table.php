<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('subject');
            $table->string('type')->default('general'); // welcome, password_reset, notification, etc.
            $table->string('status')->default('pending'); // pending, sent, delivered, bounced, failed, complained
            $table->string('provider')->nullable(); // smtp, mailgun, ses, etc.
            $table->string('message_id')->nullable(); // Provider message ID
            $table->text('error_message')->nullable();
            $table->string('bounce_type')->nullable(); // hard, soft
            $table->string('bounce_subtype')->nullable(); // undetermined, general, suppressed, etc.
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->json('metadata')->nullable(); // Additional provider data
            $table->timestamps();

            // Indexes for quick lookups
            $table->index('status');
            $table->index('type');
            $table->index('to_email');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

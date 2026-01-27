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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password')->nullable(); // Null until user sets it
            $table->string('name');
            $table->enum('status', ['pending', 'active', 'inactive', 'suspended'])->default('pending');
            $table->string('openai_thread_id')->nullable();
            $table->integer('tokens_balance')->default(0);
            $table->integer('tokens_used_month')->default(0);
            $table->string('password_token')->nullable();
            $table->timestamp('password_token_expires_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index('status');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

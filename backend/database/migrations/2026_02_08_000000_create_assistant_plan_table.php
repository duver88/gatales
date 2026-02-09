<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('assistant_id')->constrained('assistants')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'assistant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_plan');
    }
};

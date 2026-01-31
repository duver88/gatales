<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds reasoning_effort column for GPT-5 models.
     * Valid values: none, minimal, low, medium, high, xhigh
     * - none/minimal: Fastest response, minimal reasoning tokens
     * - low/medium: Balanced speed and quality
     * - high/xhigh: Best quality, more reasoning tokens
     */
    public function up(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->string('reasoning_effort', 20)->default('minimal')
                ->after('model')
                ->comment('GPT-5 reasoning effort: none, minimal, low, medium, high, xhigh');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->dropColumn('reasoning_effort');
        });
    }
};

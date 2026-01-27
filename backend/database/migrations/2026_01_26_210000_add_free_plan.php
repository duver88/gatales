<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('plans')->insert([
            'name' => 'Plan Gratuito',
            'slug' => 'free',
            'tokens_monthly' => 0,
            'price' => 0.00,
            'hotmart_product_id' => null,
            'features' => json_encode([
                'Sin acceso al asistente',
                'Visualiza el historial',
                'Actualiza para desbloquear',
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('plans')->where('slug', 'free')->delete();
    }
};

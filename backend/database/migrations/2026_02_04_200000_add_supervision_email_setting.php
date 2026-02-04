<?php

use App\Models\AiSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add supervision email setting if it doesn't exist
        if (!DB::table('ai_settings')->where('key', 'supervision_email')->exists()) {
            DB::table('ai_settings')->insert([
                'key' => 'supervision_email',
                'value' => '',
                'type' => 'string',
                'label' => 'Email de Supervision',
                'description' => 'Todos los correos enviados por la aplicacion se copiaran a esta direccion para supervision. Dejar vacio para desactivar.',
                'options' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ai_settings')->where('key', 'supervision_email')->delete();
    }
};

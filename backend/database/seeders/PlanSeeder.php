<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Básico',
                'slug' => 'basico',
                'tokens_monthly' => 100000, // 100K tokens
                'price' => 19.00,
                'hotmart_product_id' => null, // Set this in production
                'features' => [
                    'Acceso al asistente de guiones',
                    '100,000 tokens mensuales',
                    'Historial de conversaciones',
                    'Soporte por email',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'tokens_monthly' => 300000, // 300K tokens
                'price' => 49.00,
                'hotmart_product_id' => null, // Set this in production
                'features' => [
                    'Acceso al asistente de guiones',
                    '300,000 tokens mensuales',
                    'Historial de conversaciones',
                    'Soporte prioritario',
                    'Guiones más largos y detallados',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Pro Max',
                'slug' => 'pro-max',
                'tokens_monthly' => 1000000, // 1M tokens
                'price' => 99.00,
                'hotmart_product_id' => null, // Set this in production
                'features' => [
                    'Acceso al asistente de guiones',
                    '1,000,000 tokens mensuales',
                    'Historial de conversaciones',
                    'Soporte VIP',
                    'Guiones ilimitados',
                    'Acceso anticipado a nuevas funciones',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}

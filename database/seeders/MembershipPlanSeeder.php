<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

class MembershipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Emprendedor Básico',
                'slug' => 'basico',
                'description' => 'Ideal para empezar a mostrar tus productos en Mercova.',
                'price' => 0,
                'billing_period' => 'mensual',
                'max_listings' => 5,
                'features' => [
                    'Hasta 5 publicaciones activas',
                    'Perfil de emprendimiento visible en el directorio',
                    'Soporte por correo',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Emprendedor Pro',
                'slug' => 'pro',
                'description' => 'Para emprendimientos que quieren crecer sus ventas.',
                'price' => 29900,
                'billing_period' => 'mensual',
                'max_listings' => 30,
                'features' => [
                    'Hasta 30 publicaciones activas',
                    'Insignia de emprendimiento verificado',
                    'Estadísticas básicas de tus publicaciones',
                    'Soporte prioritario',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Emprendedor Premium',
                'slug' => 'premium',
                'description' => 'Publicaciones ilimitadas y máxima visibilidad en Mercova.',
                'price' => 59900,
                'billing_period' => 'mensual',
                'max_listings' => null,
                'features' => [
                    'Publicaciones ilimitadas',
                    'Destacado en la portada de Mercova',
                    'Insignia premium en tu perfil',
                    'Soporte prioritario 24/7',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            MembershipPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}

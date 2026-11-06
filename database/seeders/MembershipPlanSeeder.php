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
                'name' => 'Despegue',
                'slug' => 'basico',
                'description' => 'Gratis para siempre. Ideal para dar tus primeros pasos en Mercova.',
                'price' => 0,
                'billing_period' => 'mensual',
                'max_listings' => 5,
                'tier' => 0,
                'monthly_listing_limit' => 5,
                'monthly_request_limit' => 10,
                'featured_listings_limit' => 0,
                'commission_rate' => null,
                'has_verified_badge' => false,
                'has_advanced_stats' => false,
                'has_realtime_analytics' => false,
                'features' => [
                    'Acceso completo al catálogo y compras estándar',
                    'Hasta 5 publicaciones nuevas al mes (5 activas a la vez)',
                    'Hasta 10 solicitudes de alquiler o reserva al mes',
                    'Perfil de emprendimiento visible en el directorio',
                    'Domicilios en horario estándar',
                    'Soporte por correo',
                ],
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'name' => 'Impulso',
                'slug' => 'pro',
                'description' => 'Para emprendimientos listos para vender más y llegar más rápido.',
                'price' => 29900,
                'billing_period' => 'mensual',
                'max_listings' => 30,
                'tier' => 1,
                'monthly_listing_limit' => 30,
                'monthly_request_limit' => null,
                'featured_listings_limit' => 5,
                'commission_rate' => null,
                'has_verified_badge' => true,
                'has_advanced_stats' => true,
                'has_realtime_analytics' => false,
                'is_default' => false,
                'features' => [
                    'Hasta 30 publicaciones activas',
                    'Hasta 5 publicaciones destacadas',
                    'Solicitudes de alquiler y reserva ilimitadas',
                    'Insignia de emprendimiento verificado',
                    'Mayor visibilidad en búsquedas y categorías del marketplace',
                    'Domicilios con despacho prioritario (entregas más rápidas)',
                    'Difusión en redes sociales de Mercova 2 veces al mes',
                    'Estadísticas de visitas y ventas',
                    'Soporte prioritario por chat',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Élite',
                'slug' => 'premium',
                'description' => 'Publicaciones ilimitadas, marketing incluido y la máxima velocidad de entrega.',
                'price' => 59900,
                'billing_period' => 'mensual',
                'max_listings' => null,
                'tier' => 2,
                'monthly_listing_limit' => null,
                'monthly_request_limit' => null,
                'featured_listings_limit' => null,
                'commission_rate' => 0.04,
                'has_verified_badge' => true,
                'has_advanced_stats' => true,
                'has_realtime_analytics' => true,
                'is_default' => false,
                'features' => [
                    'Publicaciones y destacados ilimitados',
                    'Comisión reducida del 4% por venta',
                    'Analítica en tiempo real',
                    'Destacado permanente en la portada de Mercova',
                    'Insignia Élite en tu perfil y publicaciones',
                    'Domicilios exprés con la máxima prioridad de despacho',
                    'Campaña de marketing mensual en redes sociales y newsletter',
                    'Estadísticas avanzadas de alcance y conversión',
                    'Gestor de cuenta dedicado y soporte 24/7',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            MembershipPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}

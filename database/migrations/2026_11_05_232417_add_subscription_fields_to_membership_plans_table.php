<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte cada plan de membresía en un plan de suscripción de usuario:
 * cada columna es una capacidad que el backend hace cumplir (no texto de
 * marketing, eso sigue en "features").
 *
 * En los límites, null significa "ilimitado"; en commission_rate, null
 * significa "usar la comisión general de config('mercova.commission.rate')".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('tier')->default(0)->after('slug');
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->unsignedInteger('monthly_listing_limit')->nullable()->after('max_listings');
            $table->unsignedInteger('monthly_request_limit')->nullable()->after('monthly_listing_limit');
            $table->unsignedInteger('featured_listings_limit')->nullable()->default(0)->after('monthly_request_limit');
            $table->decimal('commission_rate', 5, 4)->nullable()->after('featured_listings_limit');
            $table->boolean('has_verified_badge')->default(false)->after('commission_rate');
            $table->boolean('has_advanced_stats')->default(false)->after('has_verified_badge');
            $table->boolean('has_realtime_analytics')->default(false)->after('has_advanced_stats');
        });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn([
                'tier',
                'is_default',
                'monthly_listing_limit',
                'monthly_request_limit',
                'featured_listings_limit',
                'commission_rate',
                'has_verified_badge',
                'has_advanced_stats',
                'has_realtime_analytics',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador de consumo mensual por usuario (period = "2026-11"). Se usa un
 * contador en vez de contar filas de products/rentals porque así borrar una
 * publicación no "devuelve" cupo y el incremento puede hacerse de forma
 * atómica (ver App\Services\SubscriptionService::reserveQuota()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->unsignedInteger('listings_used')->default(0);
            $table->unsignedInteger('requests_used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_usages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada intento de cobro de un plan de pago. La suscripción NO se activa al
 * crear esta fila: solo cuando el webhook de Wompi confirma el pago
 * (status = approved), y entonces se enlaza en subscription_id.
 *
 * change_type: new | upgrade | renewal | downgrade (ver SubscriptionCheckoutService).
 * status: pending | approved | declined | voided | error | amount_mismatch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('change_type');
            $table->string('reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('credit_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('COP');
            $table->string('status')->default('pending')->index();
            $table->string('transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};

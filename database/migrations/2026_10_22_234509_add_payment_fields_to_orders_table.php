<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Varios pedidos de un mismo checkout (uno por vendedor) comparten
            // la misma referencia porque Wompi cobra una sola vez por carrito:
            // no es única por fila, se busca por igualdad en el webhook.
            $table->string('payment_reference')->nullable()->after('payment_method')->index();
            // pending: aún no se intentó pagar o el widget no ha confirmado nada.
            // approved/declined/voided/error: estados que puede reportar Wompi.
            $table->string('payment_status')->default('pending')->after('payment_reference');
            $table->string('payment_transaction_id')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_reference', 'payment_status', 'payment_transaction_id', 'paid_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('commission_amount', 10, 2)->nullable()->after('total');
            $table->decimal('net_amount', 10, 2)->nullable()->after('commission_amount');
        });

        $rate = (float) config('mercova.commission.rate', 0.06);

        DB::table('orders')->whereNull('commission_amount')->get(['id', 'total'])->each(function ($order) use ($rate) {
            $total = (float) $order->total;
            DB::table('orders')->where('id', $order->id)->update([
                'commission_amount' => round($total * $rate, 2),
                'net_amount' => round($total * (1 - $rate), 2),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'net_amount']);
        });
    }
};

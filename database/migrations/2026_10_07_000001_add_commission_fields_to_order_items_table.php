<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('commission_rate', 6, 4)->nullable()->after('total');
            $table->decimal('commission_amount', 10, 2)->nullable()->after('commission_rate');
            $table->decimal('net_amount', 10, 2)->nullable()->after('commission_amount');
        });

        $rate = (float) config('mercova.commission.rate', 0.06);

        DB::table('order_items')->whereNull('commission_rate')->get(['id', 'total'])->each(function ($item) use ($rate) {
            $total = (float) $item->total;
            DB::table('order_items')->where('id', $item->id)->update([
                'commission_rate' => $rate,
                'commission_amount' => round($total * $rate, 2),
                'net_amount' => round($total * (1 - $rate), 2),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'commission_amount', 'net_amount']);
        });
    }
};

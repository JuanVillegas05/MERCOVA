<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('commission_rate', 6, 4)->nullable()->after('price');
            $table->decimal('net_amount', 10, 2)->nullable()->after('commission_rate');
        });

        $rate = (float) config('mercova.commission.rate', 0.06);

        DB::table('services')->whereNull('commission_rate')->get(['id', 'price'])->each(function ($service) use ($rate) {
            DB::table('services')->where('id', $service->id)->update([
                'commission_rate' => $rate,
                'net_amount' => round(((float) $service->price) * (1 - $rate), 2),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'net_amount']);
        });
    }
};

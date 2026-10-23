<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_per_day', 10, 2)->nullable()->after('price');
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('price_per_day');
        });

        DB::table('products')
            ->where('transaction_type', 'rent')
            ->whereNull('price_per_day')
            ->update(['price_per_day' => DB::raw('price')]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_per_day', 'deposit_amount']);
        });
    }
};

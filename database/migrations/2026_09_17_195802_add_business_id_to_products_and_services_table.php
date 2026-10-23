<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('seller_id')->constrained()->nullOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('seller_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });
    }
};

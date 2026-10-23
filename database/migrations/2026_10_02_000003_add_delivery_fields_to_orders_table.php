<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->after('notes');
            $table->string('recipient_phone')->nullable()->after('recipient_name');
            $table->string('address')->nullable()->after('recipient_phone');
            $table->string('city')->nullable()->after('address');
            $table->string('department')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['recipient_name', 'recipient_phone', 'address', 'city', 'department']);
        });
    }
};

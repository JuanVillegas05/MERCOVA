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
        Schema::table('users', function (Blueprint $table) {
            // "google" | "apple" | null (cuenta creada con correo/contraseña).
            // Una cuenta de correo/contraseña puede luego iniciar sesión con
            // Google/Apple si el correo coincide: en ese caso se completan
            // estas dos columnas sin crear una cuenta duplicada.
            $table->string('oauth_provider')->nullable()->after('password');
            $table->string('oauth_provider_id')->nullable()->after('oauth_provider');
            $table->unique(['oauth_provider', 'oauth_provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['oauth_provider', 'oauth_provider_id']);
            $table->dropColumn(['oauth_provider', 'oauth_provider_id']);
        });
    }
};

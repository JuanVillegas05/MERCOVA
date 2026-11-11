<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las cuentas eliminadas desde el panel admin se marcan con deleted_at en vez
 * de borrarse: todas las FKs hacia users son cascadeOnDelete, y un borrado
 * físico arrastraría pedidos, ventas y pagos que también pertenecen a otros
 * usuarios. Ver App\Services\UserAccountDeletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Elimina una cuenta desde el panel de administración.
 *
 * No se hace un borrado físico: todas las FKs hacia `users` son
 * cascadeOnDelete, así que borrar a un vendedor borraría los pedidos de sus
 * compradores (y viceversa), sus pagos y las métricas de ingresos. En su lugar:
 *
 * 1. Se revocan todos sus tokens (pierde el acceso al instante).
 * 2. Se anonimizan sus datos personales y se libera su correo, para que
 *    pueda volver a registrarse con él.
 * 3. Se marca como eliminada (SoftDeletes): desaparece de listados, login y
 *    autenticación, pero los pedidos/reseñas de otros siguen mostrando
 *    "Usuario eliminado".
 * 4. Sus publicaciones se archivan, su emprendimiento se suspende y sus
 *    pedidos sin pagar se cancelan reponiendo stock.
 */
class UserAccountDeletion
{
    public const DELETED_NAME = 'Usuario eliminado';

    /**
     * Motivo por el que la cuenta no se puede eliminar todavía, o null si se
     * puede. Una cuenta con compromisos en curso (dinero ya cobrado o un
     * servicio/alquiler pactado) debe cerrarse primero desde el panel.
     */
    public function blockingReason(User $user): ?string
    {
        if ($user->role === 'admin') {
            return 'No puedes eliminar una cuenta de administrador.';
        }

        $hasPaidOrdersInProgress = Order::query()
            ->where(fn (Builder $query) => $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->where('payment_status', 'approved')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();

        if ($hasPaidOrdersInProgress) {
            return 'El usuario tiene pedidos pagados sin completar (como comprador o vendedor). Complétalos o cancélalos antes de eliminar la cuenta.';
        }

        $hasActiveRentals = Rental::query()
            ->whereIn('status', Rental::ACTIVE_STATUSES)
            ->where(fn (Builder $query) => $query->where('renter_id', $user->id)
                ->orWhereHas('product', fn (Builder $product) => $product->where('seller_id', $user->id)))
            ->exists();

        if ($hasActiveRentals) {
            return 'El usuario tiene alquileres pendientes o activos. Ciérralos antes de eliminar la cuenta.';
        }

        $hasOpenBookings = Booking::query()
            ->whereIn('status', ['pending', 'processing'])
            ->where(fn (Builder $query) => $query->where('buyer_id', $user->id)
                ->orWhereHas('service', fn (Builder $service) => $service->where('seller_id', $user->id)))
            ->exists();

        if ($hasOpenBookings) {
            return 'El usuario tiene reservas de servicios pendientes o en curso. Ciérralas antes de eliminar la cuenta.';
        }

        return null;
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $this->cancelUnpaidOrders($user);

            $user->products()->update(['status' => 'archived']);
            $user->services()->update(['status' => 'archived']);
            $user->business()->update(['status' => 'suspended']);
            $user->tokens()->delete();

            $user->forceFill([
                'name' => self::DELETED_NAME,
                // Único (la columna lo exige) y en un dominio reservado que
                // nunca recibe correo: libera el original para re-registrarse.
                'email' => "deleted-{$user->id}-".Str::lower(Str::random(8)).'@deleted.invalid',
                'phone' => null,
                'bio' => null,
                'avatar_url' => null,
                'oauth_provider' => null,
                'oauth_provider_id' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'is_active' => false,
            ])->save();

            $user->delete();
        });
    }

    /**
     * Pedidos aún sin pagar del usuario (como comprador o vendedor): ya no se
     * van a completar, así que se cancelan y se repone su stock.
     */
    private function cancelUnpaidOrders(User $user): void
    {
        $orders = Order::query()
            ->where(fn (Builder $query) => $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->where('status', 'pending')
            ->where('payment_status', '!=', 'approved')
            ->lockForUpdate()
            ->get();

        foreach ($orders as $order) {
            $order->update(['status' => 'cancelled']);
            $order->restoreProductStock();
        }
    }
}

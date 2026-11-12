<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('orders:cancel-stale')]
#[Description('Cancela pedidos sin pagar que superaron el tiempo de expiracion y repone su stock')]
class CancelStaleOrders extends Command
{
    /**
     * Pedidos "pending" sin pago aprobado (el comprador abandono el
     * checkout, o su ultimo intento fue rechazado y no reintento) cuyo stock
     * quedaria bloqueado para siempre si nadie los cancela.
     */
    public function handle(): void
    {
        $cutoff = now()->subHours((int) config('mercova.order_expiry_hours', 48));

        $staleOrderIds = Order::query()
            ->where('status', 'pending')
            ->whereIn('payment_status', PaymentService::RETRYABLE_ORDER_PAYMENT_STATUSES)
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        foreach ($staleOrderIds as $orderId) {
            DB::transaction(function () use ($orderId) {
                $order = Order::query()->lockForUpdate()->find($orderId);

                if (! $order || $order->status !== 'pending' || ! in_array($order->payment_status, PaymentService::RETRYABLE_ORDER_PAYMENT_STATUSES, true)) {
                    return;
                }

                $order->update(['status' => 'cancelled', 'payment_status' => 'expired']);
                $order->restoreProductStock();
            });
        }

        $this->info("Pedidos expirados cancelados: {$staleOrderIds->count()}.");
    }
}

<?php

use App\Models\SubscriptionPayment;
use App\Services\MercadoPagoPaymentService;
use App\Services\SubscriptionCheckoutService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Red de seguridad para webhooks de Mercado Pago perdidos o demorados:
 * consulta los pagos de planes aún abiertos de los últimos 2 días y, si
 * Mercado Pago ya los resolvió, activa el plan o registra el rechazo. Los
 * abiertos más viejos se dan por abandonados ("expired").
 */
Artisan::command('subscriptions:reconcile-payments', function (SubscriptionCheckoutService $checkout) {
    $pending = SubscriptionPayment::query()
        ->whereIn('status', MercadoPagoPaymentService::RETRYABLE_SUBSCRIPTION_PAYMENT_STATUSES)
        ->whereBetween('created_at', [now()->subDays(2), now()->subMinutes(2)])
        ->get();

    $pending->each(fn (SubscriptionPayment $payment) => $checkout->reconcile($payment));

    $expired = SubscriptionPayment::query()
        ->whereIn('status', MercadoPagoPaymentService::RETRYABLE_SUBSCRIPTION_PAYMENT_STATUSES)
        ->where('created_at', '<', now()->subDays(2))
        ->update(['status' => SubscriptionPayment::STATUS_EXPIRED]);

    $this->info("Pagos revisados: {$pending->count()}. Pagos expirados: {$expired}.");
})->purpose('Concilia con Mercado Pago los pagos de planes que siguen pendientes');

Schedule::command('orders:cancel-stale')->hourly();
Schedule::command('subscriptions:reconcile-payments')->everyTenMinutes()->withoutOverlapping();

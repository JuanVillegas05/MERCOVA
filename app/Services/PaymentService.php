<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cobro de una referencia nuestra (pedido, carrito o plan) con Wompi y
 * aplicación de su resultado.
 *
 * Regla de estados: en Wompi una misma referencia puede tener varios
 * intentos (tarjeta rechazada y luego aprobada, PSE abandonado y
 * reintentado). Por eso:
 * - El último intento se refleja en `payment_status` / `status` del pago,
 *   pero un rechazo NO cancela el pedido ni repone stock: el comprador puede
 *   reintentar. Los abandonados los cancela `orders:cancel-stale`.
 * - "approved" es definitivo: ningún evento posterior lo degrada.
 */
class PaymentService
{
    /** Estados de pago de un pedido que aún admiten un nuevo intento. */
    public const RETRYABLE_ORDER_PAYMENT_STATUSES = ['pending', 'declined', 'voided'];

    /** Estados de un pago de plan que aún admiten un nuevo intento. */
    public const RETRYABLE_SUBSCRIPTION_PAYMENT_STATUSES = [SubscriptionPayment::STATUS_PENDING, 'declined', 'voided'];

    public function __construct(
        private readonly WompiService $wompi,
        private readonly SubscriptionCheckoutService $subscriptionCheckout,
    ) {}

    /**
     * Lo que se va a cobrar por una referencia del usuario, calculado siempre
     * desde la base de datos (el monto nunca se acepta del frontend). 404 si
     * la referencia no es del usuario o ya no se puede pagar.
     *
     * @return array{reference: string, amount: float, description: string}
     */
    public function payableFor(User $user, string $reference): array
    {
        $payable = $this->findPayable($user, $reference);

        abort_if($payable === null, 404, 'No encontramos un pago pendiente con esa referencia.');

        return $payable;
    }

    /**
     * Igual que payableFor(), pero devuelve null en vez de abortar. Un
     * carrito con varios vendedores comparte referencia: se cobra el total
     * de todos sus pedidos, nunca el de uno solo.
     *
     * @return array{reference: string, amount: float, description: string}|null
     */
    public function findPayable(User $user, string $reference): ?array
    {
        $orders = Order::query()->where('payment_reference', $reference)->get();

        if ($orders->isNotEmpty()) {
            $payable = $orders->every(fn (Order $order) => $order->buyer_id === $user->id
                && $order->status === 'pending'
                && in_array($order->payment_status, self::RETRYABLE_ORDER_PAYMENT_STATUSES, true));

            if (! $payable) {
                return null;
            }

            return [
                'reference' => $reference,
                'amount' => round((float) $orders->sum('total'), 2),
                'description' => 'Compra en Mercova (pedido '.$orders->pluck('id')->map(fn ($id) => "#{$id}")->join(', ').')',
            ];
        }

        $subscriptionPayment = SubscriptionPayment::query()
            ->with('plan')
            ->where('reference', $reference)
            ->where('user_id', $user->id)
            ->whereIn('status', self::RETRYABLE_SUBSCRIPTION_PAYMENT_STATUSES)
            // Pasados 2 días el precio cotizado puede estar desactualizado:
            // el usuario debe volver a iniciar el checkout del plan.
            ->where('created_at', '>=', now()->subDays(2))
            ->first();

        if ($subscriptionPayment === null) {
            return null;
        }

        return [
            'reference' => $reference,
            'amount' => round((float) $subscriptionPayment->amount, 2),
            'description' => 'Plan '.($subscriptionPayment->plan?->name ?? 'Mercova'),
        ];
    }

    /**
     * Si la referencia (pagada o no) pertenece al usuario. Se usa para no
     * revelar pagos ajenos al consultar su estado.
     */
    public function belongsTo(User $user, string $reference): bool
    {
        return Order::query()->where('payment_reference', $reference)->where('buyer_id', $user->id)->exists()
            || SubscriptionPayment::query()->where('reference', $reference)->where('user_id', $user->id)->exists();
    }

    /**
     * Enlace del Web Checkout de Wompi para una referencia del usuario, por
     * el monto que dice la base de datos. Null si ya no se puede pagar.
     */
    public function checkoutUrlFor(User $user, string $reference, string $redirectUrl): ?string
    {
        $payable = $this->findPayable($user, $reference);

        if ($payable === null) {
            return null;
        }

        return $this->wompi->checkoutUrl($payable['reference'], $payable['amount'], $redirectUrl, $user->email);
    }

    /**
     * Aplica una transacción de Wompi (obtenida de su API) al pedido/carrito
     * o al pago de plan de su referencia. Es idempotente: Wompi reintenta
     * los eventos.
     *
     * @param  array<string, mixed>  $transaction
     */
    public function apply(array $transaction): void
    {
        $reference = (string) ($transaction['reference'] ?? '');

        if ($reference === '') {
            return;
        }

        $status = $this->wompi->internalStatus($transaction['status'] ?? null);
        $transactionId = isset($transaction['id']) ? (string) $transaction['id'] : null;
        $amount = isset($transaction['amount_in_cents']) && ($transaction['currency'] ?? 'COP') === 'COP'
            ? ((int) $transaction['amount_in_cents']) / 100
            : null;

        $subscriptionPayment = SubscriptionPayment::query()->where('reference', $reference)->first();

        if ($subscriptionPayment) {
            $this->subscriptionCheckout->handlePaymentEvent($subscriptionPayment, $status, $transactionId, $amount);

            return;
        }

        $orderIds = Order::query()->where('payment_reference', $reference)->pluck('id');

        if ($orderIds->isEmpty()) {
            Log::warning("Transacción de Wompi {$transactionId} para una referencia que no existe: {$reference}");

            return;
        }

        $this->applyToOrders($orderIds, $reference, $status, $transactionId, $amount);
    }

    /**
     * @param  Collection<int, int>  $orderIds
     */
    private function applyToOrders(Collection $orderIds, string $reference, string $status, ?string $transactionId, ?float $amount): void
    {
        DB::transaction(function () use ($orderIds, $reference, $status, $transactionId, $amount) {
            // Lock de todos los pedidos de la referencia: si Wompi reintenta el
            // mismo evento (o llegan dos casi a la vez), dos requests no pueden
            // aplicar el mismo cambio dos veces.
            $orders = Order::query()->whereKey($orderIds)->lockForUpdate()->get();

            if ($status === 'approved') {
                $expected = $this->wompi->amountInCents((float) $orders->sum('total'));
                $received = $amount !== null ? $this->wompi->amountInCents($amount) : null;

                if ($received !== $expected) {
                    Log::error("Transacción de Wompi {$transactionId} aprobada por un monto distinto al del pedido ({$reference}).", [
                        'expected_cents' => $expected,
                        'received_cents' => $received,
                    ]);
                    $orders->reject(fn (Order $order) => $order->payment_status === 'approved')
                        ->each(fn (Order $order) => $order->update([
                            'payment_status' => 'amount_mismatch',
                            'payment_transaction_id' => $transactionId,
                        ]));

                    return;
                }
            }

            foreach ($orders as $order) {
                if ($order->payment_status === 'approved') {
                    continue;
                }

                if ($status === 'approved') {
                    if ($order->status !== 'pending') {
                        // Pagó un pedido que ya se había cancelado (por el
                        // comprador o por expiración): hay que reembolsar.
                        Log::critical("Pago aprobado ({$transactionId}) para el pedido #{$order->id}, que estaba \"{$order->status}\". Requiere reembolso manual.");
                    }

                    $order->update([
                        'payment_status' => 'approved',
                        'payment_transaction_id' => $transactionId,
                        'paid_at' => now(),
                        'status' => $order->status === 'pending' ? 'processing' : $order->status,
                    ]);

                    continue;
                }

                // Intento no aprobado: solo se refleja si el pedido sigue
                // abierto, para que el comprador vea el rechazo y reintente.
                if ($order->status === 'pending' && $order->payment_status !== 'amount_mismatch') {
                    $order->update([
                        'payment_status' => $status,
                        'payment_transaction_id' => $transactionId,
                    ]);
                }
            }
        });
    }
}

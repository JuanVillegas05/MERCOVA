<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'seller_id',
        'status',
        'total',
        'commission_amount',
        'net_amount',
        'payment_method',
        'payment_reference',
        'payment_status',
        'payment_transaction_id',
        'paid_at',
        'transaction_type',
        'notes',
        'recipient_name',
        'recipient_phone',
        'address',
        'city',
        'department',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            AdminNotification::record(
                'order.created',
                "Nuevo pedido #{$order->id}",
                "{$order->buyer->name} realizó un pedido por ".number_format((float) $order->total, 2),
                $order
            );

            UserNotification::record(
                $order->seller_id,
                'order.created',
                "Nueva compra: pedido #{$order->id}",
                "{$order->buyer->name} te compró por ".number_format((float) $order->total, 2).'.',
                $order
            );
        });

        static::updated(function (Order $order) {
            if (! $order->wasChanged('status')) {
                return;
            }

            UserNotification::record(
                $order->buyer_id,
                'order.status_changed',
                "Tu pedido #{$order->id} cambió de estado",
                'Nuevo estado: '.$order->status,
                $order
            );
        });
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Repone el stock de los productos de este pedido. Se usa al cancelarlo
     * (por el comprador, un admin, o un pago rechazado/anulado por la pasarela)
     * para no dejar stock consumido por una compra que nunca se completó.
     * `increment()` genera un UPDATE atómico por fila, así que no necesita
     * lockForUpdate propio.
     */
    public function restoreProductStock(): void
    {
        foreach ($this->items()->where('type', 'product')->get() as $item) {
            if ($item->product_id) {
                Product::query()->where('id', $item->product_id)->increment('stock', $item->quantity);
            }
        }
    }
}

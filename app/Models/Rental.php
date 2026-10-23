<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    use HasFactory;

    public const ACTIVE_STATUSES = ['pending', 'confirmed', 'active'];

    protected $fillable = [
        'product_id',
        'renter_id',
        'start_date',
        'end_date',
        'days',
        'price_per_day',
        'deposit_amount',
        'total',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'status',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'integer',
        'price_per_day' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (Rental $rental) {
            AdminNotification::record(
                'rental.created',
                "Nueva solicitud de alquiler #{$rental->id}",
                "{$rental->renter->name} solicitó alquilar \"{$rental->product->title}\" del {$rental->start_date->toDateString()} al {$rental->end_date->toDateString()}.",
                $rental
            );

            UserNotification::record(
                $rental->product->seller_id,
                'rental.created',
                'Nueva solicitud de alquiler',
                "{$rental->renter->name} quiere alquilar \"{$rental->product->title}\" del {$rental->start_date->toDateString()} al {$rental->end_date->toDateString()}.",
                $rental
            );
        });

        static::updated(function (Rental $rental) {
            if (! $rental->wasChanged('status')) {
                return;
            }

            $labels = [
                'confirmed' => 'fue confirmada',
                'active' => 'ahora está activa',
                'finished' => 'fue marcada como finalizada',
                'cancelled' => 'fue cancelada',
            ];

            UserNotification::record(
                $rental->renter_id,
                'rental.status_changed',
                'Tu solicitud de alquiler '.($labels[$rental->status] ?? 'cambió de estado'),
                "\"{$rental->product->title}\" · {$rental->start_date->toDateString()} al {$rental->end_date->toDateString()}.",
                $rental
            );
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function renter()
    {
        return $this->belongsTo(User::class, 'renter_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'buyer_id',
        'scheduled_at',
        'guest_count',
        'total',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'guest_count' => 'integer',
        'total' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (Booking $booking) {
            AdminNotification::record(
                'booking.created',
                "Nueva reserva #{$booking->id}",
                "{$booking->buyer->name} reservó \"{$booking->service->title}\"",
                $booking
            );

            UserNotification::record(
                $booking->service->seller_id,
                'booking.created',
                'Nueva reserva de servicio',
                "{$booking->buyer->name} reservó \"{$booking->service->title}\".",
                $booking
            );
        });

        static::updated(function (Booking $booking) {
            if (! $booking->wasChanged('status')) {
                return;
            }

            UserNotification::record(
                $booking->buyer_id,
                'booking.status_changed',
                'Tu reserva cambió de estado',
                "\"{$booking->service->title}\" · nuevo estado: {$booking->status}.",
                $booking
            );
        });
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }
}

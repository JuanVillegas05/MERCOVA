<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'business_id',
        'category_id',
        'title',
        'slug',
        'description',
        'price',
        'commission_rate',
        'net_amount',
        'duration',
        'location',
        'status',
        'image_url',
        'is_featured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'net_amount' => 'decimal:2',
        'is_featured' => 'boolean',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * El vendedor escribe la duración como texto libre ("2 horas", "45 minutos").
     * Se interpreta para poder calcular el rango de tiempo que ocupa una reserva
     * y así detectar solapamientos. Si no se puede interpretar, se asume 1 hora.
     */
    public function durationInMinutes(): int
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(hora|horas|hrs?|h)\b/iu', (string) $this->duration, $matches)) {
            return (int) round((float) str_replace(',', '.', $matches[1]) * 60);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(minuto|minutos|mins?|m)\b/iu', (string) $this->duration, $matches)) {
            return (int) round((float) str_replace(',', '.', $matches[1]));
        }

        return 60;
    }
}

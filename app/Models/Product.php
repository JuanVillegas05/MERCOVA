<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
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
        'price_per_day',
        'deposit_amount',
        'commission_rate',
        'net_amount',
        'transaction_type',
        'stock',
        'condition',
        'status',
        'location',
        'image_url',
        'is_featured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_per_day' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'net_amount' => 'decimal:2',
        'stock' => 'integer',
        'is_featured' => 'boolean',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}

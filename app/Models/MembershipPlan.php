<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_period',
        'max_listings',
        'features',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_listings' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanUsage extends Model
{
    protected $fillable = [
        'user_id',
        'period',
        'listings_used',
        'requests_used',
    ];

    protected $casts = [
        'listings_used' => 'integer',
        'requests_used' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Los cupos se reinician cada mes calendario ("2026-11").
     */
    public static function currentPeriod(): string
    {
        return now()->format('Y-m');
    }
}

<?php

namespace App\Models;

use Database\Factories\SubscriptionPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    /** @use HasFactory<SubscriptionPaymentFactory> */
    use HasFactory;

    /** De gratis (o sin plan) a un plan de pago: empieza al confirmarse el pago. */
    public const CHANGE_NEW = 'new';

    /** A un plan superior: empieza al confirmarse el pago, descontando lo no usado del plan actual. */
    public const CHANGE_UPGRADE = 'upgrade';

    /** El mismo plan otro periodo: empieza cuando vence el actual. */
    public const CHANGE_RENEWAL = 'renewal';

    /** A un plan inferior: empieza cuando vence el actual (no se pierde lo ya pagado). */
    public const CHANGE_DOWNGRADE = 'downgrade';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_AMOUNT_MISMATCH = 'amount_mismatch';

    /** Pendiente por más de 2 días: el usuario abandonó el checkout. */
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'membership_plan_id',
        'subscription_id',
        'change_type',
        'reference',
        'amount',
        'credit_amount',
        'currency',
        'status',
        'transaction_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<MembershipPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Upgrade y alta nueva arrancan apenas se confirma el pago; renovación
     * y downgrade se encolan para cuando venza el plan vigente.
     */
    public function startsImmediately(): bool
    {
        return self::changeStartsImmediately($this->change_type);
    }

    public static function changeStartsImmediately(string $changeType): bool
    {
        return in_array($changeType, [self::CHANGE_NEW, self::CHANGE_UPGRADE], true);
    }
}

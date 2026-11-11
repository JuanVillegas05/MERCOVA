<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'membership_plan_id',
        'name',
        'slug',
        'description',
        'category',
        'logo_url',
        'phone',
        'address',
        'status',
        'membership_started_at',
        'membership_expires_at',
    ];

    protected $casts = [
        'membership_started_at' => 'datetime',
        'membership_expires_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function plan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function hasActiveMembership(): bool
    {
        return $this->status === 'active'
            && $this->membership_expires_at !== null
            && $this->membership_expires_at->isFuture();
    }

    public function activeListingsCount(): int
    {
        return $this->products()->where('status', 'active')->count()
            + $this->services()->where('status', 'active')->count();
    }

    /**
     * El dueño del emprendimiento y los administradores pueden gestionarlo
     * (editarlo, cambiar su membresía, publicar a su nombre).
     */
    public function canBeManagedBy(User $user): bool
    {
        return $user->role === 'admin' || $this->user_id === $user->id;
    }

    /**
     * Agrupa las tres condiciones que debe cumplir un emprendimiento para
     * publicar un producto o servicio a su nombre: que sea del usuario (o que
     * el usuario sea admin), que tenga membresía activa, y que no haya
     * superado el límite de publicaciones de su plan.
     *
     * $noun es la palabra ("productos"/"servicios") para el mensaje de error.
     */
    public function assertCanPublishListing(User $user, string $noun): void
    {
        if (! $this->canBeManagedBy($user)) {
            abort(403, 'Este emprendimiento no te pertenece.');
        }

        if ($user->role !== 'admin' && ! $this->hasActiveMembership()) {
            abort(403, "Tu emprendimiento necesita una membresía activa para publicar {$noun}.");
        }

        $plan = $this->plan;
        if ($plan && $plan->max_listings !== null && $this->activeListingsCount() >= $plan->max_listings) {
            abort(422, 'Alcanzaste el límite de publicaciones de tu plan de membresía.');
        }
    }
}

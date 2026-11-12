<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\WelcomeUserMail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;
use Throwable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'bio', 'avatar_url', 'is_verified', 'is_active', 'oauth_provider', 'oauth_provider_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Columnas que se pueden mostrar de un usuario en respuestas públicas
     * (vendedor de un producto, dueño de un emprendimiento, autor de una
     * reseña). Nunca incluye email, teléfono ni datos de OAuth.
     * Uso: ->with('seller:'.User::PUBLIC_PROFILE_COLUMNS)
     */
    public const PUBLIC_PROFILE_COLUMNS = 'id,name,bio,avatar_url,is_verified';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'bio',
        'avatar_url',
        'is_verified',
        'is_active',
        'oauth_provider',
        'oauth_provider_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'seller_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'buyer_id');
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class, 'renter_id');
    }

    public function business()
    {
        return $this->hasOne(Business::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Historial completo de suscripciones (incluye las canceladas/vencidas).
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<SubscriptionPayment, $this>
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * La suscripción vigente más reciente (activa y sin vencer), si hay.
     *
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->current(),
        );
    }

    /**
     * Plan con el que se evalúan los permisos y límites del usuario: el de
     * su suscripción vigente o, si no tiene (cuenta antigua, o su plan pago
     * venció), el plan gratuito por defecto. Null solo si el catálogo de
     * planes no está sembrado, y en ese caso no se aplican límites.
     */
    public function currentPlan(): ?MembershipPlan
    {
        return $this->activeSubscription?->plan ?? MembershipPlan::defaultPlan();
    }

    public function isSellerOrAdmin(): bool
    {
        return in_array($this->role, ['seller', 'admin'], true);
    }

    /**
     * Si el correo de esta cuenta está en la lista de administradores
     * (config('mercova.admin_emails'), controlada por .env), la promueve.
     * Se llama al registrarse y al iniciar sesión: nunca degrada a nadie,
     * solo promueve.
     */
    public function syncRoleFromAdminEmailList(): void
    {
        if ($this->role === 'admin') {
            return;
        }

        if (in_array($this->email, config('mercova.admin_emails', []), true)) {
            $this->update(['role' => 'admin']);
        }
    }

    /**
     * Encola el correo de bienvenida (WelcomeUserMail implementa
     * ShouldQueue): el SMTP de Brevo se usa desde el worker, no durante el
     * registro. Un fallo al encolar se reporta sin tumbar el registro del
     * usuario, que ya quedó creado en base de datos.
     */
    public function sendWelcomeEmail(): void
    {
        try {
            Mail::to($this->email)->send(new WelcomeUserMail($this));
        } catch (Throwable $e) {
            report($e);
        }
    }
}

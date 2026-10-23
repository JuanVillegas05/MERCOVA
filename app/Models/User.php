<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\WelcomeNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'bio', 'avatar_url', 'is_verified', 'is_active', 'oauth_provider', 'oauth_provider_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
     * Envía el correo de bienvenida sin dejar que un fallo del proveedor de
     * correo (p. ej. restricciones de cuenta de prueba de Resend) tumbe el
     * registro del usuario, que ya quedó creado en base de datos.
     */
    public function sendWelcomeNotification(): void
    {
        try {
            $this->notify(new WelcomeNotification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

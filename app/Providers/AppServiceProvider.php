<?php

namespace App\Providers;

use App\Mail\Transport\BrevoTransport;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Services\WompiService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WompiService::class, fn () => WompiService::make());
        $this->app->singleton(CloudinaryService::class, fn () => CloudinaryService::make());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel Socialite no trae "Sign in with Apple" de fábrica; lo añade
        // el paquete socialiteproviders/apple enganchándose a este evento.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('apple', AppleProvider::class);
        });

        // El enlace del correo de recuperación lleva al frontend externo si
        // está configurado; si no, a la página propia de este backend.
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $query = http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()], '', '&', PHP_QUERY_RFC3986);
            $frontendUrl = config('mercova.frontend_url');

            return filled($frontendUrl)
                ? rtrim((string) $frontendUrl, '/').'/restablecer-password?'.$query
                : route('password.reset').'?'.$query;
        });

        // Correo por la API HTTP de Brevo (MAIL_MAILER=brevo).
        Mail::extend('brevo', fn (array $config) => new BrevoTransport(
            $this->app->make(HttpFactory::class),
            $config['key'] ?? null,
        ));
    }
}

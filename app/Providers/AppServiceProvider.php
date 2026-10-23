<?php

namespace App\Providers;

use App\Services\WompiService;
use Illuminate\Support\Facades\Event;
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
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login con Google/Apple (Socialite). Flujo "stateful" normal de OAuth
 * (sesión + cookie, no el flujo "Bearer token" del resto de la API):
 * redirect() manda al comprador a Google/Apple, callback() recibe la
 * respuesta, crea/vincula la cuenta y entrega un token Sanctum igual que
 * /api/login, para que el frontend lo guarde en localStorage de la misma
 * forma que con correo/contraseña.
 */
class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'apple'];

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);
        $this->ensureProviderIsConfigured($provider);

        $redirectUri = $this->sanitizeRedirectUri($request->query('redirect_uri'));

        if ($redirectUri) {
            $request->session()->put('social_redirect_uri', $redirectUri);
        } else {
            $request->session()->forget('social_redirect_uri');
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);
        $this->ensureProviderIsConfigured($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login', ['social_error' => 1]);
        }

        if (! $socialUser->getEmail()) {
            // Pasa sobre todo con Apple si el usuario no autoriza compartir
            // el correo real: sin correo no podemos crear ni vincular cuenta.
            return redirect()->route('login', ['social_error' => 1]);
        }

        $user = User::query()
            ->where('oauth_provider', $provider)
            ->where('oauth_provider_id', $socialUser->getId())
            ->first()
            ?? User::query()->where('email', $socialUser->getEmail())->first();

        $isNewUser = ! $user;

        if ($isNewUser) {
            $user = User::create([
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: Str::before($socialUser->getEmail(), '@'),
                'email' => $socialUser->getEmail(),
                // Cuenta social: nadie va a usar esta contraseña para entrar,
                // pero la columna es NOT NULL, así que se rellena con algo
                // inutilizable en vez de abrir una migración aparte.
                'password' => Hash::make(Str::random(40)),
                'role' => 'customer',
                'oauth_provider' => $provider,
                'oauth_provider_id' => $socialUser->getId(),
                'avatar_url' => $socialUser->getAvatar(),
                'is_verified' => true,
                'is_active' => true,
            ]);
        } else {
            $user->fill([
                'oauth_provider' => $provider,
                'oauth_provider_id' => $socialUser->getId(),
                'avatar_url' => $user->avatar_url ?: $socialUser->getAvatar(),
            ])->save();
        }

        $user->syncRoleFromAdminEmailList();

        if (! $user->is_active) {
            return redirect()->route('login', ['social_error' => 1]);
        }

        if ($isNewUser) {
            $user->sendWelcomeNotification();
        }

        $token = $user->createToken('mercova')->plainTextToken;

        $redirectUri = $request->session()->pull('social_redirect_uri');

        if ($redirectUri) {
            $separator = str_contains($redirectUri, '?') ? '&' : '?';

            return redirect($redirectUri.$separator.'token='.$token);
        }

        return redirect()->route('social.landing', ['token' => $token]);
    }

    private function ensureProviderIsSupported(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    private function ensureProviderIsConfigured(string $provider): void
    {
        if (! config("mercova.social_login.{$provider}")) {
            abort(Response::HTTP_NOT_FOUND, "El login con {$provider} no está configurado.");
        }
    }

    /**
     * Solo se acepta `redirect_uri` si su dominio coincide exactamente con
     * MERCOVA_FRONTEND_URL (el frontend externo del equipo). Cualquier otra
     * cosa se ignora para no abrir un "open redirect" hacia un dominio
     * arbitrario con un token de sesión válido en la URL.
     */
    private function sanitizeRedirectUri(?string $uri): ?string
    {
        if (! $uri) {
            return null;
        }

        $allowed = config('mercova.frontend_url');

        if (! $allowed) {
            return null;
        }

        $allowedOrigin = parse_url($allowed, PHP_URL_SCHEME).'://'.parse_url($allowed, PHP_URL_HOST);
        $origin = parse_url($uri, PHP_URL_SCHEME).'://'.parse_url($uri, PHP_URL_HOST);

        return $origin === $allowedOrigin ? $uri : null;
    }
}

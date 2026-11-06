<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FrontendRedirect;
use App\Services\SocialLoginCodes;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login con Google/Apple (Socialite).
 *
 * 1. GET /auth/{provider}/redirect[?redirect_uri=<url del frontend>] manda
 *    al usuario a Google/Apple.
 * 2. El proveedor vuelve a /auth/{provider}/callback (Google: GET; Apple:
 *    POST cross-site, "form_post"), donde se crea o vincula la cuenta.
 * 3. Se redirige al frontend con ?code=<código de un solo uso> (nunca el
 *    token en la URL) y el frontend lo canjea en POST /api/auth/social/exchange
 *    por el token Sanctum, igual que /api/login.
 *
 * Por qué no se usa la sesión: el POST de Apple llega desde otro sitio y la
 * cookie de sesión (SameSite=lax) no viaja en él. Por eso Apple corre en
 * modo stateless con el nonce en una cookie cifrada propia del proveedor, y
 * el redirect_uri del frontend también viaja en una cookie cifrada.
 */
class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'apple'];

    private const REDIRECT_COOKIE = 'mercova_social_redirect';

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly FrontendRedirect $frontendRedirect,
        private readonly SocialLoginCodes $loginCodes,
    ) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);
        $this->ensureProviderIsConfigured($provider);

        $response = $this->driver($provider)->redirect();
        $redirectUri = $this->frontendRedirect->allowed($request->query('redirect_uri'));

        return $response->withCookie(
            $redirectUri ? $this->redirectCookie($request, $redirectUri) : Cookie::forget(self::REDIRECT_COOKIE)
        );
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);
        $this->ensureProviderIsConfigured($provider);

        $redirectUri = $this->frontendRedirect->allowed($request->cookie(self::REDIRECT_COOKIE));

        try {
            $socialUser = $this->driver($provider)->user();
        } catch (\Throwable $e) {
            report($e);

            return $this->failed($redirectUri);
        }

        if (! $socialUser->getEmail()) {
            // Sin correo no podemos crear ni vincular cuenta (pasa si el
            // usuario no autoriza compartirlo).
            return $this->failed($redirectUri);
        }

        $linkedUser = User::query()
            ->where('oauth_provider', $provider)
            ->where('oauth_provider_id', $socialUser->getId())
            ->first();

        $existingByEmail = $linkedUser ? null : User::query()->where('email', $socialUser->getEmail())->first();

        // Vincular automáticamente una cuenta social a una cuenta existente
        // por solo coincidir el email, sin probar que el dueño del email es
        // quien está iniciando sesión, permitiría un account takeover si el
        // proveedor dejara registrar/usar un correo sin verificarlo. Google y
        // Apple exponen esa confirmación como "email_verified" en el usuario;
        // si no viene en true, no se vincula: la persona debe entrar con su
        // contraseña y vincular la cuenta social desde su perfil.
        if ($existingByEmail && ! $this->providerConfirmsEmail($socialUser)) {
            return $this->failed($redirectUri);
        }

        $user = $linkedUser ?? $existingByEmail;

        // Una cuenta desactivada por un admin no se toca (ni se vincula).
        if ($user && ! $user->is_active) {
            return $this->failed($redirectUri);
        }

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

        if ($isNewUser) {
            $this->subscriptions->subscribeToDefaultPlan($user);
            $user->sendWelcomeEmail();
        }

        $code = $this->loginCodes->issue($user);

        $target = $redirectUri
            ? $this->appendQuery($redirectUri, ['code' => $code])
            : route('social.landing', ['code' => $code]);

        return redirect($target)->withCookie(Cookie::forget(self::REDIRECT_COOKIE));
    }

    /**
     * Apple siempre stateless + nonce en cookie (ver docblock de la clase).
     */
    private function driver(string $provider): Provider
    {
        $driver = Socialite::driver($provider);

        return $provider === 'apple' ? $driver->stateless()->cookieNonce() : $driver;
    }

    /**
     * Error de login: de vuelta al frontend externo (?error=social_login_failed)
     * si vino de ahí, o a la página de login propia.
     */
    private function failed(?string $redirectUri): RedirectResponse
    {
        $target = $redirectUri
            ? $this->appendQuery($redirectUri, ['error' => 'social_login_failed'])
            : route('login', ['social_error' => 1]);

        return redirect($target)->withCookie(Cookie::forget(self::REDIRECT_COOKIE));
    }

    /**
     * Cookie cifrada por EncryptCookies (no se puede falsificar). En HTTPS
     * es SameSite=None para sobrevivir al POST cross-site de Apple; en HTTP
     * local queda en Lax (suficiente para el GET de Google).
     */
    private function redirectCookie(Request $request, string $redirectUri): SymfonyCookie
    {
        $secure = $request->isSecure();

        return Cookie::make(
            self::REDIRECT_COOKIE,
            $redirectUri,
            minutes: 10,
            secure: $secure,
            httpOnly: true,
            sameSite: $secure ? 'none' : 'lax',
        );
    }

    /**
     * @param  array<string, string>  $params
     */
    private function appendQuery(string $url, array $params): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($params);
    }

    /**
     * Google y Apple traen "email_verified" en los datos crudos del usuario
     * (Apple lo trae siempre en el id_token; Google, en la respuesta de su
     * API). Solo se confía en ese valor para vincular una cuenta social a
     * una cuenta existente por coincidencia de email.
     */
    private function providerConfirmsEmail(\Laravel\Socialite\Contracts\User $socialUser): bool
    {
        $raw = method_exists($socialUser, 'getRaw') ? $socialUser->getRaw() : [];

        return filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
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
}

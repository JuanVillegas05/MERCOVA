<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use SocialiteProviders\Apple\Provider as AppleProvider;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND = 'https://app.mercova.test';

    public function test_redirect_is_not_found_when_google_is_not_configured(): void
    {
        $this->get(route('social.redirect', 'google'))->assertNotFound();
    }

    public function test_unsupported_provider_is_not_found(): void
    {
        $this->get('/auth/facebook/redirect')->assertNotFound();
    }

    public function test_google_callback_creates_user_on_free_plan_and_redirects_with_one_time_code(): void
    {
        $this->enableProvider('google');
        $freePlan = MembershipPlan::factory()->free()->create();
        $this->fakeProvider('google', $this->fakeSocialiteUser('google-123', 'nuevo@gmail.com', 'Nuevo Usuario'));

        $response = $this->get(route('social.callback', 'google'));

        $user = User::where('email', 'nuevo@gmail.com')->firstOrFail();
        $this->assertSame('google', $user->oauth_provider);
        $this->assertSame('google-123', $user->oauth_provider_id);
        $this->assertSame('customer', $user->role);
        $this->assertSame($freePlan->id, $user->currentPlan()->id);

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(route('social.landing').'?code=', $location);
        $this->assertStringNotContainsString('token=', $location);

        $exchange = $this->postJson('/api/auth/social/exchange', ['code' => $this->codeFrom($location)]);

        $exchange->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('subscription.plan.id', $freePlan->id);
        $this->assertSame($user->id, PersonalAccessToken::findToken($exchange->json('token'))->tokenable_id);
    }

    public function test_login_code_can_only_be_exchanged_once(): void
    {
        $this->enableProvider('google');
        $this->fakeProvider('google', $this->fakeSocialiteUser('google-123', 'nuevo@gmail.com', 'Nuevo Usuario'));
        $code = $this->codeFrom($this->get(route('social.callback', 'google'))->headers->get('Location'));

        $this->postJson('/api/auth/social/exchange', ['code' => $code])->assertOk();

        $this->postJson('/api/auth/social/exchange', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El enlace de inicio de sesión no es válido o expiró. Intenta de nuevo.');
    }

    public function test_apple_callback_accepts_form_post_and_logs_in_statelessly(): void
    {
        $this->enableProvider('apple');
        $this->fakeProvider('apple', $this->fakeSocialiteUser('apple-001', 'relay@privaterelay.appleid.com', 'Ana Apple'));

        $response = $this->post(route('social.callback', 'apple'), ['code' => 'apple-auth-code', 'state' => 'ignored']);

        $this->assertStringStartsWith(route('social.landing').'?code=', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('users', [
            'email' => 'relay@privaterelay.appleid.com',
            'oauth_provider' => 'apple',
            'oauth_provider_id' => 'apple-001',
        ]);
    }

    public function test_callback_returns_to_the_allowed_frontend_with_the_code(): void
    {
        $this->enableProvider('google');
        config(['mercova.frontend_url' => self::FRONTEND]);
        $this->fakeProvider('google', $this->fakeSocialiteUser('google-123', 'nuevo@gmail.com', 'Nuevo Usuario'));

        $response = $this->withCookie('mercova_social_redirect', self::FRONTEND.'/auth/social')
            ->get(route('social.callback', 'google'));

        $this->assertStringStartsWith(self::FRONTEND.'/auth/social?code=', (string) $response->headers->get('Location'));
    }

    public function test_redirect_ignores_a_redirect_uri_outside_the_frontend_domain(): void
    {
        $this->enableProvider('google');
        config(['mercova.frontend_url' => self::FRONTEND]);
        $this->fakeProvider('google', redirect: true);

        $this->get(route('social.redirect', ['provider' => 'google', 'redirect_uri' => 'https://evil.test/steal']))
            ->assertCookieExpired('mercova_social_redirect');

        $this->get(route('social.redirect', ['provider' => 'google', 'redirect_uri' => self::FRONTEND.'/auth/social']))
            ->assertCookie('mercova_social_redirect', self::FRONTEND.'/auth/social');
    }

    public function test_callback_links_an_existing_account_by_email_instead_of_duplicating(): void
    {
        $this->enableProvider('google');
        $existing = User::factory()->create(['email' => 'ya-existe@gmail.com']);
        $this->fakeProvider('google', $this->fakeSocialiteUser('google-456', 'ya-existe@gmail.com', 'Ya Existe'));

        $this->get(route('social.callback', 'google'));

        $this->assertSame(1, User::where('email', 'ya-existe@gmail.com')->count());
        $existing->refresh();
        $this->assertSame('google', $existing->oauth_provider);
        $this->assertSame('google-456', $existing->oauth_provider_id);
    }

    public function test_callback_does_not_link_an_existing_account_when_provider_does_not_confirm_the_email(): void
    {
        $this->enableProvider('google');
        $existing = User::factory()->create(['email' => 'victima@gmail.com']);

        // Un correo que Google no marca como verificado no debe poder
        // vincularse (y por tanto iniciar sesión) a una cuenta existente:
        // es exactamente el hueco de account takeover que se cerró.
        $this->fakeProvider('google', $this->fakeSocialiteUser('google-789', 'victima@gmail.com', 'Atacante', emailVerified: false));

        $this->get(route('social.callback', 'google'))
            ->assertRedirectToRoute('login', ['social_error' => 1]);

        $existing->refresh();
        $this->assertNull($existing->oauth_provider);
        $this->assertNull($existing->oauth_provider_id);
    }

    public function test_callback_rejects_a_deactivated_account_without_linking_it(): void
    {
        $this->enableProvider('google');
        config(['mercova.frontend_url' => self::FRONTEND]);
        $blocked = User::factory()->create(['email' => 'bloqueado@gmail.com', 'is_active' => false]);
        $this->fakeProvider('google', $this->fakeSocialiteUser('google-999', 'bloqueado@gmail.com', 'Bloqueado'));

        $response = $this->withCookie('mercova_social_redirect', self::FRONTEND.'/auth/social')
            ->get(route('social.callback', 'google'));

        $response->assertRedirect(self::FRONTEND.'/auth/social?error=social_login_failed');
        $this->assertNull($blocked->fresh()->oauth_provider_id);
    }

    private function enableProvider(string $provider): void
    {
        config([
            "mercova.social_login.{$provider}" => true,
            "services.{$provider}.client_id" => 'fake-client-id',
            "services.{$provider}.client_secret" => 'fake-client-secret',
            "services.{$provider}.redirect" => "http://localhost/auth/{$provider}/callback",
        ]);
    }

    /**
     * Reemplaza el driver de Socialite: Google se usa tal cual; Apple debe
     * pedirse stateless()->cookieNonce() (si el controlador no lo hiciera,
     * el mock no respondería user()).
     */
    private function fakeProvider(string $provider, ?SocialiteUser $user = null, bool $redirect = false): void
    {
        $driver = Mockery::mock($provider === 'apple' ? AppleProvider::class : GoogleProvider::class);

        if ($provider === 'apple') {
            $driver->shouldReceive('stateless')->andReturnSelf();
            $driver->shouldReceive('cookieNonce')->andReturnSelf();
        }

        if ($user) {
            $driver->shouldReceive('user')->andReturn($user);
        }

        if ($redirect) {
            $driver->shouldReceive('redirect')->andReturnUsing(fn () => new RedirectResponse("https://accounts.{$provider}.test/oauth"));
        }

        $this->mock(SocialiteFactory::class, function ($mock) use ($provider, $driver) {
            $mock->shouldReceive('driver')->with($provider)->andReturn($driver);
        });
    }

    private function fakeSocialiteUser(string $id, string $email, string $name, bool $emailVerified = true): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->id = $id;
        $user->email = $email;
        $user->name = $name;
        $user->avatar = null;
        $user->setRaw(['email_verified' => $emailVerified]);

        return $user;
    }

    private function codeFrom(?string $location): string
    {
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        return $query['code'];
    }
}

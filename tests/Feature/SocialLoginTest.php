<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private function enableGoogle(): void
    {
        config([
            'mercova.social_login.google' => true,
            'services.google.client_id' => 'fake-client-id',
            'services.google.client_secret' => 'fake-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    private function fakeSocialiteUser(string $id, string $email, string $name): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->id = $id;
        $user->email = $email;
        $user->name = $name;
        $user->avatar = null;

        return $user;
    }

    public function test_redirect_is_not_found_when_google_is_not_configured(): void
    {
        $this->get(route('social.redirect', 'google'))->assertNotFound();
    }

    public function test_unsupported_provider_is_not_found(): void
    {
        $this->get('/auth/facebook/redirect')->assertNotFound();
    }

    public function test_callback_creates_a_new_user_and_redirects_with_a_token(): void
    {
        $this->enableGoogle();

        $socialiteUser = $this->fakeSocialiteUser('google-123', 'nuevo@gmail.com', 'Nuevo Usuario');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        $this->mock(SocialiteFactory::class, function ($mock) use ($provider) {
            $mock->shouldReceive('driver')->with('google')->andReturn($provider);
        });

        $response = $this->get(route('social.callback', 'google'));

        $user = User::where('email', 'nuevo@gmail.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('google', $user->oauth_provider);
        $this->assertSame('google-123', $user->oauth_provider_id);
        $this->assertSame('customer', $user->role);

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(route('social.landing').'?token=', $location);

        $plainTextToken = urldecode((string) str($location)->after('token='));
        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        $this->assertNotNull($accessToken);
        $this->assertSame($user->id, $accessToken->tokenable_id);
    }

    public function test_callback_links_an_existing_account_by_email_instead_of_duplicating(): void
    {
        $this->enableGoogle();

        $existing = User::factory()->create(['email' => 'ya-existe@gmail.com']);

        $socialiteUser = $this->fakeSocialiteUser('google-456', 'ya-existe@gmail.com', 'Ya Existe');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        $this->mock(SocialiteFactory::class, function ($mock) use ($provider) {
            $mock->shouldReceive('driver')->with('google')->andReturn($provider);
        });

        $this->get(route('social.callback', 'google'));

        $this->assertSame(1, User::where('email', 'ya-existe@gmail.com')->count());

        $existing->refresh();
        $this->assertSame('google', $existing->oauth_provider);
        $this->assertSame('google-456', $existing->oauth_provider_id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_identifier_does_not_reveal_whether_the_account_exists(): void
    {
        User::factory()->create(['email' => 'existe@mercova.test']);

        $existing = $this->postJson('/api/auth/check-identifier', ['identifier' => 'existe@mercova.test']);
        $missing = $this->postJson('/api/auth/check-identifier', ['identifier' => 'no-existe@mercova.test']);

        $existing->assertOk();
        $missing->assertOk();
        $this->assertSame($existing->json('message'), $missing->json('message'));
    }

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        $user = User::factory()->create(['email' => 'limite@mercova.test']);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertStatus(429);
    }

    public function test_login_rate_limit_is_per_client_ip_behind_the_load_balancer(): void
    {
        $user = User::factory()->create(['email' => 'proxy@mercova.test']);
        $attempt = fn (string $clientIp) => $this
            ->withHeader('X-Forwarded-For', $clientIp)
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password']);

        for ($i = 0; $i < 10; $i++) {
            $attempt('203.0.113.10')->assertStatus(401);
        }

        $attempt('203.0.113.10')->assertStatus(429);
        $attempt('198.51.100.20')->assertStatus(401);
    }

    public function test_me_returns_the_owner_of_the_token_sent_in_each_request(): void
    {
        $first = $this->postJson('/api/register', [
            'name' => 'Primera Persona',
            'email' => 'primera@mercova.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
        $this->forgetResolvedUser();

        $second = $this->postJson('/api/register', [
            'name' => 'Segunda Persona',
            'email' => 'segunda@mercova.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
        $this->forgetResolvedUser();

        $this->withToken($second->json('token'))->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'segunda@mercova.test')
            ->assertJsonPath('user.name', 'Segunda Persona');
        $this->forgetResolvedUser();

        $this->withToken($first->json('token'))->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'primera@mercova.test');
    }

    public function test_login_does_not_start_a_server_session(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertGuest('web');
    }

    public function test_account_responses_tell_the_browser_not_to_store_them(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $responses = [
            // 401 del middleware de autenticación: corre antes que el
            // controlador y también debe llevar las cabeceras.
            $this->getJson('/api/me')->assertUnauthorized(),
            $this->postJson('/api/register', [
                'name' => 'Nueva',
                'email' => 'nueva@mercova.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]),
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123']),
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password']),
            $this->actingAs($user, 'sanctum')->getJson('/api/me'),
        ];

        foreach ($responses as $response) {
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
    }

    /**
     * En producción cada petición arranca de cero; en los tests la app (y
     * el usuario que el guard ya resolvió) se reutiliza entre peticiones.
     */
    private function forgetResolvedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }
}

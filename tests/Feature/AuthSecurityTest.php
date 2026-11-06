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
}

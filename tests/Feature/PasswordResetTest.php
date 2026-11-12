<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_answers_the_same_whether_or_not_the_account_exists(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'ana@example.com']);

        $existing = $this->postJson('/api/forgot-password', ['email' => 'ana@example.com'])->assertOk();
        $missing = $this->postJson('/api/forgot-password', ['email' => 'nadie@example.com'])->assertOk();
        $repeated = $this->postJson('/api/forgot-password', ['email' => 'ana@example.com'])->assertOk();

        $this->assertSame($existing->json(), $missing->json());
        $this->assertSame($existing->json(), $repeated->json());
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_reset_link_points_to_the_frontend(): void
    {
        Notification::fake();
        config(['mercova.frontend_url' => 'https://app.mercova.test']);
        $user = User::factory()->create(['email' => 'ana+compras@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'ana+compras@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://app.mercova.test/restablecer-password?token='.$notification->token)
                && str_ends_with($url, '&email=ana%2Bcompras%40example.com');
        });
    }

    public function test_reset_password_changes_the_password_and_closes_every_session(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->createToken('celular');
        $user->createToken('portatil');
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'ana@example.com',
            'password' => 'NuevaClave-2026',
            'password_confirmation' => 'NuevaClave-2026',
        ])->assertOk();

        $this->assertTrue(Hash::check('NuevaClave-2026', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $originalHash = $user->password;

        $this->postJson('/api/reset-password', [
            'token' => 'token-falso',
            'email' => 'ana@example.com',
            'password' => 'NuevaClave-2026',
            'password_confirmation' => 'NuevaClave-2026',
        ])->assertUnprocessable()->assertJsonPath('message', 'El token de recuperación no es válido o expiró.');

        $this->assertSame($originalHash, $user->fresh()->password);
    }
}

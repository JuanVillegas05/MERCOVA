<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminEmailListTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_with_an_email_from_the_admin_list_becomes_admin_immediately(): void
    {
        Config::set('mercova.admin_emails', ['founder@mercova.test']);

        $response = $this->postJson('/api/register', [
            'name' => 'Fundador',
            'email' => 'founder@mercova.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)->assertJsonPath('user.role', 'admin');
        $this->assertDatabaseHas('users', ['email' => 'founder@mercova.test', 'role' => 'admin']);
    }

    public function test_registering_with_an_email_not_in_the_admin_list_stays_customer(): void
    {
        Config::set('mercova.admin_emails', ['founder@mercova.test']);

        $response = $this->postJson('/api/register', [
            'name' => 'Cliente Normal',
            'email' => 'normal@mercova.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)->assertJsonPath('user.role', 'customer');
    }

    public function test_an_existing_customer_is_promoted_to_admin_on_login_if_their_email_joins_the_list(): void
    {
        $user = User::factory()->create(['email' => 'lateadmin@mercova.test', 'role' => 'customer']);
        Config::set('mercova.admin_emails', ['lateadmin@mercova.test']);

        $response = $this->postJson('/api/login', [
            'email' => 'lateadmin@mercova.test',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('user.role', 'admin');
        $this->assertSame('admin', $user->fresh()->role);
    }

    public function test_registration_sends_the_welcome_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@mercova.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $user = User::where('email', 'nuevo@mercova.test')->first();
        Notification::assertSentTo($user, WelcomeNotification::class);
    }
}

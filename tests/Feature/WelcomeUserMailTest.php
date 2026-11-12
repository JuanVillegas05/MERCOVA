<?php

namespace Tests\Feature;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WelcomeUserMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_email_greets_the_user_and_links_to_the_marketplace(): void
    {
        $user = User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@example.com']);

        $mail = new WelcomeUserMail($user);

        $mail->assertHasSubject('¡Bienvenido a '.config('app.name').'!');
        $mail->assertSeeInHtml('¡Hola, Ana Gómez!');
        $mail->assertSeeInHtml('ana@example.com');
        $mail->assertSeeInHtml(route('marketplace'));
        $mail->assertSeeInText('¡Hola, Ana Gómez!');
        $mail->assertSeeInText(route('marketplace'));
    }

    public function test_welcome_email_is_sent_through_the_brevo_http_api(): void
    {
        $this->useBrevoMailer();
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<abc@smtp-relay.mailin.fr>'], 201)]);
        $user = User::factory()->create(['name' => 'Ana Gómez', 'email' => 'ana@example.com']);

        $user->sendWelcomeEmail();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request->hasHeader('api-key', 'xkeysib-test')
            && $request['sender'] === ['email' => 'mercova@example.com', 'name' => 'Mercova']
            && $request['to'] === [['email' => 'ana@example.com']]
            && $request['subject'] === '¡Bienvenido a '.config('app.name').'!'
            && str_contains($request['htmlContent'], '¡Hola, Ana Gómez!'));
    }

    public function test_registration_still_succeeds_when_brevo_rejects_the_welcome_email(): void
    {
        $this->useBrevoMailer();
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'Key not found'], 401)]);

        $this->postJson('/api/register', [
            'name' => 'Ana Gómez',
            'email' => 'ana@example.com',
            'password' => 'Secreta-123456',
            'password_confirmation' => 'Secreta-123456',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
        Http::assertSentCount(1);
    }

    private function useBrevoMailer(): void
    {
        config([
            'mail.default' => 'brevo',
            'mail.mailers.brevo.key' => 'xkeysib-test',
            'mail.from' => ['address' => 'mercova@example.com', 'name' => 'Mercova'],
            'queue.default' => 'sync',
        ]);
    }

    public function test_welcome_email_escapes_the_user_name(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

        $mail = new WelcomeUserMail($user);

        $mail->assertDontSeeInHtml('<script>alert(1)</script>', escape: false);
        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
    }
}

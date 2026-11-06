<?php

namespace Tests\Feature;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_welcome_email_escapes_the_user_name(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

        $mail = new WelcomeUserMail($user);

        $mail->assertDontSeeInHtml('<script>alert(1)</script>', escape: false);
        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
    }
}

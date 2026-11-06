<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de bienvenida al crear una cuenta (registro normal o login social).
 *
 * Implementa ShouldQueue: Mail::to(...)->send() lo encola en vez de abrir la
 * conexión SMTP durante el registro, así un Brevo lento o caído no demora
 * ni tumba la respuesta. El worker reintenta los fallos (ver $tries).
 */
class WelcomeUserMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Reintentos ante fallos transitorios del SMTP. */
    public int $tries = 3;

    /** Segundos entre reintentos. */
    public int $backoff = 60;

    public function __construct(public User $user)
    {
        // Si el registro llegara a correr dentro de una transacción, el
        // correo solo sale cuando el usuario ya quedó guardado.
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Bienvenido a '.config('app.name').'!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            text: 'emails.welcome-text',
            with: [
                'appName' => config('app.name'),
                'marketplaceUrl' => route('marketplace'),
            ],
        );
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('¡Bienvenido a Mercova!')
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line('Tu cuenta en Mercova fue creada con éxito. Ya puedes comprar, vender o alquilar productos y servicios en todo Colombia.')
            ->action('Explorar el marketplace', url('/marketplace'))
            ->line('Si no creaste esta cuenta, puedes ignorar este correo.')
            ->salutation('El equipo de Mercova');
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoachAccountApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('services.visio.frontend_url', 'https://gotfit.tech'), '/');

        return (new MailMessage)
            ->subject('Votre compte coach GotFit est vérifié')
            ->greeting('Bonjour '.($notifiable->display_name ?: $notifiable->name ?: '').',')
            ->line('Votre compte coach a été vérifié avec succès par l’équipe GotFit.')
            ->line('Vous pouvez maintenant accéder à votre espace coach et publier vos annonces de prestations en ligne.')
            ->action('Accéder à mon espace coach', $frontendUrl.'/intervenant/dashboard')
            ->salutation('L’équipe GotFit');
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoachApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre compte coach GotFit est validé')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre compte coach a été vérifié avec succès. Vous pouvez désormais publier vos annonces de coaching en ligne.')
            ->action('Accéder à mon espace coach', rtrim(config('services.visio.frontend_url'), '/').'/intervenant/dashboard')
            ->line('Depuis votre espace, créez votre annonce et indiquez vos tarifs et disponibilités.');
    }
}

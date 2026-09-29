<?php

namespace App\Notifications;

use App\Models\Annonce;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnonceStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Annonce $annonce,
        private readonly string $status
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $published = $this->status === 'valide';
        $frontendUrl = rtrim((string) config('services.visio.frontend_url', 'https://gotfit.tech'), '/');

        $mail = (new MailMessage)
            ->subject($published
                ? 'Votre annonce GotFit est publiée'
                : 'Votre annonce GotFit a été refusée')
            ->greeting('Bonjour '.($notifiable->display_name ?: $notifiable->name ?: '').',')
            ->line($published
                ? 'Bonne nouvelle : votre annonce a été vérifiée et publiée par l’équipe GotFit.'
                : 'Après vérification, votre annonce n’a pas été publiée par l’équipe GotFit.')
            ->line('Annonce : '.$this->annonce->titre);

        if (! $published) {
            $mail->line('Vous pouvez la modifier depuis votre espace, puis la renvoyer pour une nouvelle validation.');
        }

        return $mail
            ->action($published ? 'Voir mon annonce' : 'Gérer mes annonces', $published
                ? $frontendUrl.'/annonces/'.$this->annonce->id
                : $frontendUrl.'/annonces/mes-annonces')
            ->salutation('L’équipe GotFit');
    }
}

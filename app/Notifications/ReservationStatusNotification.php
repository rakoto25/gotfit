<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Reservation $reservation,
        private readonly string $event,
        private readonly ?string $customMessage = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reservation = $this->reservation->loadMissing(['annonce', 'client', 'intervenant']);
        $subject = $this->subject();
        $message = $this->customMessage ?: $this->message();
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://gotfit.tech/webapp'), '/');
        $reservationUrl = $frontendUrl . '/profile?reservation=' . $reservation->id;

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Bonjour ' . ($notifiable->name ?? '') . ',')
            ->line($message)
            ->line('Séance : ' . $reservation->calendarTitle())
            ->line('Date : ' . $reservation->scheduledAt()->format('d/m/Y à H:i'))
            ->line('Client : ' . ($reservation->client?->name ?: 'Non renseigné'))
            ->line('Coach : ' . ($reservation->intervenant?->name ?: 'Non renseigné'))
            ->action('Voir la réservation', $reservationUrl)
            ->line('Merci,')
            ->salutation('L’équipe GotFit');
    }

    private function subject(): string
    {
        return match ($this->event) {
            'created' => 'Nouvelle réservation GotFit',
            'confirmed' => 'Réservation confirmée',
            'refused' => 'Réservation refusée',
            'paid' => 'Paiement confirmé',
            'payment_failed' => 'Paiement échoué',
            'pending_validation' => 'Séance terminée : validation demandée',
            'validated' => 'Prestation validée',
            'disputed' => 'Litige ouvert sur une réservation',
            'transferred' => 'Reversement coach effectué',
            'refunded' => 'Réservation remboursée',
            'cancelled' => 'Réservation annulée',
            default => 'Mise à jour de réservation GotFit',
        };
    }

    private function message(): string
    {
        return match ($this->event) {
            'created' => 'Une réservation vient d’être créée. Le paiement est maintenant attendu.',
            'confirmed' => 'La réservation a été confirmée par le coach.',
            'refused' => 'La réservation a été refusée.',
            'paid' => 'Le paiement de la réservation est confirmé.',
            'payment_failed' => 'Le paiement de la réservation a échoué.',
            'pending_validation' => 'La séance est marquée comme réalisée. Le client peut confirmer la prestation ou ouvrir un litige.',
            'validated' => 'La prestation est validée. Le reversement coach peut être déclenché.',
            'disputed' => 'Un litige a été ouvert. Le reversement coach est bloqué en attendant la décision admin.',
            'transferred' => 'Le reversement coach a été effectué.',
            'refunded' => 'La réservation a été remboursée.',
            'cancelled' => 'La réservation a été annulée.',
            default => 'Le statut de votre réservation a été mis à jour.',
        };
    }
}

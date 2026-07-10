<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\VisioParticipant;
use App\Models\VisioSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationVisioService
{
    public function syncPaidReservation(Reservation $reservation): ?VisioSession
    {
        $reservation->loadMissing(['annonce', 'client', 'intervenant']);

        if (!$this->isPaid($reservation) || !$this->isOnline($reservation)) {
            return null;
        }

        return DB::transaction(function () use ($reservation) {
            $reservation->refresh();
            $reservation->loadMissing(['annonce', 'client', 'intervenant']);

            $session = $reservation->visioSession;

            if (!$session) {
                $session = VisioSession::create([
                    'reservation_id' => $reservation->id,
                    'annonce_id' => $reservation->annonce_id,
                    'coach_id' => $reservation->intervenant_id,
                    'session_type' => 'individual',
                    'title' => $reservation->annonce?->titre ?: 'Séance GotFit',
                    'description' => 'Séance visio liée à la réservation #' . $reservation->id,
                    'start_at' => $reservation->scheduledAt(),
                    'duration_minutes' => max((int) ($reservation->annonce?->duration ?: 60), 15),
                    'min_participants' => 1,
                    'max_participants' => 1,
                    'price' => 0,
                    'currency' => strtoupper($reservation->currency ?: 'EUR'),
                    'status' => 'confirmed',
                    'provider' => config('services.visio.provider', 'livekit'),
                    'room_name' => $this->uniqueRoomName($reservation),
                ]);

                $reservation->forceFill(['visio_session_id' => $session->id])->save();
            } else {
                $session->forceFill([
                    'reservation_id' => $reservation->id,
                    'annonce_id' => $reservation->annonce_id,
                    'coach_id' => $reservation->intervenant_id,
                    'session_type' => 'individual',
                    'start_at' => $reservation->scheduledAt(),
                    'duration_minutes' => max((int) ($reservation->annonce?->duration ?: 60), 15),
                    'min_participants' => 1,
                    'max_participants' => 1,
                    'status' => in_array($session->status, ['ended', 'cancelled'], true) ? $session->status : 'confirmed',
                ])->save();
            }

            VisioParticipant::updateOrCreate(
                ['visio_session_id' => $session->id, 'user_id' => $reservation->client_id],
                [
                    'reservation_id' => $reservation->id,
                    'role' => 'participant',
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'amount' => $reservation->total_client_amount ?: $reservation->price ?: 0,
                    'currency' => strtoupper($reservation->currency ?: 'EUR'),
                    'payment_intent_id' => $reservation->payment_intent_id,
                    'paid_at' => $reservation->paid_at ?: now(),
                    'cancelled_at' => null,
                ]
            );

            VisioParticipant::updateOrCreate(
                ['visio_session_id' => $session->id, 'user_id' => $reservation->intervenant_id],
                [
                    'reservation_id' => $reservation->id,
                    'role' => 'coach',
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'amount' => 0,
                    'currency' => strtoupper($reservation->currency ?: 'EUR'),
                    'paid_at' => $reservation->paid_at ?: now(),
                    'cancelled_at' => null,
                ]
            );

            return $session->fresh(['coach:id,name,email,photo', 'participants.user:id,name,email,photo']);
        });
    }

    public function cancelForReservation(Reservation $reservation): void
    {
        $session = $reservation->visioSession;
        if (!$session || $session->status === 'ended') {
            return;
        }

        $session->update([
            'status' => 'cancelled',
            'cancellation_reason' => 'Réservation annulée ou remboursée.',
        ]);

        $session->participants()->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    private function isPaid(Reservation $reservation): bool
    {
        return (bool) $reservation->is_paid || $reservation->payment_status === 'paid';
    }

    private function isOnline(Reservation $reservation): bool
    {
        $type = Str::lower((string) $reservation->annonce?->type_prestation);

        return (bool) $reservation->annonce?->is_online
            || in_array($type, ['online', 'en_ligne', 'en ligne', 'visio', 'distance'], true);
    }

    private function uniqueRoomName(Reservation $reservation): string
    {
        $base = 'gotfit-reservation-' . $reservation->id;
        $roomName = $base;
        $counter = 1;

        while (VisioSession::where('room_name', $roomName)->exists()) {
            $roomName = $base . '-' . $counter++;
        }

        return $roomName;
    }
}

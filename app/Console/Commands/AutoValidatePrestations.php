<?php

namespace App\Console\Commands;

use App\Models\PackSession;
use App\Models\Reservation;
use App\Notifications\ReservationStatusNotification;
use App\Services\PackPayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoValidatePrestations extends Command
{
    protected $signature = 'gotfit:auto-validate-prestations';

    protected $description = 'Valide automatiquement les prestations payées sans litige après le délai configuré.';

    public function handle(PackPayoutService $payouts): int
    {
        $reservations = Reservation::query()
            ->where('is_paid', true)
            ->where('payment_status', 'paid')
            ->whereIn('prestation_status', ['paid', 'pending_validation'])
            ->whereNull('validated_at')
            ->whereNotNull('validation_deadline')
            ->where('validation_deadline', '<=', now())
            ->whereNull('stripe_transfer_id')
            ->get();

        foreach ($reservations as $reservation) {
            $reservation->update([
                'prestation_status' => 'validated',
                'validated_at' => now(),
                'validated_by' => null,
                'resolution_note' => 'Validation automatique après délai sans litige.',
            ]);

            $reservation->load(['client', 'intervenant', 'annonce', 'payement']);

            foreach ([$reservation->client, $reservation->intervenant] as $user) {
                if ($user && $user->email) {
                    try {
                        $user->notify(new ReservationStatusNotification(
                            $reservation,
                            'validated',
                            'La prestation a été validée automatiquement après le délai sans litige.'
                        ));
                    } catch (\Throwable $e) {
                        Log::warning('Notification auto-validation non envoyée', [
                            'reservation_id' => $reservation->id,
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $packSessions = PackSession::query()
            ->where('status', 'awaiting_client_confirmation')
            ->whereNull('validated_at')
            ->whereNotNull('validation_deadline')
            ->where('validation_deadline', '<=', now())
            ->whereNull('stripe_transfer_id')
            ->get();

        foreach ($packSessions as $session) {
            $session->update([
                'status' => 'validated',
                'validated_at' => now(),
                'validated_by' => null,
            ]);
        }

        $payoutSessions = PackSession::query()
            ->where('status', 'validated')
            ->whereIn('payout_status', ['pending', 'processing', 'failed'])
            ->whereNull('stripe_transfer_id')
            ->get();

        foreach ($payoutSessions as $session) {
            try {
                $payouts->payout($session->fresh());
            } catch (\Throwable $e) {
                Log::error('Reversement après auto-validation de séance pack impossible', [
                    'pack_session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($reservations->count().' prestation(s), '.$packSessions->count().' séance(s) de pack validée(s), '.$payoutSessions->count().' reversement(s) traité(s).');

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use App\Models\Pack;
use App\Models\PackPayout;
use App\Models\PackSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PackPayoutService
{
    public function __construct(private readonly StripeMarketplaceService $stripe) {}

    public function payout(PackSession $session): PackSession
    {
        $session = DB::transaction(function () use ($session) {
            $session = PackSession::with('pack.coach')->lockForUpdate()->findOrFail($session->id);

            if ($session->stripe_transfer_id || $session->payout_status === 'paid') {
                return $session;
            }
            if ($session->status !== 'validated') {
                throw ValidationException::withMessages(['session' => 'La séance doit être validée avant reversement.']);
            }
            if ($session->pack->status !== 'active') {
                throw ValidationException::withMessages(['pack' => 'Le pack n’est pas actif.']);
            }
            $coach = $session->pack->coach;
            if (! $coach?->stripe_account_id || ! $coach->stripe_onboarding_completed) {
                throw ValidationException::withMessages(['coach' => 'Le compte Stripe Connect du coach n’est pas prêt.']);
            }

            $session->update(['payout_status' => 'processing', 'payout_error' => null]);
            PackPayout::updateOrCreate(
                ['pack_session_id' => $session->id],
                [
                    'pack_id' => $session->pack_id,
                    'coach_id' => $session->pack->coach_id,
                    'client_id' => $session->pack->client_id,
                    'amount' => $session->amount_due,
                    'currency' => $session->pack->currency,
                    'status' => 'processing',
                    'failure_reason' => null,
                ]
            );

            return $session->fresh(['pack.coach']);
        });

        if ($session->stripe_transfer_id) {
            return $session;
        }

        try {
            $transfer = $this->stripe->transferPackSession($session);
        } catch (\Throwable $e) {
            DB::transaction(function () use ($session, $e) {
                PackSession::whereKey($session->id)->update([
                    'payout_status' => 'failed',
                    'payout_error' => $e->getMessage(),
                ]);
                PackPayout::where('pack_session_id', $session->id)->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                ]);
            });
            Log::error('Reversement d’une séance de pack impossible', [
                'pack_session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return DB::transaction(function () use ($session, $transfer) {
            $session = PackSession::lockForUpdate()->findOrFail($session->id);
            if (! $session->stripe_transfer_id) {
                $session->update([
                    'status' => 'paid',
                    'payout_status' => 'paid',
                    'stripe_transfer_id' => $transfer->id,
                    'transferred_at' => now(),
                    'payout_error' => null,
                ]);
                PackPayout::where('pack_session_id', $session->id)->update([
                    'stripe_transfer_id' => $transfer->id,
                    'status' => 'paid',
                    'transferred_at' => now(),
                    'failure_reason' => null,
                ]);

                $pack = Pack::lockForUpdate()->findOrFail($session->pack_id);
                $pack->amount_transferred = $pack->sessions()->where('payout_status', 'paid')->sum('amount_due');
                $pack->completed_sessions = $pack->sessions()->where('status', 'paid')->count();
                if ($pack->completed_sessions >= $pack->session_count) {
                    $pack->status = 'completed';
                }
                $pack->save();
            }

            return $session->fresh(['pack', 'payout']);
        });
    }
}

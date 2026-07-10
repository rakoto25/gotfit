<?php

namespace App\Http\Controllers;

use App\Models\Payement;
use App\Models\Reservation;
use App\Notifications\ReservationStatusNotification;
use App\Services\ReservationVisioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Transfer;
use Stripe\Webhook;

class PayementController extends Controller
{
    public function index()
    {
        $payments = Payement::with(['client:id,name,email', 'intervenant:id,name,email', 'reservation'])->latest()->get();

        return response()->json([
            'status' => 200,
            'payments' => $payments,
            'total' => $payments->sum('amount'),
            'totalServiceFee' => $payments->sum('service_fee'),
            'totalCommission' => $payments->sum('commission'),
            'totalGotfit' => $payments->sum('commission') + $payments->sum('service_fee'),
            'totalIntervenant' => $payments->sum('intervenant_amount'),
        ]);
    }

    public function myPayments(Request $request)
    {
        $user = $request->user();

        $query = Payement::with(['client:id,name,email', 'intervenant:id,name,email', 'reservation.annonce', 'reservation.visioSession'])
            ->latest();

        if ($user->hasRole('intervenant')) {
            $query->where('intervenant_id', $user->id);
        } else {
            $query->where('client_id', $user->id);
        }

        $payments = $query->get();

        return response()->json([
            'status' => 200,
            'payments' => $payments,
            'total' => $payments->whereIn('status', ['paid', 'transferred'])->sum('amount'),
        ]);
    }

    public function createPaymentIntent(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
        ]);

        $reservation = Reservation::with(['annonce', 'intervenant'])->findOrFail($request->reservation_id);

        if ((int) $reservation->client_id !== (int) auth()->id()) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if ($reservation->is_paid || $reservation->payment_status === 'paid') {
            return response()->json(['status' => 400, 'message' => 'Cette réservation est déjà payée'], 400);
        }

        if (in_array($reservation->prestation_status, ['disputed', 'cancelled', 'refunded'], true)) {
            return response()->json(['status' => 400, 'message' => 'Cette réservation ne peut plus être payée'], 400);
        }

        $amountInCents = (int) round(((float) $reservation->total_client_amount) * 100);

        if ($amountInCents <= 0) {
            return response()->json(['status' => 400, 'message' => 'Montant invalide'], 400);
        }

        if ($reservation->payment_intent_id && $reservation->payment_status === 'pending') {
            $existingIntent = PaymentIntent::retrieve($reservation->payment_intent_id);

            return response()->json([
                'status' => 200,
                'clientSecret' => $existingIntent->client_secret,
                'payment_intent_id' => $existingIntent->id,
                'amount' => $reservation->total_client_amount,
                'currency' => $reservation->currency ?: 'eur',
                'reservation' => $reservation,
            ]);
        }

        $paymentIntent = PaymentIntent::create([
            'amount' => $amountInCents,
            'currency' => $reservation->currency ?: 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'transfer_group' => 'reservation_' . $reservation->id,
            'metadata' => [
                'reservation_id' => $reservation->id,
                'client_id' => $reservation->client_id,
                'intervenant_id' => $reservation->intervenant_id,
                'platform_commission_amount' => $reservation->commission_amount,
                'coach_amount' => $reservation->intervenant_amount,
            ],
        ]);

        $reservation->update([
            'payment_intent_id' => $paymentIntent->id,
            'payment_status' => 'pending',
            'prestation_status' => 'pending_payment',
            'payout_status' => 'pending',
        ]);

        return response()->json([
            'status' => 200,
            'clientSecret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $reservation->total_client_amount,
            'currency' => $reservation->currency ?: 'eur',
            'reservation' => $reservation,
        ]);
    }

    public function checkPaymentStatus($paymentIntentId)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

        return response()->json([
            'status' => 200,
            'payment_status' => $paymentIntent->status,
        ]);
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (!$secret || !$sigHeader) {
            Log::warning('Stripe webhook refusé: signature ou secret manquant');
            return response()->json(['error' => 'Signature Stripe manquante'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook invalide', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook invalide'], 400);
        }

        if (($event->type ?? null) === 'payment_intent.succeeded') {
            $intent = $event->data->object;
            $reservationId = $intent->metadata->reservation_id ?? null;

            if (!$reservationId) {
                return response()->json(['error' => 'Réservation absente'], 400);
            }

            $reservation = Reservation::find($reservationId);

            if (!$reservation) {
                return response()->json(['error' => 'Réservation introuvable'], 404);
            }

            DB::transaction(function () use ($intent, $reservation) {
                $amount = ($intent->amount_received ?? $intent->amount) / 100;
                $chargeId = is_string($intent->latest_charge ?? null) ? $intent->latest_charge : null;
                Payement::updateOrCreate(
                    ['payment_intent_id' => $intent->id],
                    [
                        'reservation_id' => $reservation->id,
                        'stripe_charge_id' => $chargeId,
                        'amount' => $amount,
                        'service_fee' => $reservation->service_fee_amount,
                        'commission_rate' => $reservation->commission_rate,
                        'commission' => $reservation->commission_amount,
                        'intervenant_amount' => $reservation->intervenant_amount,
                        'net_amount' => $reservation->intervenant_amount,
                        'intervenant_id' => $reservation->intervenant_id,
                        'client_id' => $reservation->client_id,
                        'currency' => $reservation->currency ?: 'eur',
                        'status' => 'paid',
                        'payout_status' => 'pending',
                    ]
                );

                $reservation->update([
                    'is_paid' => true,
                    'payment_status' => 'paid',
                    'prestation_status' => 'paid',
                    'payout_status' => 'pending',
                    'payment_intent_id' => $intent->id,
                    'stripe_charge_id' => $chargeId,
                    'paid_at' => now(),
                    'validation_deadline' => null,
                ]);
            });

            $reservation->load(['client', 'intervenant', 'annonce', 'payement', 'visioSession']);

            try {
                app(ReservationVisioService::class)->syncPaidReservation($reservation);
            } catch (\Throwable $e) {
                Log::error('Synchronisation visio après paiement impossible', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->notifyReservationUsers($reservation->fresh(['client', 'intervenant', 'annonce', 'payement', 'visioSession']), 'paid');
        }

        if (($event->type ?? null) === 'payment_intent.payment_failed') {
            $intent = $event->data->object;
            $reservationId = $intent->metadata->reservation_id ?? null;

            if ($reservationId) {
                $reservation = Reservation::with(['client', 'intervenant', 'annonce', 'payement'])->find($reservationId);

                $reservation?->update([
                    'payment_status' => 'failed',
                    'prestation_status' => 'payment_failed',
                    'payout_status' => 'failed',
                ]);

                if ($reservation) {
                    $this->notifyReservationUsers($reservation->fresh(['client', 'intervenant', 'annonce', 'payement']), 'payment_failed');
                }
            }
        }

        if (in_array(($event->type ?? null), ['charge.dispute.created', 'charge.dispute.closed'], true)) {
            $dispute = $event->data->object;
            $payment = Payement::where('stripe_charge_id', $dispute->charge ?? null)->first();

            if ($payment && $payment->reservation) {
                $status = ($event->type === 'charge.dispute.created') ? 'disputed' : 'paid';

                $payment->reservation->update([
                    'prestation_status' => $status,
                    'payout_status' => $status === 'disputed' ? 'blocked' : $payment->reservation->payout_status,
                    'disputed_at' => $status === 'disputed' ? now() : $payment->reservation->disputed_at,
                    'dispute_reason' => $status === 'disputed' ? 'Litige Stripe ouvert' : $payment->reservation->dispute_reason,
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    public function createConnectOnboarding(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $user = $request->user();

        if (!$user->isIntervenant()) {
            return response()->json(['status' => 403, 'message' => 'Réservé aux intervenants'], 403);
        }

        if (!$user->stripe_account_id) {
                $account = Account::create([
                    'type' => 'express',
                    'email' => $user->email,
                    'country' => env('STRIPE_CONNECT_COUNTRY', 'FR'),
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers' => ['requested' => true],
                    ],
                    'metadata' => [
                        'user_id' => $user->id,
                        'platform' => 'gotfit',
                    ],
                ]);

            $user->update([
                'stripe_account_id' => $account->id,
                'stripe_onboarding_completed' => false,
            ]);
        }

            $frontendUrl = rtrim(env('FRONTEND_URL', 'https://gotfit.tech/webapp'), '/');

            $accountLink = AccountLink::create([
                'account' => $user->stripe_account_id,
                'refresh_url' => $frontendUrl . '/profile?stripe=refresh',
                'return_url' => $frontendUrl . '/profile?stripe=success',
                'type' => 'account_onboarding',
            ]);

        return response()->json([
            'status' => 200,
            'url' => $accountLink->url,
            'stripe_account_id' => $user->stripe_account_id,
        ]);
    }

    public function connectStatus(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $user = $request->user();

        if (!$user->stripe_account_id) {
            return response()->json([
                'status' => 200,
                'connected' => false,
                'onboarding_completed' => false,
            ]);
        }

        $account = Account::retrieve($user->stripe_account_id);
        $completed = (bool) ($account->charges_enabled && $account->payouts_enabled);

        $user->update([
            'stripe_onboarding_completed' => $completed,
        ]);

        return response()->json([
            'status' => 200,
            'connected' => true,
            'onboarding_completed' => $completed,
            'charges_enabled' => $account->charges_enabled,
            'payouts_enabled' => $account->payouts_enabled,
            'stripe_account_id' => $user->stripe_account_id,
        ]);
    }

    public function connectReturn()
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://gotfit.tech/webapp'), '/');

        return redirect()->away($frontendUrl . '/profile?stripe=success');
    }

    public function connectRefresh()
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://gotfit.tech/webapp'), '/');

        return redirect()->away($frontendUrl . '/profile?stripe=refresh');
    }

    public function validatePrestation(Request $request, $id)
    {
        $reservation = Reservation::with(['client', 'intervenant', 'payement'])->findOrFail($id);

        $guard = $this->ensurePrestationCanBeValidated($reservation);
        if ($guard) {
            return $guard;
        }

        $reservation->update([
            'prestation_status' => 'validated',
            'validated_at' => now(),
            'validated_by' => $request->user()?->id,
        ]);

        $reservation = $reservation->fresh(['client', 'intervenant', 'payement', 'annonce']);
        $this->notifyReservationUsers($reservation, 'validated');

        return response()->json([
            'status' => 200,
            'message' => 'Prestation validée',
            'reservation' => $reservation,
        ]);
    }

    public function confirmPrestationByClient(Request $request, $id)
    {
        $reservation = Reservation::with(['client', 'intervenant', 'payement'])->findOrFail($id);

        if ((int) $reservation->client_id !== (int) $request->user()->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $guard = $this->ensurePrestationCanBeValidated($reservation);
        if ($guard) {
            return $guard;
        }

        $reservation->update([
            'prestation_status' => 'validated',
            'validated_at' => now(),
            'validated_by' => $request->user()->id,
        ]);

        $reservation = $reservation->fresh(['client', 'intervenant', 'payement', 'annonce']);
        $this->notifyReservationUsers($reservation, 'validated');

        return response()->json([
            'status' => 200,
            'message' => 'Prestation confirmée. Le reversement coach peut être déclenché.',
            'reservation' => $reservation,
        ]);
    }

    public function disputePrestation(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:2000',
        ]);

        $reservation = Reservation::with(['client', 'intervenant', 'payement'])->findOrFail($id);

        if ((int) $reservation->client_id !== (int) $request->user()->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if (!$reservation->is_paid || $reservation->payment_status !== 'paid') {
            return response()->json(['status' => 400, 'message' => 'La réservation doit être payée avant litige'], 400);
        }

        if ($reservation->stripe_transfer_id) {
            return response()->json(['status' => 400, 'message' => 'Le reversement est déjà effectué. Contactez l’administration.'], 400);
        }

        if (in_array($reservation->prestation_status, ['validated', 'transferred', 'refunded', 'cancelled'], true)) {
            return response()->json(['status' => 400, 'message' => 'Cette prestation ne peut plus être contestée'], 400);
        }

        $reservation->update([
            'prestation_status' => 'disputed',
            'payout_status' => 'blocked',
            'disputed_at' => now(),
            'dispute_reason' => $request->reason,
        ]);

        $reservation = $reservation->fresh(['client', 'intervenant', 'payement', 'annonce']);
        $this->notifyReservationUsers($reservation, 'disputed');

        return response()->json([
            'status' => 200,
            'message' => 'Litige envoyé. Le reversement coach est bloqué en attendant la décision admin.',
            'reservation' => $reservation,
        ]);
    }

    public function transferToCoach($id)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $reservation = Reservation::with(['intervenant', 'payement'])->findOrFail($id);

        if (!$reservation->is_paid || $reservation->payment_status !== 'paid') {
            return response()->json(['status' => 400, 'message' => 'Réservation non payée'], 400);
        }

        if ($reservation->prestation_status !== 'validated') {
            return response()->json(['status' => 400, 'message' => 'Prestation non validée'], 400);
        }

        if (in_array($reservation->payout_status, ['blocked', 'refunded', 'cancelled'], true)) {
            return response()->json(['status' => 400, 'message' => 'Reversement bloqué pour cette réservation'], 400);
        }

        if ($reservation->stripe_transfer_id) {
            return response()->json(['status' => 400, 'message' => 'Reversement déjà effectué'], 400);
        }

        $coach = $reservation->intervenant;

        if (!$coach || !$coach->stripe_account_id || !$coach->stripe_onboarding_completed) {
            return response()->json(['status' => 400, 'message' => 'Compte Stripe coach non prêt'], 400);
        }

        $amountInCents = (int) round(((float) $reservation->intervenant_amount) * 100);

        if ($amountInCents <= 0) {
            return response()->json(['status' => 400, 'message' => 'Montant intervenant invalide'], 400);
        }

        try {
            $transferData = [
                'amount' => $amountInCents,
                'currency' => $reservation->currency ?: 'eur',
                'destination' => $coach->stripe_account_id,
                'transfer_group' => 'reservation_' . $reservation->id,
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'payment_intent_id' => $reservation->payment_intent_id,
                    'intervenant_id' => $reservation->intervenant_id,
                    'commission_amount' => $reservation->commission_amount,
                    'service_fee_amount' => $reservation->service_fee_amount,
                ],
            ];

            if ($reservation->stripe_charge_id) {
                $transferData['source_transaction'] = $reservation->stripe_charge_id;
            }

            $transfer = Transfer::create($transferData, [
                'idempotency_key' => 'reservation_' . $reservation->id . '_coach_transfer',
            ]);
        } catch (\Throwable $e) {
            Log::error('Erreur reversement Stripe Connect', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            $reservation->update([
                'payout_status' => 'failed',
            ]);

            if ($reservation->payement) {
                $reservation->payement->update([
                    'payout_status' => 'failed',
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Reversement impossible',
                'error' => $e->getMessage(),
            ], 400);
        }

        $reservation->update([
            'stripe_transfer_id' => $transfer->id,
            'transferred_at' => now(),
            'payout_status' => 'transferred',
            'prestation_status' => 'transferred',
        ]);

        if ($reservation->payement) {
            $reservation->payement->update([
                'stripe_transfer_id' => $transfer->id,
                'transferred_at' => now(),
                'payout_status' => 'transferred',
                'status' => 'transferred',
            ]);
        }

        $reservation = $reservation->fresh(['client', 'intervenant', 'payement', 'annonce']);
        $this->notifyReservationUsers($reservation, 'transferred');

        return response()->json([
            'status' => 200,
            'message' => 'Reversement coach effectué',
            'transfer_id' => $transfer->id,
            'reservation' => $reservation,
        ]);
    }

    public function refundReservation(Request $request, $id)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $request->validate([
            'amount' => 'nullable|numeric|min:0.5',
            'reason' => 'nullable|in:duplicate,fraudulent,requested_by_customer',
            'admin_note' => 'nullable|string|max:2000',
        ]);

        $reservation = Reservation::with(['payement'])->findOrFail($id);

        if (!$reservation->payment_intent_id || !$reservation->is_paid) {
            return response()->json(['status' => 400, 'message' => 'Aucun paiement à rembourser'], 400);
        }

        if ($reservation->payment_status === 'refunded') {
            return response()->json(['status' => 400, 'message' => 'Réservation déjà remboursée'], 400);
        }

        $amount = $request->filled('amount') ? (float) $request->amount : (float) $reservation->total_client_amount;
        $amountInCents = (int) round($amount * 100);
        $totalInCents = (int) round(((float) $reservation->total_client_amount) * 100);

        if ($amountInCents <= 0 || $amountInCents > $totalInCents) {
            return response()->json(['status' => 400, 'message' => 'Montant de remboursement invalide'], 400);
        }

        try {
            $refund = Refund::create([
                'payment_intent' => $reservation->payment_intent_id,
                'amount' => $amountInCents,
                'reason' => $request->reason ?: 'requested_by_customer',
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'admin_id' => $request->user()?->id,
                ],
            ], [
                'idempotency_key' => 'reservation_' . $reservation->id . '_refund_' . $amountInCents,
            ]);

            if ($reservation->stripe_transfer_id) {
                Transfer::createReversal($reservation->stripe_transfer_id, [
                    'amount' => min($amountInCents, (int) round(((float) $reservation->intervenant_amount) * 100)),
                    'metadata' => [
                        'reservation_id' => $reservation->id,
                        'refund_id' => $refund->id,
                    ],
                ], [
                    'idempotency_key' => 'reservation_' . $reservation->id . '_transfer_reversal_' . $refund->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Erreur remboursement Stripe', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Remboursement impossible',
                'error' => $e->getMessage(),
            ], 400);
        }

        $isFullRefund = $amountInCents === $totalInCents;

        $reservation->update([
            'payment_status' => $isFullRefund ? 'refunded' : 'partially_refunded',
            'prestation_status' => $isFullRefund ? 'refunded' : 'disputed',
            'payout_status' => $reservation->stripe_transfer_id ? 'reversed' : 'blocked',
            'refunded_at' => now(),
            'refund_reason' => $request->admin_note ?: 'Remboursement admin',
        ]);

        if ($reservation->payement) {
            $reservation->payement->update([
                'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
                'payout_status' => $reservation->stripe_transfer_id ? 'reversed' : 'blocked',
            ]);
        }

        $reservation = $reservation->fresh(['client', 'intervenant', 'payement', 'annonce', 'visioSession']);

        if ($isFullRefund) {
            app(ReservationVisioService::class)->cancelForReservation($reservation);
        }

        $this->notifyReservationUsers($reservation, 'refunded');

        return response()->json([
            'status' => 200,
            'message' => $isFullRefund ? 'Réservation remboursée' : 'Réservation remboursée partiellement',
            'refund_id' => $refund->id,
            'reservation' => $reservation,
        ]);
    }

    public function resolveDispute(Request $request, $id)
    {
        $request->validate([
            'decision' => 'required|in:validate,refund,cancel',
            'admin_note' => 'nullable|string|max:2000',
        ]);

        if ($request->decision === 'refund') {
            return $this->refundReservation($request, $id);
        }

        $reservation = Reservation::with(['client', 'intervenant', 'payement'])->findOrFail($id);

        if ($reservation->prestation_status !== 'disputed') {
            return response()->json(['status' => 400, 'message' => 'Cette réservation n’est pas en litige'], 400);
        }

        if ($request->decision === 'cancel') {
            $reservation->update([
                'prestation_status' => 'cancelled',
                'payout_status' => 'cancelled',
                'resolved_at' => now(),
                'resolution_note' => $request->admin_note,
            ]);

            $reservation = $reservation->fresh(['client', 'intervenant', 'payement', 'annonce']);
            $this->notifyReservationUsers($reservation, 'cancelled');

            return response()->json([
                'status' => 200,
                'message' => 'Litige clôturé : réservation annulée sans reversement',
                'reservation' => $reservation,
            ]);
        }

        $reservation->update([
            'prestation_status' => 'validated',
            'payout_status' => 'pending',
            'validated_at' => now(),
            'validated_by' => $request->user()?->id,
            'resolved_at' => now(),
            'resolution_note' => $request->admin_note,
        ]);

        $reservation = $reservation->fresh(['client', 'intervenant', 'payement', 'annonce']);
        $this->notifyReservationUsers($reservation, 'validated');

        return response()->json([
            'status' => 200,
            'message' => 'Litige clôturé : prestation validée',
            'reservation' => $reservation,
        ]);
    }

    public function commissionByIntervenant()
    {
        $payement = Payement::selectRaw('
            intervenant_id,
            SUM(amount) as total_paye,
            SUM(service_fee) as total_frais_client,
            SUM(commission) as total_commission,
            SUM(intervenant_amount) as total_intervenant
        ')
            ->groupBy('intervenant_id')
            ->with('intervenant:id,name,email')
            ->get();

        return response()->json(['status' => 200, 'payements' => $payement]);
    }

    public function myRevenue(Request $request)
    {
        $user = $request->user();

        $payement = Payement::where('intervenant_id', $user->id)
            ->selectRaw('
                SUM(amount) as total_paye,
                SUM(commission) as total_commission,
                SUM(intervenant_amount) as total_recu
            ')
            ->first();

        return response()->json(['status' => 200, 'payements' => $payement]);
    }

    private function ensurePrestationCanBeValidated(Reservation $reservation)
    {
        if (!$reservation->is_paid || $reservation->payment_status !== 'paid') {
            return response()->json(['status' => 400, 'message' => 'La réservation doit être payée avant validation'], 400);
        }

        if (in_array($reservation->prestation_status, ['validated', 'transferred'], true)) {
            return response()->json(['status' => 400, 'message' => 'La prestation est déjà validée ou reversée'], 400);
        }

        if (in_array($reservation->prestation_status, ['disputed', 'refunded', 'cancelled'], true)) {
            return response()->json(['status' => 400, 'message' => 'La prestation est bloquée par un litige ou un remboursement'], 400);
        }

        $reservation->loadMissing('annonce');

        if ($reservation->status !== 'realise' && !$reservation->hasSessionPassed()) {
            return response()->json(['status' => 400, 'message' => 'La prestation ne peut être validée qu’après la séance'], 400);
        }

        return null;
    }

    private function notifyReservationUsers(Reservation $reservation, string $event, ?string $message = null): void
    {
        foreach ([$reservation->client, $reservation->intervenant] as $user) {
            if ($user && $user->email) {
                try {
                    $user->notify(new ReservationStatusNotification($reservation, $event, $message));
                } catch (\Throwable $e) {
                    Log::warning('Notification réservation non envoyée', [
                        'reservation_id' => $reservation->id,
                        'user_id' => $user->id,
                        'event' => $event,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}

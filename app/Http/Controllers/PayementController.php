<?php

namespace App\Http\Controllers;

use App\Models\Payement;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\PaymentIntent;
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

    public function createPaymentIntent(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
        ]);

        $reservation = Reservation::with('annonce')->findOrFail($request->reservation_id);

        if ((int) $reservation->client_id !== (int) auth()->id()) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if ($reservation->is_paid) {
            return response()->json(['status' => 400, 'message' => 'Cette réservation est déjà payée'], 400);
        }

        $amountInCents = (int) round(((float) $reservation->total_client_amount) * 100);

        if ($amountInCents <= 0) {
            return response()->json(['status' => 400, 'message' => 'Montant invalide'], 400);
        }

        $paymentIntent = PaymentIntent::create([
            'amount' => $amountInCents,
            'currency' => $reservation->currency ?: 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'reservation_id' => $reservation->id,
                'client_id' => $reservation->client_id,
                'intervenant_id' => $reservation->intervenant_id,
            ],
        ]);

        $reservation->update([
            'payment_intent_id' => $paymentIntent->id,
            'payment_status' => 'pending',
            'prestation_status' => 'pending',
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

        try {
            $event = $secret && $sigHeader
                ? Webhook::constructEvent($payload, $sigHeader, $secret)
                : json_decode($payload);
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

            $amount = ($intent->amount_received ?? $intent->amount) / 100;

            Payement::updateOrCreate(
                ['payment_intent_id' => $intent->id],
                [
                    'reservation_id' => $reservation->id,
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
                'paid_at' => now(),
            ]);
        }

        if (($event->type ?? null) === 'payment_intent.payment_failed') {
            $intent = $event->data->object;
            $reservationId = $intent->metadata->reservation_id ?? null;

            if ($reservationId) {
                Reservation::where('id', $reservationId)->update([
                    'payment_status' => 'failed',
                    'prestation_status' => 'payment_failed',
                    'payout_status' => 'failed',
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
            ]);

            $user->update([
                'stripe_account_id' => $account->id,
                'stripe_onboarding_completed' => false,
            ]);
        }

        $accountLink = AccountLink::create([
            'account' => $user->stripe_account_id,
            'refresh_url' => config('app.url') . '/api/stripe/connect/refresh',
            'return_url' => config('app.url') . '/api/stripe/connect/return',
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
        return response()->json([
            'status' => 200,
            'message' => 'Onboarding Stripe terminé. Vous pouvez revenir dans Gotfit.',
        ]);
    }

    public function connectRefresh()
    {
        return response()->json([
            'status' => 200,
            'message' => 'Lien Stripe expiré. Veuillez relancer la connexion Stripe depuis Gotfit.',
        ]);
    }

    public function validatePrestation($id)
    {
        $reservation = Reservation::with(['intervenant', 'payement'])->findOrFail($id);

        if (!$reservation->is_paid || $reservation->payment_status !== 'paid') {
            return response()->json(['status' => 400, 'message' => 'La réservation doit être payée avant validation'], 400);
        }

        if ($reservation->prestation_status === 'transferred') {
            return response()->json(['status' => 400, 'message' => 'La prestation a déjà été reversée'], 400);
        }

        $reservation->update([
            'prestation_status' => 'validated',
            'validated_at' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Prestation validée',
            'reservation' => $reservation->fresh(['client', 'intervenant', 'payement']),
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
            $transfer = Transfer::create([
                'amount' => $amountInCents,
                'currency' => $reservation->currency ?: 'eur',
                'destination' => $coach->stripe_account_id,
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'payment_intent_id' => $reservation->payment_intent_id,
                    'intervenant_id' => $reservation->intervenant_id,
                ],
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

        return response()->json([
            'status' => 200,
            'message' => 'Reversement coach effectué',
            'transfer_id' => $transfer->id,
            'reservation' => $reservation->fresh(['client', 'intervenant', 'payement']),
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
}

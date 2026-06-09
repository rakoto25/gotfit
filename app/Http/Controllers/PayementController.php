<?php

namespace App\Http\Controllers;

use App\Models\Payement;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use Stripe\Stripe;
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
                ]
            );

            $reservation->update([
                'is_paid' => true,
                'payment_status' => 'paid',
                'payment_intent_id' => $intent->id,
                'paid_at' => now(),
            ]);
        }

        if (($event->type ?? null) === 'payment_intent.payment_failed') {
            $intent = $event->data->object;
            $reservationId = $intent->metadata->reservation_id ?? null;
            if ($reservationId) {
                Reservation::where('id', $reservationId)->update(['payment_status' => 'failed']);
            }
        }

        return response()->json(['success' => true]);
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

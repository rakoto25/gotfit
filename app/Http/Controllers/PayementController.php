<?php

namespace App\Http\Controllers;

use App\Models\Payement;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PayementController extends Controller
{
    public function index()
    {
        $payments = Payement::latest()->get();

        $total = $payments->sum('amount');
        $totalCommission = $payments->sum('commission');
        $totalNet = $payments->sum('net_amount');

        return response()->json([
            'status' => 200,
            'payemnts' => $payments,
            'total' => $total,
            'totalCommission' => $totalCommission,
            'totalNet' => $totalNet,
        ]);

    }

    public function createPaymentIntent(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $request->validate([
            'amount' => 'required|integer',
            'reservation_id' => 'required|exists:reservations,id',
        ]);

        $paymentIntent = PaymentIntent::create([
            'amount' => $request->amount,
            'currency' => 'usd',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => [
                'reservation_id' => $request->reservation_id,
                'client_id' => auth()->id(),
            ]
        ]);

        return response()->json([
            'status' => 200,
            'clientSecret' => $paymentIntent->client_secret,
        ]);
    }

    public function checkPaymentStatus($paymentIntentId)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

        return response()->json([
            'status' => $paymentIntent->status
        ]);
    }

    public function handleWebhook(Request $request)
    {
        $event = json_decode($request->getContent());

        if ($event->type === 'payment_intent.succeeded') {

            $intent = $event->data->object;

            $amount = $intent->amount / 100;

            $reservationId = $intent->metadata->reservation_id ?? null;

            if (!$reservationId) {
                return response()->json(['error' => 'No reservation'], 400);
            }

            $reservation = Reservation::find($reservationId);

            if (!$reservation) {
                return response()->json(['error' => 'Reservation not found'], 404);
            }

            // 💰 Commission
            $commissionRate = 0.2;
            $commission = $amount * $commissionRate;
            $intervenantAmount = $amount - $commission;

            // 🔥 Sauvegarde paiement
            Payement::create([
                'payment_intent_id' => $intent->id,
                'amount' => $amount,
                'commission' => $commission,
                'intervenant_amount' => $intervenantAmount,
                'intervenant_id' => $reservation->intervenant_id,
                'status' => 'paid',
            ]);

            // ✅ UPDATE RESERVATION
            $reservation->update([
                'is_paid' => true,
                'payment_status' => 'paid',
            ]);
        }

        return response()->json(['success' => true]);
    }


    public function commissionByIntervenant()
    {
        $payement = Payement::selectRaw('
            intervenant_id,
            SUM(amount) as total_paye,
            SUM(commission) as total_commission,
            SUM(intervenant_amount) as total_intervenant
        ')
        ->groupBy('intervenant_id')
        ->with('intervenant:id,name')
        ->get();

        return response()->json([
            'status' => 200,
            'payements' => $payement,
        ]);
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

        return response()->json([
            'status' => 200,
            'payements' => $payement,
        ]);
    }

}

<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Offer;
use App\Models\Pack;
use App\Models\Payement;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackPaymentService
{
    public function completeOfferPayment(
        Offer $offer,
        string $paymentIntentId,
        int $amountReceived,
        string $currency,
        ?string $chargeId = null
    ): Pack {
        return DB::transaction(function () use (
            $offer, $paymentIntentId, $amountReceived, $currency, $chargeId
        ) {
            $offer = Offer::lockForUpdate()->findOrFail($offer->id);
            $currency = strtolower($currency);

            if ($amountReceived !== $offer->amount_total || $currency !== strtolower($offer->currency)) {
                throw ValidationException::withMessages([
                    'payment' => 'Le montant ou la devise Stripe ne correspond pas à l’offre.',
                ]);
            }

            $existing = Pack::where('stripe_payment_intent_id', $paymentIntentId)->first();
            if ($existing) {
                if ($chargeId && ! $existing->stripe_charge_id) {
                    $existing->update(['stripe_charge_id' => $chargeId]);
                    Payement::where('payment_intent_id', $paymentIntentId)
                        ->whereNull('stripe_charge_id')
                        ->update(['stripe_charge_id' => $chargeId]);
                }

                return $existing->load('sessions');
            }

            if ($offer->status === 'paid' && $offer->pack) {
                return $offer->pack->load('sessions');
            }

            if (! in_array($offer->status, ['sent', 'expired'], true)) {
                throw ValidationException::withMessages([
                    'offer' => 'Cette offre ne peut plus être payée.',
                ]);
            }

            $commissionRate = (float) BusinessSetting::value('intervenant_commission_rate', 12);
            $commissionAmount = (int) round($offer->amount_total * $commissionRate / 100);
            $coachNet = $offer->amount_total - $commissionAmount;

            $pack = Pack::create([
                'offer_id' => $offer->id,
                'client_id' => $offer->client_id,
                'coach_id' => $offer->coach_id,
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_charge_id' => $chargeId,
                'amount_total' => $offer->amount_total,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'coach_net_amount' => $coachNet,
                'session_count' => $offer->session_count,
                'currency' => $currency,
                'status' => 'active',
                'paid_at' => now(),
            ]);

            $baseAmount = intdiv($coachNet, $offer->session_count);
            $remainder = $coachNet % $offer->session_count;
            for ($sequence = 1; $sequence <= $offer->session_count; $sequence++) {
                $pack->sessions()->create([
                    'sequence' => $sequence,
                    'amount_due' => $baseAmount + ($sequence <= $remainder ? 1 : 0),
                    'status' => 'pending',
                    'payout_status' => 'pending',
                ]);
            }

            Payement::updateOrCreate(
                ['payment_intent_id' => $paymentIntentId],
                [
                    'offer_id' => $offer->id,
                    'pack_id' => $pack->id,
                    'reservation_id' => null,
                    'stripe_charge_id' => $chargeId,
                    'amount' => $amountReceived / 100,
                    'service_fee' => 0,
                    'commission_rate' => $commissionRate,
                    'commission' => $commissionAmount / 100,
                    'intervenant_amount' => $coachNet / 100,
                    'net_amount' => $coachNet / 100,
                    'intervenant_id' => $offer->coach_id,
                    'client_id' => $offer->client_id,
                    'currency' => $currency,
                    'status' => 'paid',
                    'payout_status' => 'pending',
                ]
            );

            $offer->update([
                'status' => 'paid',
                'stripe_payment_intent_id' => $paymentIntentId,
                'paid_at' => now(),
            ]);

            $this->creditCashback($pack);

            return $pack->load('sessions');
        });
    }

    public function reconcileRefund(string $paymentIntentId, int $amountRefunded): ?Pack
    {
        return DB::transaction(function () use ($paymentIntentId, $amountRefunded) {
            $pack = Pack::where('stripe_payment_intent_id', $paymentIntentId)
                ->lockForUpdate()
                ->first();
            if (! $pack) {
                return null;
            }

            $refunded = max(0, min($amountRefunded, $pack->amount_total));
            $pack->status = $refunded >= $pack->amount_total ? 'refunded' : 'partially_refunded';
            $pack->save();

            $payment = Payement::where('payment_intent_id', $paymentIntentId)->first();
            if ($payment) {
                $payment->update([
                    'status' => $pack->status,
                    'payout_status' => $pack->status === 'refunded' ? 'refunded' : $payment->payout_status,
                ]);
            }

            $credited = WalletTransaction::where('pack_id', $pack->id)
                ->where('type', 'cashback_credit')->sum('amount');
            $alreadyReversed = WalletTransaction::where('pack_id', $pack->id)
                ->where('type', 'cashback_reversal')->sum('amount');
            $eligibleAfterRefund = (int) round(($pack->amount_total - $refunded) * 0.01);
            $toReverse = max(0, $credited - $alreadyReversed - $eligibleAfterRefund);

            if ($toReverse > 0) {
                $wallet = Wallet::where('user_id', $pack->client_id)->lockForUpdate()->firstOrFail();
                $actual = min($toReverse, $wallet->balance);
                if ($actual > 0) {
                    $wallet->decrement('balance', $actual);
                    $wallet->refresh();
                    WalletTransaction::firstOrCreate([
                        'idempotency_key' => 'cashback_refund:'.$paymentIntentId.':'.$refunded,
                    ], [
                        'wallet_id' => $wallet->id,
                        'pack_id' => $pack->id,
                        'payment_intent_id' => $paymentIntentId,
                        'type' => 'cashback_reversal',
                        'amount' => $actual,
                        'balance_after' => $wallet->balance,
                        'metadata' => ['amount_refunded' => $refunded],
                    ]);
                }
            }

            return $pack;
        });
    }

    private function creditCashback(Pack $pack): void
    {
        $cashback = (int) round($pack->amount_total * 0.01);
        if ($cashback <= 0) {
            return;
        }

        $key = 'cashback_payment:'.$pack->stripe_payment_intent_id;
        if (WalletTransaction::where('idempotency_key', $key)->exists()) {
            return;
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $pack->client_id],
            ['balance' => 0, 'currency' => $pack->currency]
        );
        $wallet = Wallet::lockForUpdate()->findOrFail($wallet->id);
        $wallet->increment('balance', $cashback);
        $wallet->refresh();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'pack_id' => $pack->id,
            'payment_intent_id' => $pack->stripe_payment_intent_id,
            'type' => 'cashback_credit',
            'amount' => $cashback,
            'balance_after' => $wallet->balance,
            'idempotency_key' => $key,
            'metadata' => ['rate' => 1],
        ]);
    }
}

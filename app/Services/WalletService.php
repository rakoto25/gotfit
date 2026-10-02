<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Pack;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function applyToOffer(Offer $offer, int $amount): Offer
    {
        return DB::transaction(function () use ($offer, $amount) {
            $offer = Offer::lockForUpdate()->findOrFail($offer->id);
            if ($offer->stripe_checkout_session_id) {
                if ($amount !== $offer->wallet_amount_applied) {
                    throw ValidationException::withMessages([
                        'wallet_amount' => 'Le montant wallet ne peut plus changer après création du paiement.',
                    ]);
                }

                return $offer;
            }

            $amount = max(0, $amount);
            if ($amount > max(0, $offer->amount_total - 50)) {
                throw ValidationException::withMessages([
                    'wallet_amount' => 'Le paiement Stripe restant doit être d’au moins 0,50 €.',
                ]);
            }

            if ($amount > 0) {
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $offer->client_id],
                    ['balance' => 0, 'currency' => $offer->currency]
                );
                $wallet = Wallet::lockForUpdate()->findOrFail($wallet->id);
                if ($wallet->balance < $amount) {
                    throw ValidationException::withMessages([
                        'wallet_amount' => 'Le solde de la cagnotte est insuffisant.',
                    ]);
                }

                $attempt = WalletTransaction::where('offer_id', $offer->id)
                    ->where('type', 'purchase_debit')->count() + 1;
                $wallet->decrement('balance', $amount);
                $wallet->refresh();
                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'offer_id' => $offer->id,
                    'type' => 'purchase_debit',
                    'amount' => $amount,
                    'balance_after' => $wallet->balance,
                    'idempotency_key' => 'wallet_offer_debit:'.$offer->id.':'.$attempt,
                    'metadata' => ['state' => 'reserved'],
                ]);
            }

            $offer->update([
                'wallet_amount_applied' => $amount,
                'stripe_amount_due' => $offer->amount_total - $amount,
            ]);

            return $offer->fresh();
        });
    }

    public function releaseOfferDebit(Offer $offer, string $reason): void
    {
        DB::transaction(function () use ($offer, $reason) {
            $offer = Offer::lockForUpdate()->findOrFail($offer->id);
            if ($offer->status === 'paid' || $offer->wallet_amount_applied <= 0) {
                return;
            }

            $debit = WalletTransaction::where('offer_id', $offer->id)
                ->where('type', 'purchase_debit')
                ->whereNull('pack_id')
                ->latest('id')
                ->first();
            if (! $debit) {
                return;
            }
            $releaseKey = 'wallet_offer_release:'.$debit->id;
            if (WalletTransaction::where('idempotency_key', $releaseKey)->exists()) {
                return;
            }

            $wallet = Wallet::lockForUpdate()->findOrFail($debit->wallet_id);
            $wallet->increment('balance', $debit->amount);
            $wallet->refresh();
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'offer_id' => $offer->id,
                'type' => 'purchase_release',
                'amount' => $debit->amount,
                'balance_after' => $wallet->balance,
                'idempotency_key' => $releaseKey,
                'metadata' => ['offer_id' => $offer->id, 'reason' => $reason],
            ]);
            $offer->update([
                'wallet_amount_applied' => 0,
                'stripe_amount_due' => null,
                'stripe_checkout_session_id' => null,
                'stripe_checkout_url' => null,
            ]);
        });
    }

    public function markOfferDebitCaptured(Offer $offer, int $packId): void
    {
        $transaction = WalletTransaction::where('offer_id', $offer->id)
            ->where('type', 'purchase_debit')
            ->whereNull('pack_id')
            ->latest('id')
            ->first();
        if ($transaction) {
            $transaction->update(['pack_id' => $packId, 'metadata' => [
                'offer_id' => $offer->id,
                'state' => 'captured',
            ]]);
        }
    }

    public function refundPackDebit(Pack $pack): void
    {
        if ($pack->wallet_amount_used <= 0) {
            return;
        }

        DB::transaction(function () use ($pack) {
            $key = 'wallet_pack_refund:'.$pack->id;
            if (WalletTransaction::where('idempotency_key', $key)->exists()) {
                return;
            }

            $wallet = Wallet::where('user_id', $pack->client_id)->lockForUpdate()->firstOrFail();
            $wallet->increment('balance', $pack->wallet_amount_used);
            $wallet->refresh();
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'pack_id' => $pack->id,
                'payment_intent_id' => $pack->stripe_payment_intent_id,
                'type' => 'purchase_refund',
                'amount' => $pack->wallet_amount_used,
                'balance_after' => $wallet->balance,
                'idempotency_key' => $key,
                'metadata' => ['offer_id' => $pack->offer_id],
            ]);
        });
    }
}

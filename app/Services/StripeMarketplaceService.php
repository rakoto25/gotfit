<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\PackSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Stripe;
use Stripe\Transfer;

class StripeMarketplaceService
{
    public function createOfferCheckout(Offer $offer): object
    {
        Stripe::setApiKey((string) config('services.stripe.secret'));

        $returnUrls = $this->offerReturnUrls($offer);

        $data = [
            'mode' => 'payment',
            'success_url' => $returnUrls['success_url'],
            'cancel_url' => $returnUrls['cancel_url'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $offer->currency,
                    'unit_amount' => $offer->stripe_amount_due ?? $offer->amount_total,
                    'product_data' => [
                        'name' => $offer->title,
                        'description' => $offer->session_count.' séance(s) GotFit',
                    ],
                ],
            ]],
            'payment_intent_data' => [
                'transfer_group' => 'offer_'.$offer->id,
                'metadata' => $this->offerMetadata($offer),
            ],
            'metadata' => $this->offerMetadata($offer),
            'expires_at' => min(
                $offer->expires_at?->timestamp ?? now()->addDay()->timestamp,
                now()->addHours(24)->timestamp
            ),
        ];

        if ($offer->client?->email) {
            $data['customer_email'] = $offer->client->email;
        }

        return CheckoutSession::create($data, [
            'idempotency_key' => 'offer_'.$offer->id.'_checkout_'.$offer->wallet_amount_applied.'_v1',
        ]);
    }

    public function transferPackSession(PackSession $session): object
    {
        Stripe::setApiKey((string) config('services.stripe.secret'));
        $session->loadMissing('pack.coach');
        $pack = $session->pack;

        $data = [
            'amount' => $session->amount_due,
            'currency' => $pack->currency,
            'destination' => $pack->coach->stripe_account_id,
            'transfer_group' => 'pack_'.$pack->id,
            'metadata' => [
                'pack_id' => (string) $pack->id,
                'pack_session_id' => (string) $session->id,
                'session_sequence' => (string) $session->sequence,
                'offer_id' => (string) $pack->offer_id,
            ],
        ];

        if ($pack->stripe_charge_id) {
            $data['source_transaction'] = $pack->stripe_charge_id;
        }

        return Transfer::create($data, [
            'idempotency_key' => 'pack_session_'.$session->id.'_transfer_v1',
        ]);
    }

    public function offerReturnUrls(Offer $offer): array
    {
        $frontendUrl = rtrim((string) config('services.stripe.frontend_url'), '/');
        $query = 'conversation_id='.$offer->conversation_id.'&offer='.$offer->id;

        return [
            'success_url' => $frontendUrl.'/messages?'.$query
                .'&payment=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontendUrl.'/messages?'.$query.'&payment=cancelled',
        ];
    }

    private function offerMetadata(Offer $offer): array
    {
        return [
            'offer_id' => (string) $offer->id,
            'conversation_id' => (string) $offer->conversation_id,
            'client_id' => (string) $offer->client_id,
            'coach_id' => (string) $offer->coach_id,
            'session_count' => (string) $offer->session_count,
            'wallet_amount_applied' => (string) $offer->wallet_amount_applied,
            'offer_amount_total' => (string) $offer->amount_total,
        ];
    }
}

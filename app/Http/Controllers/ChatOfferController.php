<?php

namespace App\Http\Controllers;

use App\Jobs\SendExpoPushNotification;
use App\Models\Conversations;
use App\Models\Message;
use App\Models\Offer;
use App\Services\StripeMarketplaceService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatOfferController extends Controller
{
    public function __construct(private readonly StripeMarketplaceService $stripe) {}

    public function store(Request $request, Conversations $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        abort_unless((int) $conversation->intervenant_id === (int) $request->user()->id, 403,
            'Seul le coach de la conversation peut créer une offre.');

        $data = $request->validate([
            'title' => 'required|string|max:160',
            'session_count' => 'required|integer|min:1|max:100',
            'amount' => 'required|numeric|min:0.50|max:999999.99',
            'description' => 'nullable|string|max:3000',
            'validity_days' => 'nullable|integer|min:1|max:90',
        ]);

        [$offer, $message] = DB::transaction(function () use ($data, $conversation, $request) {
            $offer = Offer::create([
                'conversation_id' => $conversation->id,
                'coach_id' => $request->user()->id,
                'client_id' => $conversation->client_id,
                'title' => trim($data['title']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'session_count' => $data['session_count'],
                'amount_total' => (int) round(((float) $data['amount']) * 100),
                'currency' => 'eur',
                'status' => 'sent',
                'expires_at' => now()->addDays($data['validity_days'] ?? 7),
            ]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $request->user()->id,
                'receiver_id' => $conversation->client_id,
                'offer_id' => $offer->id,
                'message' => $offer->title,
                'type' => 'offer',
            ]);

            return [$offer, $message];
        });

        SendExpoPushNotification::dispatch(
            $conversation->client_id,
            'Nouvelle offre GotFit',
            $offer->title.' · '.$offer->session_count.' séance(s) · '.number_format($offer->amount_major, 2, ',', ' ').' €',
            ['type' => 'offer', 'conversation_id' => $conversation->id, 'offer_id' => $offer->id]
        );

        return response()->json([
            'status' => 201,
            'offer' => $offer->load(['coach:id,name', 'client:id,name', 'pack.sessions']),
            'message' => $message->load(['sender', 'offer.pack.sessions']),
        ], 201);
    }

    public function show(Request $request, Offer $offer)
    {
        $this->authorizeConversation($request, $offer->conversation);

        return response()->json([
            'status' => 200,
            'offer' => $this->expireIfNeeded($offer)->load(['coach:id,name', 'client:id,name', 'pack.sessions']),
        ]);
    }

    public function checkout(Request $request, Offer $offer, WalletService $wallets)
    {
        $this->authorizeConversation($request, $offer->conversation);
        abort_unless((int) $offer->client_id === (int) $request->user()->id, 403,
            'Seul le client destinataire peut payer cette offre.');

        $offer = $this->expireIfNeeded($offer);
        if ($offer->status === 'paid') {
            return response()->json([
                'status' => 200,
                'already_paid' => true,
                'offer' => $offer->load('pack.sessions'),
            ]);
        }
        if ($offer->status !== 'sent') {
            return response()->json(['status' => 409, 'message' => 'Cette offre n’est plus payable.'], 409);
        }
        if (! config('services.stripe.secret')) {
            return response()->json(['status' => 503, 'message' => 'Stripe n’est pas configuré.'], 503);
        }

        $data = $request->validate([
            'wallet_amount' => 'nullable|numeric|min:0',
        ]);
        $walletAmount = (int) round(((float) ($data['wallet_amount'] ?? 0)) * 100);

        if ($offer->stripe_checkout_session_id && $offer->stripe_checkout_url) {
            if ($walletAmount !== $offer->wallet_amount_applied) {
                return response()->json([
                    'status' => 409,
                    'message' => 'Une session de paiement existe déjà avec un autre montant wallet.',
                ], 409);
            }

            return response()->json([
                'status' => 200,
                'checkout_url' => $offer->stripe_checkout_url,
                'checkout_session_id' => $offer->stripe_checkout_session_id,
                'offer' => $offer,
            ]);
        }

        $offer = $wallets->applyToOffer($offer, $walletAmount);

        $offer->loadMissing('client');
        try {
            $session = $this->stripe->createOfferCheckout($offer);
        } catch (\Throwable $e) {
            $wallets->releaseOfferDebit($offer, 'stripe_checkout_failed');
            report($e);

            return response()->json(['status' => 502, 'message' => 'Stripe est momentanément indisponible.'], 502);
        }

        $offer->update([
            'stripe_checkout_session_id' => $session->id,
            'stripe_checkout_url' => $session->url,
        ]);

        return response()->json([
            'status' => 200,
            'checkout_url' => $session->url,
            'checkout_session_id' => $session->id,
            'offer' => $offer->fresh(),
        ]);
    }

    public function cancel(Request $request, Offer $offer, WalletService $wallets)
    {
        $this->authorizeConversation($request, $offer->conversation);
        abort_unless((int) $offer->coach_id === (int) $request->user()->id, 403);

        if ($offer->status === 'paid') {
            return response()->json(['status' => 409, 'message' => 'Une offre payée ne peut pas être annulée.'], 409);
        }
        $wallets->releaseOfferDebit($offer, 'offer_cancelled');
        $offer->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return response()->json(['status' => 200, 'offer' => $offer->fresh()]);
    }

    private function expireIfNeeded(Offer $offer): Offer
    {
        if ($offer->status === 'sent' && $offer->expires_at?->isPast()) {
            $offer->update(['status' => 'expired']);
        }

        return $offer->fresh();
    }

    private function authorizeConversation(Request $request, Conversations $conversation): void
    {
        $user = $request->user();
        abort_unless($user, 401);
        if ($user->hasRole('admin')) {
            return;
        }
        abort_unless(
            (int) $conversation->client_id === (int) $user->id
            || (int) $conversation->intervenant_id === (int) $user->id,
            403,
            'Non autorisé'
        );
    }
}

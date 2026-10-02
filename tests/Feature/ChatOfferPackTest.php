<?php

namespace Tests\Feature;

use App\Models\Conversations;
use App\Models\Offer;
use App\Models\Role;
use App\Models\User;
use App\Services\PackPaymentService;
use App\Services\StripeMarketplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class ChatOfferPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_creates_an_offer_directly_in_the_chat(): void
    {
        Queue::fake();
        [$client, $coach, $conversation] = $this->participants();
        Sanctum::actingAs($coach);

        $this->postJson("/api/conversations/{$conversation->id}/offers", [
            'title' => 'Coaching personnalisé',
            'session_count' => 5,
            'amount' => 135,
            'description' => 'Programme sur mesure',
            'validity_days' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('offer.status', 'sent')
            ->assertJsonPath('offer.amount_total', 13500)
            ->assertJsonPath('message.type', 'offer');

        $this->assertDatabaseHas('offers', [
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'session_count' => 5,
            'amount_total' => 13500,
        ]);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'type' => 'offer']);
    }

    public function test_confirmed_payment_creates_one_pack_sessions_payment_and_cashback_idempotently(): void
    {
        [$client, $coach, $conversation] = $this->participants();
        $offer = $this->offer($conversation, $client, $coach);
        $service = app(PackPaymentService::class);

        $first = $service->completeOfferPayment($offer, 'pi_offer_135', 13500, 'eur', 'ch_offer_135');
        $second = $service->completeOfferPayment($offer, 'pi_offer_135', 13500, 'eur', 'ch_offer_135');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('packs', 1);
        $this->assertDatabaseCount('pack_sessions', 5);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(11880, (int) $first->sessions()->sum('amount_due'));
        $this->assertDatabaseHas('wallets', ['user_id' => $client->id, 'balance' => 135]);
        $this->assertDatabaseHas('offers', ['id' => $offer->id, 'status' => 'paid']);
    }

    public function test_client_receives_a_stripe_checkout_url_for_the_offer(): void
    {
        [$client, $coach, $conversation] = $this->participants();
        $offer = $this->offer($conversation, $client, $coach);
        config(['services.stripe.secret' => 'sk_test_fake']);
        $this->mock(StripeMarketplaceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createOfferCheckout')->once()->andReturn((object) [
                'id' => 'cs_test_offer',
                'url' => 'https://checkout.stripe.test/session',
            ]);
        });
        Sanctum::actingAs($client);

        $this->postJson("/api/offers/{$offer->id}/checkout")
            ->assertOk()
            ->assertJsonPath('checkout_session_id', 'cs_test_offer')
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.test/session');

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'stripe_checkout_session_id' => 'cs_test_offer',
            'status' => 'sent',
        ]);
    }

    public function test_client_validation_releases_only_that_session_once(): void
    {
        [$client, $coach, $conversation] = $this->participants();
        $coach->update(['stripe_account_id' => 'acct_coach', 'stripe_onboarding_completed' => true]);
        $pack = app(PackPaymentService::class)->completeOfferPayment(
            $this->offer($conversation, $client, $coach), 'pi_payout', 13500, 'eur', 'ch_payout'
        );
        $session = $pack->sessions()->first();
        $session->update(['status' => 'awaiting_client_confirmation', 'completed_at' => now()]);

        $this->mock(StripeMarketplaceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferPackSession')->once()->andReturn((object) ['id' => 'tr_session_1']);
        });
        Sanctum::actingAs($client);

        $this->postJson("/api/pack-sessions/{$session->id}/validate")
            ->assertOk()
            ->assertJsonPath('session.status', 'paid')
            ->assertJsonPath('session.stripe_transfer_id', 'tr_session_1');
        $this->postJson("/api/pack-sessions/{$session->id}/validate")
            ->assertOk()
            ->assertJsonPath('already_paid', true);

        $this->assertDatabaseCount('pack_payouts', 1);
        $this->assertDatabaseHas('packs', [
            'id' => $pack->id,
            'completed_sessions' => 1,
            'amount_transferred' => $session->amount_due,
        ]);
    }

    public function test_refund_removes_cashback_and_keeps_history(): void
    {
        [$client, $coach, $conversation] = $this->participants();
        $service = app(PackPaymentService::class);
        $pack = $service->completeOfferPayment(
            $this->offer($conversation, $client, $coach), 'pi_refund_pack', 13500, 'eur'
        );

        $service->reconcileRefund('pi_refund_pack', 13500);

        $this->assertDatabaseHas('packs', ['id' => $pack->id, 'status' => 'refunded']);
        $this->assertDatabaseHas('wallets', ['user_id' => $client->id, 'balance' => 0]);
        $this->assertDatabaseHas('wallet_transactions', [
            'pack_id' => $pack->id, 'type' => 'cashback_reversal', 'amount' => 135,
        ]);
    }

    private function participants(): array
    {
        $clientRole = Role::create(['name' => 'Client', 'slug' => 'client', 'is_active' => true]);
        $coachRole = Role::create(['name' => 'Intervenant', 'slug' => 'intervenant', 'is_active' => true]);
        $client = User::factory()->create();
        $coach = User::factory()->create();
        $client->roles()->attach($clientRole);
        $coach->roles()->attach($coachRole);
        $conversation = Conversations::create(['client_id' => $client->id, 'intervenant_id' => $coach->id]);

        return [$client, $coach, $conversation];
    }

    private function offer(Conversations $conversation, User $client, User $coach): Offer
    {
        return Offer::create([
            'conversation_id' => $conversation->id,
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'title' => 'Coaching personnalisé',
            'description' => 'Programme sur mesure',
            'session_count' => 5,
            'amount_total' => 13500,
            'currency' => 'eur',
            'status' => 'sent',
            'expires_at' => now()->addWeek(),
        ]);
    }
}

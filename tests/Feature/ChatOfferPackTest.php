<?php

namespace Tests\Feature;

use App\Models\Conversations;
use App\Models\Offer;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
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

    public function test_client_can_apply_wallet_credit_to_an_offer_and_cashback_uses_stripe_amount(): void
    {
        [$client, $coach, $conversation] = $this->participants();
        $offer = $this->offer($conversation, $client, $coach);
        Wallet::create(['user_id' => $client->id, 'balance' => 500, 'currency' => 'eur']);
        config(['services.stripe.secret' => 'sk_test_fake']);
        $this->mock(StripeMarketplaceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createOfferCheckout')->once()->withArgs(function ($offer) {
                return $offer->wallet_amount_applied === 100 && $offer->stripe_amount_due === 13400;
            })->andReturn((object) [
                'id' => 'cs_wallet_offer',
                'url' => 'https://checkout.stripe.test/wallet',
            ]);
        });
        Sanctum::actingAs($client);

        $this->postJson("/api/offers/{$offer->id}/checkout", ['wallet_amount' => 1])
            ->assertOk()
            ->assertJsonPath('offer.wallet_amount_applied', 100)
            ->assertJsonPath('offer.stripe_amount_due', 13400);
        $this->postJson("/api/offers/{$offer->id}/checkout", ['wallet_amount' => 1])
            ->assertOk()
            ->assertJsonPath('checkout_session_id', 'cs_wallet_offer');

        $this->assertDatabaseHas('wallets', ['user_id' => $client->id, 'balance' => 400]);
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'purchase_debit', 'amount' => 100]);

        $pack = app(PackPaymentService::class)->completeOfferPayment(
            $offer->fresh(), 'pi_wallet_offer', 13400, 'eur'
        );
        $this->assertSame(100, $pack->wallet_amount_used);
        $this->assertSame(13400, $pack->stripe_amount_paid);
        $this->assertDatabaseHas('wallets', ['user_id' => $client->id, 'balance' => 534]);
        $this->assertDatabaseHas('wallet_transactions', [
            'pack_id' => $pack->id, 'type' => 'cashback_credit', 'amount' => 134,
        ]);

        app(PackPaymentService::class)->reconcileRefund('pi_wallet_offer', 13400);
        $this->assertDatabaseHas('wallets', ['user_id' => $client->id, 'balance' => 500]);
        $this->assertDatabaseHas('wallet_transactions', [
            'pack_id' => $pack->id, 'type' => 'purchase_refund', 'amount' => 100,
        ]);
    }

    public function test_checkout_failure_releases_reserved_wallet_amount(): void
    {
        [$client, $coach, $conversation] = $this->participants();
        $offer = $this->offer($conversation, $client, $coach);
        Wallet::create(['user_id' => $client->id, 'balance' => 500, 'currency' => 'eur']);
        config(['services.stripe.secret' => 'sk_test_fake']);
        $this->mock(StripeMarketplaceService::class, function (MockInterface $mock) {
            $attempt = 0;
            $mock->shouldReceive('createOfferCheckout')->twice()->andReturnUsing(function () use (&$attempt) {
                $attempt++;
                if ($attempt === 1) {
                    throw new \RuntimeException('Stripe indisponible');
                }

                return (object) [
                    'id' => 'cs_retry_wallet', 'url' => 'https://checkout.stripe.test/retry',
                ];
            });
        });
        Sanctum::actingAs($client);

        $this->postJson("/api/offers/{$offer->id}/checkout", ['wallet_amount' => 2])
            ->assertStatus(502);

        $this->assertDatabaseHas('wallets', ['user_id' => $client->id, 'balance' => 500]);
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'purchase_release', 'amount' => 200]);
        $this->assertDatabaseHas('offers', [
            'id' => $offer->id, 'wallet_amount_applied' => 0, 'stripe_checkout_session_id' => null,
        ]);

        $this->postJson("/api/offers/{$offer->id}/checkout", ['wallet_amount' => 2])
            ->assertOk()
            ->assertJsonPath('checkout_session_id', 'cs_retry_wallet');
        $this->assertDatabaseHas('wallets', ['user_id' => $client->id, 'balance' => 300]);
        $this->assertDatabaseCount('wallet_transactions', 3);
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

    public function test_early_cancellation_releases_session_but_late_client_cancellation_pays_coach(): void
    {
        [$client, $coach, $conversation] = $this->participants();
        $coach->update(['stripe_account_id' => 'acct_policy', 'stripe_onboarding_completed' => true]);
        $pack = app(PackPaymentService::class)->completeOfferPayment(
            $this->offer($conversation, $client, $coach), 'pi_policy', 13500, 'eur', 'ch_policy'
        );
        $early = $pack->sessions()->first();
        $early->update(['scheduled_at' => now()->addDays(3)]);
        Sanctum::actingAs($client);

        $this->postJson("/api/pack-sessions/{$early->id}/cancel", ['reason' => 'Indisponible'])
            ->assertOk()
            ->assertJsonPath('session.status', 'pending')
            ->assertJsonPath('session.scheduled_at', null);
        $this->assertDatabaseHas('pack_session_cancellations', [
            'pack_session_id' => $early->id, 'is_late' => false, 'consumes_session' => false,
        ]);

        $late = $pack->sessions()->where('sequence', 2)->firstOrFail();
        $late->update(['scheduled_at' => now()->addHour()]);
        $this->mock(StripeMarketplaceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferPackSession')->once()->andReturn((object) ['id' => 'tr_late_cancel']);
        });

        $this->postJson("/api/pack-sessions/{$late->id}/cancel", ['reason' => 'Empêchement tardif'])
            ->assertOk()
            ->assertJsonPath('session.status', 'paid')
            ->assertJsonPath('session.stripe_transfer_id', 'tr_late_cancel');
        $this->assertDatabaseHas('pack_session_cancellations', [
            'pack_session_id' => $late->id, 'is_late' => true, 'consumes_session' => true,
        ]);
    }

    public function test_client_no_show_can_only_be_declared_after_grace_period_and_is_paid_once(): void
    {
        [$client, $coach, $conversation] = $this->participants();
        $coach->update(['stripe_account_id' => 'acct_no_show', 'stripe_onboarding_completed' => true]);
        $pack = app(PackPaymentService::class)->completeOfferPayment(
            $this->offer($conversation, $client, $coach), 'pi_no_show', 13500, 'eur', 'ch_no_show'
        );
        $session = $pack->sessions()->first();
        $session->update(['scheduled_at' => now()->addMinutes(20)]);
        Sanctum::actingAs($coach);
        $this->postJson("/api/pack-sessions/{$session->id}/no-show")->assertStatus(409);

        $session->update(['scheduled_at' => now()->subHour()]);
        $this->mock(StripeMarketplaceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferPackSession')->once()->andReturn((object) ['id' => 'tr_no_show']);
        });
        $this->postJson("/api/pack-sessions/{$session->id}/no-show")
            ->assertOk()
            ->assertJsonPath('session.status', 'paid')
            ->assertJsonPath('session.stripe_transfer_id', 'tr_no_show');
        $this->postJson("/api/pack-sessions/{$session->id}/no-show")->assertStatus(409);

        $this->assertDatabaseHas('pack_session_cancellations', [
            'pack_session_id' => $session->id,
            'kind' => 'no_show',
            'consumes_session' => true,
        ]);
        $this->assertDatabaseCount('pack_payouts', 1);
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

<?php

namespace Tests\Feature;

use App\Models\Annonce;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ReservationStatusNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationPaymentRescheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_cannot_confirm_an_unpaid_reservation(): void
    {
        [$client, $coach, $annonce] = $this->marketplaceUsers();

        $reservation = $this->reservation($client, $coach, $annonce, [
            'is_paid' => false,
            'payment_status' => 'pending',
        ]);

        Sanctum::actingAs($coach);

        $this->putJson("/api/reservation/{$reservation->id}/valider")
            ->assertStatus(409)
            ->assertJsonPath('status', 409);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'attente',
            'is_paid' => false,
        ]);
    }

    public function test_client_can_reschedule_and_payment_is_preserved(): void
    {
        Notification::fake();
        Http::fake();

        [$client, $coach, $annonce] = $this->marketplaceUsers();

        $reservation = $this->reservation($client, $coach, $annonce, [
            'status' => 'confirme',
            'is_paid' => true,
            'payment_status' => 'paid',
            'payment_intent_id' => 'pi_test_123',
            'paid_at' => now(),
            'prestation_status' => 'paid',
        ]);

        $newDate = Carbon::now()->addDays(5)->format('Y-m-d');

        Sanctum::actingAs($client);

        $this->putJson("/api/reservation/{$reservation->id}/reschedule", [
            'reservation_date' => $newDate,
            'reservation_time' => '14:30:00',
            'note' => 'Contrainte professionnelle',
            'notify_coach' => true,
            'source' => 'gotfit-mobile',
        ])
            ->assertOk()
            ->assertJsonPath('reservation.status', 'attente')
            ->assertJsonPath('reservation.payment_status', 'paid')
            ->assertJsonPath('reservation.is_paid', true)
            ->assertJsonPath('coach_notified', true);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'reservation_date' => $newDate.' 00:00:00',
            'reservation_time' => '14:30:00',
            'status' => 'attente',
            'payment_status' => 'paid',
            'payment_intent_id' => 'pi_test_123',
        ]);

        $this->assertDatabaseHas('reservation_reschedule_histories', [
            'reservation_id' => $reservation->id,
            'changed_by' => $client->id,
            'new_reservation_date' => $newDate.' 00:00:00',
            'new_reservation_time' => '14:30:00',
            'source' => 'gotfit-mobile',
        ]);

        Notification::assertSentTo($coach, ReservationStatusNotification::class);
    }

    public function test_another_client_cannot_reschedule_the_reservation(): void
    {
        [$client, $coach, $annonce] = $this->marketplaceUsers();
        $otherClient = User::factory()->create();
        $otherClient->roles()->attach(Role::where('slug', 'client')->firstOrFail());
        $reservation = $this->reservation($client, $coach, $annonce);

        Sanctum::actingAs($otherClient);

        $this->putJson("/api/reservation/{$reservation->id}/reschedule", [
            'reservation_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
            'reservation_time' => '09:00:00',
        ])->assertForbidden();

        $this->assertDatabaseCount('reservation_reschedule_histories', 0);
    }

    private function marketplaceUsers(): array
    {
        $clientRole = Role::create([
            'name' => 'Client',
            'slug' => 'client',
            'is_active' => true,
        ]);
        $coachRole = Role::create([
            'name' => 'Intervenant',
            'slug' => 'intervenant',
            'is_active' => true,
        ]);

        $client = User::factory()->create();
        $client->roles()->attach($clientRole);
        $coach = User::factory()->create();
        $coach->roles()->attach($coachRole);

        $annonce = Annonce::create([
            'titre' => 'Coaching premium',
            'contenu' => 'Séance personnalisée',
            'status' => 'valide',
            'user_id' => $coach->id,
            'price' => 50,
            'duration' => 60,
        ]);

        return [$client, $coach, $annonce];
    }

    private function reservation(
        User $client,
        User $coach,
        Annonce $annonce,
        array $overrides = []
    ): Reservation {
        return Reservation::create(array_merge([
            'annonce_id' => $annonce->id,
            'client_id' => $client->id,
            'intervenant_id' => $coach->id,
            'reservation_date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '10:30:00',
            'guests' => 1,
            'price' => 50,
            'service_fee_rate' => 5,
            'service_fee_amount' => 2.5,
            'commission_rate' => 12,
            'commission_amount' => 6,
            'intervenant_amount' => 44,
            'total_client_amount' => 52.5,
            'currency' => 'eur',
            'status' => 'attente',
            'is_paid' => false,
            'payment_status' => 'pending',
            'prestation_status' => 'pending_payment',
            'payout_status' => 'pending',
        ], $overrides));
    }
}

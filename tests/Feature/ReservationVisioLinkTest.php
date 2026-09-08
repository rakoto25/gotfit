<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use App\Services\ReservationVisioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationVisioLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_reservation_link_is_created_by_its_coach_and_shared_only_with_the_client(): void
    {
        config([
            'services.visio.frontend_url' => 'https://gotfit.tech',
            'services.visio.server_url' => 'wss://gotfit-test.livekit.cloud',
            'services.visio.api_key' => 'test-key',
            'services.visio.api_secret' => 'test-secret',
        ]);

        $coach = $this->userWithRole('intervenant');
        $otherCoach = $this->userWithRole('intervenant');
        $client = $this->userWithRole('client');
        $reservation = Reservation::create([
            'client_id' => $client->id,
            'intervenant_id' => $coach->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '10:00:00',
            'price' => 50,
            'total_client_amount' => 52.5,
            'currency' => 'EUR',
            'status' => 'confirme',
            'is_paid' => true,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        $session = app(ReservationVisioService::class)->syncPaidReservation($reservation);

        Sanctum::actingAs($client);
        $this->postJson("/api/visio/sessions/{$session->id}/join")->assertStatus(423);

        Sanctum::actingAs($otherCoach);
        $this->postJson("/api/reservation/{$reservation->id}/visio-link")->assertForbidden();

        Sanctum::actingAs($coach);
        $link = $this->postJson("/api/reservation/{$reservation->id}/visio-link")
            ->assertOk()
            ->assertJsonPath('session.max_attendees', 2)
            ->json('join_url');
        $this->assertStringStartsWith("https://gotfit.tech/visio/{$session->id}?invite=", $link);

        Sanctum::actingAs($client);
        $this->postJson("/api/visio/sessions/{$session->id}/join")
            ->assertOk()
            ->assertJsonPath('room_name', $session->room_name);
    }

    public function test_unpaid_reservation_cannot_get_a_link(): void
    {
        $coach = $this->userWithRole('intervenant');
        $client = $this->userWithRole('client');
        $reservation = Reservation::create([
            'client_id' => $client->id,
            'intervenant_id' => $coach->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '10:00:00',
            'price' => 50,
            'status' => 'attente',
            'is_paid' => false,
            'payment_status' => 'pending',
        ]);

        Sanctum::actingAs($coach);
        $this->postJson("/api/reservation/{$reservation->id}/visio-link")->assertStatus(402);
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'is_active' => true]
        );
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}

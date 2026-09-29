<?php

namespace Tests\Feature;

use App\Models\Annonce;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnonceCapacityTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $user->roles()->attach(Role::firstOrCreate(
            ['slug' => $role],
            ['name' => ucfirst($role), 'is_active' => true]
        ));

        return $user;
    }

    public function test_coach_defines_the_maximum_number_of_clients_on_an_announcement(): void
    {
        $coach = $this->member('intervenant');
        Sanctum::actingAs($coach);

        $this->postJson('/api/annonces', [
            'titre' => 'Coaching collectif en visio',
            'contenu' => 'Une séance collective adaptée au niveau des participants.',
            'category' => 'Fitness',
            'price' => 35,
            'duration' => 60,
            'max_participants' => 3,
            'available_days' => ['monday'],
            'available_hours' => ['09:00-12:00'],
        ])->assertOk()->assertJsonPath('annonce.max_participants', 3);

        $this->assertDatabaseHas('annonces', [
            'user_id' => $coach->id,
            'max_participants' => 3,
        ]);
    }

    public function test_client_cannot_choose_guests_and_coach_capacity_is_enforced(): void
    {
        Notification::fake();
        $coach = $this->member('intervenant');
        $date = now()->addWeek()->startOfWeek()->toDateString();
        $annonce = Annonce::create([
            'user_id' => $coach->id,
            'titre' => 'Petit groupe en visio',
            'contenu' => 'Séance limitée à deux coachés.',
            'status' => 'valide',
            'announcement_type' => 'coach_service',
            'price' => 40,
            'duration' => 60,
            'max_participants' => 2,
            'available_days' => ['monday'],
            'available_hours' => ['09:00-12:00'],
        ]);

        foreach ([$this->member('client'), $this->member('client')] as $client) {
            Sanctum::actingAs($client);
            $this->putJson("/api/annonces/{$annonce->id}/reserve", [
                'reservation_date' => $date,
                'reservation_time' => '09:00',
                'guests' => 20,
            ])->assertOk()->assertJsonPath('reservation.guests', 1);
        }

        Sanctum::actingAs($this->member('client'));
        $this->putJson("/api/annonces/{$annonce->id}/reserve", [
            'reservation_date' => $date,
            'reservation_time' => '09:00',
        ])->assertConflict();

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_rescheduling_cannot_exceed_the_announcement_capacity(): void
    {
        Notification::fake();
        $coach = $this->member('intervenant');
        $firstClient = $this->member('client');
        $secondClient = $this->member('client');
        $date = now()->addWeek()->startOfWeek()->toDateString();
        $annonce = Annonce::create([
            'user_id' => $coach->id,
            'titre' => 'Séance individuelle',
            'contenu' => 'Une seule place par créneau.',
            'status' => 'valide',
            'announcement_type' => 'coach_service',
            'price' => 40,
            'duration' => 60,
            'max_participants' => 1,
            'available_days' => ['monday'],
            'available_hours' => ['09:00-12:00'],
        ]);

        Reservation::create([
            'annonce_id' => $annonce->id,
            'client_id' => $firstClient->id,
            'intervenant_id' => $coach->id,
            'reservation_date' => $date,
            'reservation_time' => '09:00:00',
            'guests' => 1,
            'status' => 'attente',
            'payment_status' => 'pending',
        ]);
        $reservation = Reservation::create([
            'annonce_id' => $annonce->id,
            'client_id' => $secondClient->id,
            'intervenant_id' => $coach->id,
            'reservation_date' => $date,
            'reservation_time' => '10:00:00',
            'guests' => 1,
            'status' => 'attente',
            'payment_status' => 'pending',
        ]);

        Sanctum::actingAs($secondClient);
        $this->putJson("/api/reservation/{$reservation->id}/reschedule", [
            'reservation_date' => $date,
            'reservation_time' => '09:00',
        ])->assertConflict();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'reservation_time' => '10:00:00',
        ]);
    }
}

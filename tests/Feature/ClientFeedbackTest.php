<?php

namespace Tests\Feature;

use App\Models\Annonce;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CoachApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $role], ['name' => ucfirst($role), 'is_active' => true]));

        return $user;
    }

    private function service(User $coach): Annonce
    {
        return Annonce::create([
            'user_id' => $coach->id, 'titre' => 'Coaching en ligne', 'contenu' => 'Séance personnalisée',
            'status' => 'valide', 'announcement_type' => 'coach_service', 'price' => '45.50', 'duration' => 60,
            'available_days' => [strtolower(now()->addDay()->englishDayOfWeek)], 'available_hours' => ['09:00-12:00'],
        ]);
    }

    public function test_reservation_only_accepts_published_days_and_full_session_within_hours(): void
    {
        Notification::fake();
        $coach = $this->member('intervenant');
        $annonce = $this->service($coach);
        Sanctum::actingAs($this->member('client'));
        $url = "/api/annonces/{$annonce->id}/reserve";
        $date = now()->addDay()->toDateString();
        foreach (['08:45', '11:15', '12:00'] as $time) {
            $this->putJson($url, ['reservation_date' => $date, 'reservation_time' => $time])->assertUnprocessable();
        }
        $this->putJson($url, ['reservation_date' => now()->addDays(2)->toDateString(), 'reservation_time' => '10:00'])->assertUnprocessable();
        $this->putJson($url, ['reservation_date' => $date, 'reservation_time' => '11:00'])
            ->assertOk()->assertJsonPath('reservation.price', '45.50');
    }

    public function test_missing_availability_is_not_unrestricted_booking(): void
    {
        $annonce = $this->service($this->member('intervenant'));
        $annonce->update(['available_hours' => null]);
        Sanctum::actingAs($this->member('client'));
        $this->putJson("/api/annonces/{$annonce->id}/reserve", [
            'reservation_date' => now()->addDay()->toDateString(), 'reservation_time' => '10:00',
        ])->assertUnprocessable();
    }

    public function test_rescheduling_outside_coach_hours_preserves_original_reservation(): void
    {
        Notification::fake();
        $annonce = $this->service($this->member('intervenant'));
        Sanctum::actingAs($this->member('client'));
        $date = now()->addDay()->toDateString();
        $id = $this->putJson("/api/annonces/{$annonce->id}/reserve", [
            'reservation_date' => $date, 'reservation_time' => '09:00',
        ])->assertOk()->json('reservation.id');
        $this->putJson("/api/reservation/{$id}/reschedule", [
            'reservation_date' => $date, 'reservation_time' => '11:30',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('reservations', ['id' => $id, 'reservation_time' => '09:00:00']);
    }

    public function test_both_roles_can_list_and_edit_their_own_announcements_only(): void
    {
        foreach (['client', 'intervenant'] as $role) {
            $owner = $this->member($role);
            $annonce = $this->service($owner);
            $annonce->update(['announcement_type' => $role === 'client' ? 'client_request' : 'coach_service']);
            Sanctum::actingAs($owner);
            $this->getJson('/api/annonces/my')->assertOk()->assertJsonCount(1, 'annonces');
            $this->postJson("/api/annonces/{$annonce->id}", ['_method' => 'PUT', 'titre' => 'Nouveau titre', 'price' => '62.75'])
                ->assertOk()->assertJsonPath('annonce.price', '62.75')->assertJsonPath('annonce.status', 'en_attente');
            Sanctum::actingAs($this->member($role));
            $this->putJson("/api/annonces/{$annonce->id}", ['titre' => 'Interdit'])->assertForbidden();
            $this->getJson('/api/annonces/my')->assertJsonCount(0, 'annonces');
        }
    }

    public function test_coach_approval_sends_one_email_with_dashboard_link(): void
    {
        Notification::fake();
        $coach = $this->member('intervenant');
        $coach->update(['account_status' => 'pending']);
        Sanctum::actingAs($this->member('admin'));
        $this->putJson("/api/users/{$coach->id}/validate", ['status' => 'approved'])->assertOk();
        $this->putJson("/api/users/{$coach->id}/validate", ['status' => 'approved'])->assertOk();
        Notification::assertSentToTimes($coach, CoachApprovedNotification::class, 1);
        $mail = (new CoachApprovedNotification)->toMail($coach);
        $this->assertStringEndsWith('/intervenant/dashboard', $mail->actionUrl);
        $this->assertStringStartsWith('http', $mail->actionUrl);
    }

    public function test_profile_name_is_persisted_and_returned(): void
    {
        $user = $this->member('client');
        Sanctum::actingAs($user);
        $this->postJson('/api/profile/update', ['name' => 'Mon Pseudo Complet'])
            ->assertOk()->assertJsonPath('user.name', 'Mon Pseudo Complet');
        $this->getJson('/api/profile')->assertOk()->assertJsonPath('user.name', 'Mon Pseudo Complet');
    }
}

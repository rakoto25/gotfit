<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisioParticipantLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_visio_v1_accepts_only_two_clients_in_addition_to_the_coach(): void
    {
        $coach = $this->userWithRole('intervenant');
        $clients = collect(range(1, 3))->map(fn () => $this->userWithRole('client'));

        Sanctum::actingAs($coach);
        $creation = $this->postJson('/api/visio/sessions', [
            'title' => 'Petit groupe V1',
            'start_at' => now()->addDay()->toIso8601String(),
            'min_participants' => 1,
            'max_participants' => 2,
            'price' => 0,
        ]);

        $creation
            ->assertCreated()
            ->assertJsonPath('session.max_participants', 2)
            ->assertJsonPath('session.max_attendees', 3);

        $sessionId = $creation->json('session.id');

        Sanctum::actingAs($clients[0]);
        $this->postJson("/api/visio/sessions/{$sessionId}/reserve")->assertCreated();

        Sanctum::actingAs($clients[1]);
        $this->postJson("/api/visio/sessions/{$sessionId}/reserve")->assertCreated();

        Sanctum::actingAs($clients[2]);
        $this->postJson("/api/visio/sessions/{$sessionId}/reserve")
            ->assertUnprocessable();

        $this->assertDatabaseCount('visio_participants', 3);
    }

    public function test_coach_cannot_create_a_session_for_more_than_two_clients(): void
    {
        $coach = $this->userWithRole('intervenant');
        Sanctum::actingAs($coach);

        $this->postJson('/api/visio/sessions', [
            'title' => 'Trop grande session',
            'start_at' => now()->addDay()->toIso8601String(),
            'max_participants' => 3,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_participants');
    }

    public function test_paid_client_and_coach_can_access_a_future_session_immediately(): void
    {
        config([
            'services.visio.provider' => 'livekit',
            'services.visio.server_url' => 'wss://gotfit-test.livekit.cloud',
            'services.visio.api_key' => 'test-livekit-key',
            'services.visio.api_secret' => 'test-livekit-secret',
            'services.visio.token_ttl' => 3600,
        ]);

        $coach = $this->userWithRole('intervenant');
        $client = $this->userWithRole('client');

        Sanctum::actingAs($coach);
        $creation = $this->postJson('/api/visio/sessions', [
            'title' => 'Séance accessible immédiatement',
            'start_at' => now()->addDay()->toIso8601String(),
            'min_participants' => 1,
            'max_participants' => 1,
            'price' => 0,
        ])->assertCreated();

        $sessionId = $creation->json('session.id');

        Sanctum::actingAs($client);
        $this->postJson("/api/visio/sessions/{$sessionId}/reserve")
            ->assertCreated();

        // Même si l'heure planifiée est demain, le coach peut démarrer tout de suite.
        Sanctum::actingAs($coach);
        $this->postJson("/api/visio/sessions/{$sessionId}/start")
            ->assertOk()
            ->assertJsonPath('session.status', 'live');

        // Et le client payé/validé peut rejoindre immédiatement.
        Sanctum::actingAs($client);
        $this->postJson("/api/visio/sessions/{$sessionId}/join")
            ->assertOk()
            ->assertJsonPath('provider', 'livekit')
            ->assertJsonPath('server_url', 'wss://gotfit-test.livekit.cloud');
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

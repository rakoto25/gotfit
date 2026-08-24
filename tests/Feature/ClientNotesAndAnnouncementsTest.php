<?php

namespace Tests\Feature;

use App\Models\Annonce;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientNotesAndAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_assign_a_note_to_a_coach(): void
    {
        $client = $this->userWithRole('client');
        $coach = $this->userWithRole('intervenant');

        Sanctum::actingAs($client);

        $this->postJson("/api/clients/{$client->id}/notes", [
            'intervenant_id' => $coach->id,
            'visibility' => 'shared',
            'title' => 'Objectifs de la semaine',
            'content' => 'Reprendre progressivement les séances de mobilité.',
        ])
            ->assertCreated()
            ->assertJsonPath('note.intervenant_id', $coach->id)
            ->assertJsonPath('note.intervenant.id', $coach->id);

        $this->assertDatabaseHas('client_notes', [
            'client_id' => $client->id,
            'intervenant_id' => $coach->id,
            'author_id' => $client->id,
        ]);
    }

    public function test_note_cannot_be_assigned_to_a_non_coach(): void
    {
        $client = $this->userWithRole('client');
        $otherClient = $this->userWithRole('client');

        Sanctum::actingAs($client);

        $this->postJson("/api/clients/{$client->id}/notes", [
            'intervenant_id' => $otherClient->id,
            'content' => 'Note invalide.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('intervenant_id');
    }

    public function test_client_can_publish_a_coach_request(): void
    {
        $client = $this->userWithRole('client');
        Sanctum::actingAs($client);

        $annonceId = $this->postJson('/api/annonces', [
            'titre' => 'Je cherche un coach de remise en forme',
            'contenu' => 'Je souhaite reprendre le sport avec un accompagnement progressif.',
            'category' => 'Remise en forme',
            'price' => 40,
            'duration' => 60,
            'is_online' => true,
        ])
            ->assertOk()
            ->assertJsonPath('annonce.announcement_type', 'client_request')
            ->assertJsonPath('annonce.user_id', $client->id)
            ->json('annonce.id');

        $this->assertDatabaseHas('annonces', [
            'id' => $annonceId,
            'user_id' => $client->id,
            'announcement_type' => 'client_request',
            'status' => 'en_attente',
        ]);
    }

    public function test_client_request_cannot_be_reserved_as_a_paid_service(): void
    {
        $client = $this->userWithRole('client');
        $otherClient = $this->userWithRole('client');
        $annonce = Annonce::create([
            'titre' => 'Recherche coach running',
            'contenu' => 'Préparation pour une première course de dix kilomètres.',
            'user_id' => $client->id,
            'status' => 'valide',
            'announcement_type' => 'client_request',
        ]);

        Sanctum::actingAs($otherClient);

        $this->putJson("/api/annonces/{$annonce->id}/reserve", [
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '10:00',
            'guests' => 1,
        ])->assertUnprocessable();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'description' => null, 'is_active' => true]
        );
        $user->roles()->attach($role);

        return $user;
    }
}

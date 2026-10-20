<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachFavoriteFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_list_add_and_remove_coach_favorites(): void
    {
        $client = $this->userWithRole('client');
        $coach = $this->userWithRole('intervenant');

        Sanctum::actingAs($client);

        $this->getJson('/api/coach-favorites')
            ->assertOk()
            ->assertJsonCount(0, 'favorites');

        $favoriteId = $this->postJson("/api/coach-favorites/{$coach->id}")
            ->assertOk()
            ->assertJsonPath('favorite.user_id', $client->id)
            ->assertJsonPath('favorite.coach_id', $coach->id)
            ->assertJsonPath('favorite.coach.id', $coach->id)
            ->json('favorite.id');

        $this->postJson("/api/coach-favorites/{$coach->id}")
            ->assertOk()
            ->assertJsonPath('favorite.id', $favoriteId);

        $this->assertDatabaseCount('coach_favorites', 1);

        $this->getJson('/api/coach-favorites')
            ->assertOk()
            ->assertJsonCount(1, 'favorites')
            ->assertJsonPath('favorites.0.coach.id', $coach->id)
            ->assertJsonMissingPath('favorites.0.coach.email');

        $this->deleteJson("/api/coach-favorites/{$coach->id}")
            ->assertOk();

        $this->assertDatabaseMissing('coach_favorites', [
            'user_id' => $client->id,
            'coach_id' => $coach->id,
        ]);
    }

    public function test_only_clients_can_manage_coach_favorites(): void
    {
        $coach = $this->userWithRole('intervenant');
        Sanctum::actingAs($coach);

        $this->getJson('/api/coach-favorites')->assertForbidden();
        $this->postJson("/api/coach-favorites/{$coach->id}")->assertForbidden();
    }

    public function test_client_cannot_favorite_a_non_coach(): void
    {
        $client = $this->userWithRole('client');
        $otherClient = $this->userWithRole('client');
        Sanctum::actingAs($client);

        $this->postJson("/api/coach-favorites/{$otherClient->id}")
            ->assertNotFound();
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

<?php

namespace Tests\Feature;

use App\Models\Annonce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_add_and_remove_an_annonce_from_favorites(): void
    {
        $client = User::factory()->create();
        $coach = User::factory()->create();
        $annonce = Annonce::create([
            'titre' => 'Coaching personnalisé',
            'contenu' => 'Séance de remise en forme',
            'user_id' => $coach->id,
            'status' => 'valide',
        ]);

        Sanctum::actingAs($client);

        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(0, 'favorites');

        $favoriteId = $this->postJson("/api/favorites/{$annonce->id}")
            ->assertOk()
            ->assertJsonPath('favorite.user_id', $client->id)
            ->assertJsonPath('favorite.annonce_id', $annonce->id)
            ->json('favorite.id');

        $this->postJson("/api/favorites/{$annonce->id}")
            ->assertOk()
            ->assertJsonPath('favorite.id', $favoriteId);

        $this->assertDatabaseCount('favorites', 1);

        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'favorites')
            ->assertJsonPath('favorites.0.id', $favoriteId)
            ->assertJsonPath('favorites.0.annonce.id', $annonce->id);

        $this->deleteJson("/api/favorites/annonce/{$annonce->id}")
            ->assertOk()
            ->assertJsonPath('status', 200);

        $this->assertDatabaseMissing('favorites', ['id' => $favoriteId]);
    }

    public function test_user_cannot_delete_another_users_favorite(): void
    {
        $firstClient = User::factory()->create();
        $secondClient = User::factory()->create();
        $coach = User::factory()->create();
        $annonce = Annonce::create([
            'titre' => 'Programme mobilité',
            'contenu' => 'Accompagnement personnalisé',
            'user_id' => $coach->id,
            'status' => 'valide',
        ]);

        Sanctum::actingAs($firstClient);

        $favoriteId = $this->postJson("/api/favorites/{$annonce->id}")
            ->assertOk()
            ->json('favorite.id');

        Sanctum::actingAs($secondClient);

        $this->deleteJson("/api/favorites/{$favoriteId}")
            ->assertNotFound();

        $this->assertDatabaseHas('favorites', [
            'id' => $favoriteId,
            'user_id' => $firstClient->id,
            'annonce_id' => $annonce->id,
        ]);
    }
}

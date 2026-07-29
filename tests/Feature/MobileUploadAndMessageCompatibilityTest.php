<?php

namespace Tests\Feature;

use App\Models\Conversations;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileUploadAndMessageCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_can_update_only_its_profile_photo_with_multipart_form_data(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/profile/update', [
            'photo' => UploadedFile::fake()->image('profil.jpg', 600, 600),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 200)
            ->assertJsonPath('user.id', $user->id);

        $storedPhoto = $user->fresh()->photo;

        $this->assertNotNull($storedPhoto);
        Storage::disk('public')->assertExists($storedPhoto);
    }

    public function test_mobile_can_update_a_message_through_the_post_compatibility_route(): void
    {
        $client = User::factory()->create();
        $coach = User::factory()->create();

        $conversation = Conversations::create([
            'client_id' => $client->id,
            'intervenant_id' => $coach->id,
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $client->id,
            'receiver_id' => $coach->id,
            'message' => 'Texte initial',
            'type' => 'text',
        ]);

        Sanctum::actingAs($client);

        $this->postJson("/api/message/{$message->id}/update", [
            'message' => 'Texte corrigé',
        ])
            ->assertOk()
            ->assertJsonPath('message.message', 'Texte corrigé')
            ->assertJsonPath('message.is_edited', true);
    }
}

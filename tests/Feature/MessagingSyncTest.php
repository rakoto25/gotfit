<?php

namespace Tests\Feature;

use App\Models\Conversations;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_edit_and_delete_a_message_for_everyone(): void
    {
        [$client, $coach, $conversation] = $this->conversation();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $client->id,
            'receiver_id' => $coach->id,
            'message' => 'Message initial',
            'type' => 'text',
        ]);

        Sanctum::actingAs($client);

        $this->patchJson("/api/message/{$message->id}", [
            'message' => 'Message corrigé',
        ])
            ->assertOk()
            ->assertJsonPath('message.message', 'Message corrigé')
            ->assertJsonPath('message.is_edited', true);

        $this->deleteJson("/api/message/{$message->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('message.is_deleted', true);

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'message' => '',
            'type' => 'deleted',
        ]);
    }

    public function test_another_participant_cannot_edit_or_delete_the_message(): void
    {
        [$client, $coach, $conversation] = $this->conversation();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $client->id,
            'receiver_id' => $coach->id,
            'message' => 'Message privé',
            'type' => 'text',
        ]);

        Sanctum::actingAs($coach);

        $this->patchJson("/api/message/{$message->id}", [
            'message' => 'Modification interdite',
        ])->assertForbidden();

        $this->deleteJson("/api/message/{$message->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'message' => 'Message privé',
            'deleted_at' => null,
        ]);
    }

    public function test_mobile_can_register_and_remove_its_expo_push_token(): void
    {
        $user = User::factory()->create();
        $token = 'ExponentPushToken[gotfit-test-device]';

        Sanctum::actingAs($user);

        $this->postJson('/api/push-tokens', [
            'expo_push_token' => $token,
            'platform' => 'android',
            'device_name' => 'Pixel test',
        ])
            ->assertOk()
            ->assertJsonPath('push_token.token', $token);

        $this->assertDatabaseHas('push_tokens', [
            'user_id' => $user->id,
            'token' => $token,
            'platform' => 'android',
        ]);

        $this->deleteJson('/api/push-tokens', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('push_tokens', ['token' => $token]);
    }

    private function conversation(): array
    {
        $client = User::factory()->create();
        $coach = User::factory()->create();

        $conversation = Conversations::create([
            'client_id' => $client->id,
            'intervenant_id' => $coach->id,
        ]);

        return [$client, $coach, $conversation];
    }
}

<?php

namespace Tests\Feature;

use App\Models\CoachForumPost;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachForumTest extends TestCase
{
    use RefreshDatabase;

    public function test_forum_is_reserved_for_coaches(): void
    {
        $client = $this->userWithRole('client');

        Sanctum::actingAs($client);

        $this->getJson('/api/coach/forum')->assertForbidden();
        $this->postJson('/api/coach/forum', [
            'content' => 'Ce message ne doit pas être créé.',
        ])->assertForbidden();

        $this->assertDatabaseCount('coach_forum_posts', 0);
    }

    public function test_coach_can_publish_and_read_forum_messages(): void
    {
        $coach = $this->userWithRole('intervenant');

        Sanctum::actingAs($coach);

        $this->postJson('/api/coach/forum', [
            'content' => '  Bonjour à toute la communauté des coachs.  ',
        ])
            ->assertCreated()
            ->assertJsonPath('post.user_id', $coach->id)
            ->assertJsonPath('post.content', 'Bonjour à toute la communauté des coachs.');

        $this->getJson('/api/coach/forum')
            ->assertOk()
            ->assertJsonPath('posts.0.author.id', $coach->id)
            ->assertJsonPath('posts.0.content', 'Bonjour à toute la communauté des coachs.');
    }

    public function test_coach_can_only_delete_their_own_message(): void
    {
        $coach = $this->userWithRole('intervenant');
        $otherCoach = $this->userWithRole('intervenant');
        $post = CoachForumPost::create([
            'user_id' => $coach->id,
            'content' => 'Mon message.',
        ]);

        Sanctum::actingAs($otherCoach);
        $this->deleteJson("/api/coach/forum/{$post->id}")->assertForbidden();

        Sanctum::actingAs($coach);
        $this->deleteJson("/api/coach/forum/{$post->id}")->assertOk();

        $this->assertSoftDeleted('coach_forum_posts', ['id' => $post->id]);
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

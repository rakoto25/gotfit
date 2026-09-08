<?php

namespace Tests\Feature;

use App\Models\ForumChannel;
use App\Models\ForumDiscussion;
use App\Models\ForumNotification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForumArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_coaches_and_admins_can_access_the_forum(): void
    {
        $pendingCoach = $this->userWithRole('intervenant', 'pending');
        Sanctum::actingAs($pendingCoach);
        $this->getJson('/api/forum/channels')->assertForbidden();

        $client = $this->userWithRole('client');
        Sanctum::actingAs($client);
        $this->getJson('/api/forum/channels')->assertForbidden();

        $coach = $this->userWithRole('intervenant');
        Sanctum::actingAs($coach);
        $this->getJson('/api/forum/channels')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'annonces-gotfit', 'is_official' => true]);

        $official = ForumChannel::where('slug', 'annonces-gotfit')->firstOrFail();
        $this->postJson('/api/forum/discussions', [
            'channel_id' => $official->id,
            'title' => 'Interdit',
            'body' => 'Un coach ne publie pas dans le canal officiel.',
        ])->assertForbidden();

        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin);
        $this->postJson('/api/forum/discussions', [
            'channel_id' => $official->id,
            'title' => 'Maintenance planifiée',
            'body' => 'Annonce officielle GotFit.',
        ])->assertCreated();
    }

    public function test_discussions_nested_replies_reactions_search_mentions_and_notifications_work(): void
    {
        $coach = $this->userWithRole('intervenant');
        $mentionedCoach = $this->userWithRole('intervenant');
        $general = ForumChannel::where('slug', 'general')->firstOrFail();

        Sanctum::actingAs($coach);
        $created = $this->postJson('/api/forum/discussions', [
            'channel_id' => $general->id,
            'title' => 'Mobilité des épaules',
            'body' => 'Quelles progressions utilisez-vous ?',
            'mention_ids' => [$mentionedCoach->id],
        ])->assertCreated();
        $discussionId = $created->json('discussion.id');

        $this->getJson('/api/forum/discussions?q=épaules')
            ->assertOk()
            ->assertJsonPath('discussions.data.0.id', $discussionId);

        $firstReply = $this->postJson("/api/forum/discussions/{$discussionId}/comments", [
            'body' => 'Je commence par une évaluation active.',
        ])->assertCreated();
        $parentId = $firstReply->json('comment.id');

        Sanctum::actingAs($mentionedCoach);
        $this->postJson("/api/forum/discussions/{$discussionId}/comments", [
            'body' => 'Merci, je complète avec un travail contrôlé.',
            'parent_id' => $parentId,
            'mention_ids' => [$coach->id],
        ])->assertCreated();

        $this->postJson("/api/forum/discussions/{$discussionId}/reactions", ['type' => 'helpful'])
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('discussion.viewer_reaction', 'helpful');

        $this->getJson("/api/forum/discussions/{$discussionId}")
            ->assertOk()
            ->assertJsonPath('comments.0.id', $parentId)
            ->assertJsonPath('comments.0.children.0.parent_id', $parentId);

        $this->getJson('/api/forum/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->putJson('/api/forum/notifications/read-all')->assertOk();
        $this->assertSame(0, ForumNotification::where('user_id', $mentionedCoach->id)->whereNull('read_at')->count());
    }

    public function test_reporting_pinning_locking_and_moderation_rights_are_enforced(): void
    {
        $coach = $this->userWithRole('intervenant');
        $admin = $this->userWithRole('admin');
        $discussion = ForumDiscussion::create([
            'channel_id' => ForumChannel::where('slug', 'general')->value('id'),
            'author_id' => $coach->id,
            'title' => 'Discussion à modérer',
            'body' => 'Contenu test.',
            'last_activity_at' => now(),
        ]);

        Sanctum::actingAs($coach);
        $this->postJson("/api/forum/discussions/{$discussion->id}/report", [
            'reason' => 'inappropriate',
            'details' => 'À vérifier.',
        ])->assertCreated();

        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/forum/discussions/{$discussion->id}/moderate", [
            'is_pinned' => true,
            'is_locked' => true,
        ])->assertOk();
        $reportId = $this->getJson('/api/admin/forum/reports?status=pending')
            ->assertOk()
            ->json('reports.data.0.id');
        $this->putJson("/api/admin/forum/reports/{$reportId}", [
            'status' => 'resolved',
            'moderator_note' => 'Traité.',
        ])->assertOk();

        Sanctum::actingAs($coach);
        $this->postJson("/api/forum/discussions/{$discussion->id}/comments", [
            'body' => 'Impossible après verrouillage.',
        ])->assertStatus(423);

        $this->assertDatabaseHas('forum_discussions', [
            'id' => $discussion->id,
            'is_pinned' => true,
            'is_locked' => true,
        ]);
        $this->assertDatabaseCount('forum_reports', 1);
        $this->assertDatabaseCount('forum_comments', 0);
    }

    public function test_discussion_and_comment_crud_respect_ownership(): void
    {
        $author = $this->userWithRole('intervenant');
        $otherCoach = $this->userWithRole('intervenant');
        $general = ForumChannel::where('slug', 'general')->firstOrFail();

        Sanctum::actingAs($author);
        $discussionId = $this->postJson('/api/forum/discussions', [
            'channel_id' => $general->id,
            'title' => 'Titre initial',
            'body' => 'Contenu initial.',
        ])->assertCreated()->json('discussion.id');
        $commentId = $this->postJson("/api/forum/discussions/{$discussionId}/comments", [
            'body' => 'Réponse initiale.',
        ])->assertCreated()->json('comment.id');

        Sanctum::actingAs($otherCoach);
        $this->putJson("/api/forum/discussions/{$discussionId}", ['title' => 'Intrusion'])->assertForbidden();
        $this->deleteJson("/api/forum/comments/{$commentId}")->assertForbidden();

        Sanctum::actingAs($author);
        $this->putJson("/api/forum/discussions/{$discussionId}", ['title' => 'Titre corrigé'])
            ->assertOk()
            ->assertJsonPath('discussion.title', 'Titre corrigé');
        $this->putJson("/api/forum/comments/{$commentId}", ['body' => 'Réponse corrigée.'])
            ->assertOk()
            ->assertJsonPath('comment.body', 'Réponse corrigée.');
        $this->deleteJson("/api/forum/comments/{$commentId}")->assertOk();
        $this->deleteJson("/api/forum/discussions/{$discussionId}")->assertOk();

        $this->assertSoftDeleted('forum_comments', ['id' => $commentId]);
        $this->assertSoftDeleted('forum_discussions', ['id' => $discussionId]);
    }

    public function test_admin_channel_names_generate_unique_slugs(): void
    {
        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/forum/channels', ['name' => 'Général'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    private function userWithRole(string $slug, string $status = 'approved'): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'is_active' => true]
        );
        $user = User::factory()->create(['account_status' => $status]);
        $user->roles()->attach($role);

        return $user;
    }
}

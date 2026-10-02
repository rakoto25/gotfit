<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCoachBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_and_send_one_message_to_eleven_approved_coaches(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'is_active' => true]);
        $coachRole = Role::create(['name' => 'Intervenant', 'slug' => 'intervenant', 'is_active' => true]);
        $admin = User::factory()->create(['account_status' => 'approved']);
        $admin->roles()->attach($adminRole);

        User::factory()->count(11)->create(['account_status' => 'approved'])
            ->each(fn (User $coach) => $coach->roles()->attach($coachRole));
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/messages/broadcast-coaches', [
            'subject' => 'Information GotFit',
            'message' => 'Message destiné aux coachs.',
            'only_approved' => true,
            'dry_run' => true,
        ])
            ->assertOk()
            ->assertJsonPath('recipients_count', 11)
            ->assertJsonPath('dry_run', true);
        $this->assertDatabaseCount('messages', 0);

        $this->postJson('/api/admin/messages/broadcast-coaches', [
            'subject' => 'Information GotFit',
            'message' => 'Message destiné aux coachs.',
            'only_approved' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('recipients_count', 11)
            ->assertJsonPath('sent_count', 11)
            ->assertJsonPath('failed_count', 0);

        $this->assertDatabaseCount('messages', 11);
        $this->assertDatabaseCount('conversations', 11);
        $this->assertDatabaseHas('messages', [
            'sender_id' => $admin->id,
            'is_admin_broadcast' => true,
            'broadcast_target_role' => 'intervenant',
        ]);
    }
}

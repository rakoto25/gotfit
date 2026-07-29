<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_upload_a_diploma_with_verification_metadata(): void
    {
        Storage::fake('public');
        $coach = $this->userWithRole('intervenant');
        Sanctum::actingAs($coach);

        $response = $this->post('/api/coach/credentials', [
            'name' => 'BPJEPS Activités de la forme',
            'document_type' => 'diploma',
            'document_number' => 'BP-2026-001',
            'issuing_organization' => 'DRAJES',
            'issued_at' => now()->subYear()->format('Y-m-d'),
            'file' => UploadedFile::fake()->create('diplome.pdf', 300, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('document.document_type', 'diploma')
            ->assertJsonPath('document.status', 'en_attente')
            ->assertJsonPath('document.issuing_organization', 'DRAJES');

        $path = $response->json('document.file_path');
        Storage::disk('public')->assertExists($path);

        $this->assertDatabaseHas('documents', [
            'user_id' => $coach->id,
            'document_type' => 'diploma',
            'document_number' => 'BP-2026-001',
        ]);
    }

    public function test_client_cannot_use_the_coach_credentials_endpoint(): void
    {
        $client = $this->userWithRole('client');
        Sanctum::actingAs($client);

        $this->getJson('/api/coach/credentials')->assertForbidden();
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

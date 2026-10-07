<?php

namespace Tests\Feature;

use App\Models\LegalDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegalDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_legal_documents_are_public(): void
    {
        $this->getJson('/api/legal-documents/client-achat')
            ->assertOk()
            ->assertJsonPath('document.slug', 'client-achat')
            ->assertJsonPath('document.audience', 'client')
            ->assertJsonPath('document.is_published', true);

        $this->getJson('/api/legal-documents/intervenants')
            ->assertOk()
            ->assertJsonPath('document.audience', 'intervenant');
    }

    public function test_unpublished_legal_document_is_not_public(): void
    {
        LegalDocument::where('slug', 'client-achat')->update(['is_published' => false]);

        $this->getJson('/api/legal-documents/client-achat')->assertNotFound();
    }

    public function test_admin_can_update_and_publish_legal_document(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'is_active' => true]);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        Sanctum::actingAs($admin);

        $document = LegalDocument::where('slug', 'client-achat')->firstOrFail();

        $this->putJson("/api/admin/legal-documents/{$document->id}", [
            'title' => 'CGV clients GotFit',
            'content' => str_repeat('Contenu juridique mis à jour. ', 8),
            'version' => '1.1',
            'effective_at' => '2026-10-07T00:00:00Z',
            'is_published' => true,
            'audience' => 'client',
        ])
            ->assertOk()
            ->assertJsonPath('document.version', '1.1')
            ->assertJsonPath('document.title', 'CGV clients GotFit');
    }
}

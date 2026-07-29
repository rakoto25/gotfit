<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FitnessAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_a_configurable_form_and_client_can_submit_it(): void
    {
        $admin = $this->userWithRole('admin');
        $client = $this->userWithRole('client');

        Sanctum::actingAs($admin);
        $formResponse = $this->postJson('/api/admin/fitness-assessment/forms', [
            'slug' => 'bilan-initial',
            'title' => 'Bilan de forme initial',
            'version' => 1,
            'is_active' => true,
            'questions' => [
                [
                    'key' => 'objectif',
                    'label' => 'Quel est votre objectif principal ?',
                    'type' => 'textarea',
                    'required' => true,
                ],
                [
                    'key' => 'douleur',
                    'label' => 'Avez-vous une douleur actuelle ?',
                    'type' => 'boolean',
                    'required' => false,
                ],
            ],
        ]);

        $formResponse->assertCreated();
        $formId = $formResponse->json('form.id');

        Sanctum::actingAs($client);
        $this->getJson('/api/fitness-assessment/form')
            ->assertOk()
            ->assertJsonPath('form.id', $formId);

        $this->putJson('/api/fitness-assessment', [
            'form_id' => $formId,
            'answers' => ['douleur' => false],
            'status' => 'submitted',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answers.objectif');

        $this->putJson('/api/fitness-assessment', [
            'form_id' => $formId,
            'answers' => [
                'objectif' => 'Retrouver une bonne condition physique',
                'douleur' => false,
            ],
            'status' => 'submitted',
        ])
            ->assertOk()
            ->assertJsonPath('assessment.status', 'submitted')
            ->assertJsonPath('assessment.answers.objectif', 'Retrouver une bonne condition physique');

        $this->assertDatabaseHas('fitness_assessments', [
            'client_id' => $client->id,
            'form_id' => $formId,
            'status' => 'submitted',
        ]);
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

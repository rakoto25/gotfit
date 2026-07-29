<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'gotfit-web-client.apps.googleusercontent.com']);
    }

    public function test_google_login_creates_a_client_without_a_manual_form(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-123',
                'aud' => 'gotfit-web-client.apps.googleusercontent.com',
                'iss' => 'https://accounts.google.com',
                'email' => 'amina@example.com',
                'email_verified' => 'true',
                'name' => 'Amina Diallo',
                'picture' => 'https://images.example.com/amina.jpg',
                'exp' => now()->addHour()->timestamp,
            ]),
        ]);

        $response = $this->postJson('/api/auth/google', [
            'credential' => 'signed-google-credential',
            'role' => 'client',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('is_new_user', true)
            ->assertJsonPath('user.email', 'amina@example.com')
            ->assertJsonPath('user.roles.0.slug', 'client')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'amina@example.com',
            'google_id' => 'google-user-123',
            'account_status' => 'approved',
        ]);
    }

    public function test_existing_account_keeps_its_roles_during_google_login(): void
    {
        $clientRole = Role::create([
            'name' => 'Client',
            'slug' => 'client',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['email' => 'amina@example.com']);
        $user->roles()->attach($clientRole);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-123',
                'aud' => 'gotfit-web-client.apps.googleusercontent.com',
                'iss' => 'accounts.google.com',
                'email' => 'amina@example.com',
                'email_verified' => 'true',
                'name' => 'Amina Diallo',
                'exp' => now()->addHour()->timestamp,
            ]),
        ]);

        $response = $this->postJson('/api/auth/google', [
            'credential' => 'signed-google-credential',
            'role' => 'intervenant',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('is_new_user', false)
            ->assertJsonPath('user.roles.0.slug', 'client');

        $this->assertFalse($user->fresh()->hasRole('intervenant'));
    }

    public function test_google_coach_is_redirected_to_professional_profile_completion(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-coach-456',
                'aud' => 'gotfit-web-client.apps.googleusercontent.com',
                'iss' => 'https://accounts.google.com',
                'email' => 'coach-google@example.com',
                'email_verified' => 'true',
                'name' => 'Coach Google',
                'exp' => now()->addHour()->timestamp,
            ]),
        ]);

        $this->postJson('/api/auth/google', [
            'credential' => 'signed-google-coach-credential',
            'role' => 'intervenant',
        ])
            ->assertCreated()
            ->assertJsonPath('user.account_status', 'pending')
            ->assertJsonPath('professional_profile.requires_completion', true)
            ->assertJsonPath('professional_profile.missing_fields.0', 'siret')
            ->assertJsonPath('professional_profile.missing_fields.1', 'diploma_or_certification');
    }

    public function test_google_login_rejects_a_token_for_another_application(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-123',
                'aud' => 'another-app.apps.googleusercontent.com',
                'iss' => 'https://accounts.google.com',
                'email' => 'amina@example.com',
                'email_verified' => 'true',
                'exp' => now()->addHour()->timestamp,
            ]),
        ]);

        $this->postJson('/api/auth/google', [
            'credential' => 'signed-google-credential',
        ])->assertUnauthorized();
    }
}

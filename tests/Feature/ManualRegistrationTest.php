<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_register_with_the_form_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Amina Diallo',
            'email' => 'amina@example.com',
            'phone' => '+221 77 000 00 00',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
            'role' => 'client',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', 201)
            ->assertJsonPath('user.email', 'amina@example.com')
            ->assertJsonPath('user.account_status', 'approved')
            ->assertJsonPath('user.roles.0.slug', 'client')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'amina@example.com',
            'account_status' => 'approved',
        ]);
    }

    public function test_coach_registration_stays_pending_until_validation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Moussa Coach',
            'email' => 'coach@example.com',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
            'role' => 'intervenant',
            'siret' => '123 456 789 00012',
        ])
            ->assertCreated()
            ->assertJsonPath('user.account_status', 'pending')
            ->assertJsonPath('user.siret', '12345678900012')
            ->assertJsonPath('user.roles.0.slug', 'intervenant');
    }

    public function test_siret_is_required_for_coach_registration(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Coach sans SIRET',
            'email' => 'coach-sans-siret@example.com',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
            'role' => 'intervenant',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('siret');
    }

    public function test_password_confirmation_is_required(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Amina Diallo',
            'email' => 'amina@example.com',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-different',
            'role' => 'client',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', [
            'email' => 'amina@example.com',
        ]);
    }
}

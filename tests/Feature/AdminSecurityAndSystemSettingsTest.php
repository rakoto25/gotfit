<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSecurityAndSystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_challenge_exposes_only_the_public_turnstile_key(): void
    {
        Config::set('services.turnstile.enabled', true);
        Config::set('services.turnstile.site_key', 'public-site-key');
        Config::set('services.turnstile.secret_key', 'private-secret-key');

        $this->getJson('/api/admin/security/challenge')
            ->assertOk()
            ->assertJson([
                'provider' => 'turnstile',
                'enabled' => true,
                'configured' => true,
                'site_key' => 'public-site-key',
            ])
            ->assertJsonMissing(['secret_key' => 'private-secret-key']);
    }

    public function test_public_config_never_exposes_private_keys(): void
    {
        Config::set('services.stripe.key', 'pk_test_public');
        Config::set('services.stripe.secret', 'sk_test_private');
        Config::set('services.google.client_id', 'google-public-id');
        Config::set('services.google.client_secret', 'google-private-secret');

        $this->getJson('/api/public-config')
            ->assertOk()
            ->assertJson([
                'stripe_publishable_key' => 'pk_test_public',
                'google_client_id' => 'google-public-id',
            ])
            ->assertJsonMissing(['stripe_secret' => 'sk_test_private'])
            ->assertJsonMissing(['google_client_secret' => 'google-private-secret']);
    }

    public function test_turnstile_is_verified_before_an_admin_token_is_created(): void
    {
        Config::set('services.turnstile.enabled', true);
        Config::set('services.turnstile.site_key', 'public-site-key');
        Config::set('services.turnstile.secret_key', 'private-secret-key');
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $admin = $this->admin('admin@example.test', 'correct-password');

        $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
            'turnstile_token' => 'valid-browser-token',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonStructure(['token']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'private-secret-key'
            && $request['response'] === 'valid-browser-token'
        );

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_failed_turnstile_and_non_admin_accounts_never_receive_a_token(): void
    {
        Config::set('services.turnstile.enabled', true);
        Config::set('services.turnstile.site_key', 'public-site-key');
        Config::set('services.turnstile.secret_key', 'private-secret-key');
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $admin = $this->admin('blocked@example.test', 'correct-password');

        $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
            'turnstile_token' => 'invalid-token',
        ])->assertUnprocessable();

        Config::set('services.turnstile.enabled', false);
        $user = User::factory()->create([
            'email' => 'client@example.test',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_can_store_encrypted_settings_without_reading_secrets_back(): void
    {
        $admin = $this->admin('settings@example.test', 'correct-password');
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/system-settings', [
            'settings' => [
                'STRIPE_SECRET' => 'sk_test_never_return_this',
                'MAIL_HOST' => 'smtp.gmail.com',
                'MAIL_PORT' => '587',
                'MAIL_ENCRYPTION' => 'none',
            ],
        ])->assertOk();

        $stripe = collect($response->json('settings'))->firstWhere('key', 'STRIPE_SECRET');
        $this->assertTrue($stripe['configured']);
        $this->assertSame('', $stripe['value']);
        $this->assertSame('database', $stripe['source']);

        $rawValue = DB::table('system_settings')->where('key', 'STRIPE_SECRET')->value('value');
        $this->assertNotSame('sk_test_never_return_this', $rawValue);
        $this->assertSame('sk_test_never_return_this', SystemSetting::where('key', 'STRIPE_SECRET')->firstOrFail()->value);
        $this->assertSame('sk_test_never_return_this', config('services.stripe.secret'));

        $mailEncryption = collect($response->json('settings'))->firstWhere('key', 'MAIL_ENCRYPTION');
        $this->assertTrue($mailEncryption['configured']);
        $this->assertSame('none', $mailEncryption['value']);

        $this->getJson('/api/admin/system-settings')
            ->assertOk()
            ->assertJsonMissing(['value' => 'sk_test_never_return_this']);
    }

    private function admin(string $email, string $password): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'is_active' => true]
        );
        $admin = User::factory()->create([
            'email' => $email,
            'password' => $password,
        ]);
        $admin->roles()->attach($role);

        return $admin;
    }
}

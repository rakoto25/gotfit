<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettingRegistry
{
    /**
     * The database is deliberately excluded: changing it from a table stored
     * in that same database would make the application impossible to start.
     * APP_KEY is also excluded because it encrypts every value below.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'TURNSTILE_ENABLED' => $this->definition('Sécurité', 'Activer Turnstile', 'boolean', false, 'services.turnstile.enabled'),
            'TURNSTILE_SITE_KEY' => $this->definition('Sécurité', 'Clé publique Turnstile', 'text', false, 'services.turnstile.site_key'),
            'TURNSTILE_SECRET_KEY' => $this->definition('Sécurité', 'Clé secrète Turnstile', 'password', true, 'services.turnstile.secret_key'),

            'STRIPE_KEY' => $this->definition('Stripe', 'Clé publique Stripe', 'text', false, 'services.stripe.key'),
            'STRIPE_SECRET' => $this->definition('Stripe', 'Clé secrète Stripe', 'password', true, 'services.stripe.secret'),
            'STRIPE_WEBHOOK_SECRET' => $this->definition('Stripe', 'Secret du webhook Stripe', 'password', true, 'services.stripe.webhook_secret'),
            'STRIPE_CONNECT_COUNTRY' => $this->definition('Stripe', 'Pays Stripe Connect', 'text', false, 'services.stripe.connect_country'),
            'STRIPE_VALIDATION_DELAY_HOURS' => $this->definition('Stripe', 'Délai de validation (heures)', 'number', false, 'services.stripe.validation_delay_hours'),
            'STRIPE_PACK_VALIDATION_DELAY_HOURS' => $this->definition('Stripe', 'Délai packs (heures)', 'number', false, 'services.stripe.pack_validation_delay_hours'),

            'MAIL_MAILER' => $this->definition('E-mail / SMTP', 'Transport e-mail', 'select', false, 'mail.default', ['smtp', 'log', 'array']),
            'MAIL_HOST' => $this->definition('E-mail / SMTP', 'Serveur SMTP', 'text', false, 'mail.mailers.smtp.host'),
            'MAIL_PORT' => $this->definition('E-mail / SMTP', 'Port SMTP', 'number', false, 'mail.mailers.smtp.port'),
            'MAIL_USERNAME' => $this->definition('E-mail / SMTP', 'Utilisateur SMTP', 'text', false, 'mail.mailers.smtp.username'),
            'MAIL_PASSWORD' => $this->definition('E-mail / SMTP', 'Mot de passe / mot de passe d’application', 'password', true, 'mail.mailers.smtp.password'),
            'MAIL_ENCRYPTION' => $this->definition('E-mail / SMTP', 'Chiffrement SMTP', 'select', false, 'mail.mailers.smtp.encryption', ['tls', 'ssl', 'none']),
            'MAIL_FROM_ADDRESS' => $this->definition('E-mail / SMTP', 'Adresse expéditeur', 'email', false, 'mail.from.address'),
            'MAIL_FROM_NAME' => $this->definition('E-mail / SMTP', 'Nom expéditeur', 'text', false, 'mail.from.name'),
            'CONTACT_MAIL_TO' => $this->definition('E-mail / SMTP', 'Destinataire du formulaire de contact', 'email', false, 'services.contact.to'),

            'GOOGLE_CLIENT_ID' => $this->definition('Google', 'ID client Google OAuth Web', 'text', false, 'services.google.client_id'),
            'GOOGLE_ANDROID_CLIENT_ID' => $this->definition('Google', 'ID client Google OAuth Android', 'text', false, 'services.google.android_client_id'),
            'GOOGLE_IOS_CLIENT_ID' => $this->definition('Google', 'ID client Google OAuth iOS', 'text', false, 'services.google.ios_client_id'),
            'GOOGLE_CLIENT_SECRET' => $this->definition('Google', 'Secret client Google OAuth', 'password', true, 'services.google.client_secret'),

            'VISIO_PROVIDER' => $this->definition('Visioconférence', 'Fournisseur', 'select', false, 'services.visio.provider', ['livekit']),
            'VISIO_SERVER_URL' => $this->definition('Visioconférence', 'URL du serveur LiveKit', 'url', false, 'services.visio.server_url'),
            'VISIO_API_KEY' => $this->definition('Visioconférence', 'Clé API LiveKit', 'password', true, 'services.visio.api_key'),
            'VISIO_API_SECRET' => $this->definition('Visioconférence', 'Secret API LiveKit', 'password', true, 'services.visio.api_secret'),
            'VISIO_TOKEN_TTL' => $this->definition('Visioconférence', 'Durée du jeton (secondes)', 'number', false, 'services.visio.token_ttl'),

            'FRONTEND_URL' => $this->definition('Application', 'URL du site public', 'url', false, 'app.frontend_url'),
        ];
    }

    /** @return array<string, mixed> */
    private function definition(
        string $group,
        string $label,
        string $type,
        bool $secret,
        string $config,
        array $options = []
    ): array {
        return compact('group', 'label', 'type', 'secret', 'config', 'options');
    }

    public function isAllowed(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    /** @return array<int, array<string, mixed>> */
    public function adminPayload(): array
    {
        $stored = $this->storedValues();

        return collect($this->definitions())
            ->map(function (array $definition, string $key) use ($stored): array {
                $hasDatabaseValue = array_key_exists($key, $stored);
                $effectiveValue = $hasDatabaseValue
                    ? $stored[$key]
                    : Config::get($definition['config']);

                return [
                    'key' => $key,
                    'group' => $definition['group'],
                    'label' => $definition['label'],
                    'type' => $definition['type'],
                    'secret' => $definition['secret'],
                    'options' => $definition['options'],
                    'configured' => $hasDatabaseValue || (! is_null($effectiveValue) && $effectiveValue !== ''),
                    'source' => $hasDatabaseValue ? 'database' : 'environment',
                    'value' => $definition['secret']
                        ? ''
                        : $this->displayValue($effectiveValue, $definition),
                ];
            })
            ->values()
            ->all();
    }

    public function put(string $key, mixed $value, int $userId): void
    {
        $definition = $this->definitions()[$key] ?? null;
        abort_unless($definition, 422, 'Paramètre non autorisé.');

        abort_unless(is_scalar($value) || is_null($value), 422, 'Format de valeur invalide.');
        $normalized = $this->normalize($value, $definition);

        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $normalized, 'updated_by' => $userId]
        );

        Config::set($definition['config'], $normalized);
        $this->synchronizeAliases($key, $normalized);
    }

    public function forget(string $key): void
    {
        abort_unless($this->isAllowed($key), 404, 'Paramètre inconnu.');
        SystemSetting::query()->where('key', $key)->delete();

        $definition = $this->definitions()[$key];
        Config::set($definition['config'], null);
        $this->synchronizeAliases($key, null);
    }

    public function applyStoredSettings(): void
    {
        foreach ($this->storedValues() as $key => $value) {
            $definition = $this->definitions()[$key] ?? null;

            if (! $definition) {
                continue;
            }

            Config::set($definition['config'], $value);
            $this->synchronizeAliases($key, $value);
        }
    }

    /** @return array<string, mixed> */
    private function storedValues(): array
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return [];
            }

            return SystemSetting::query()
                ->get(['key', 'value'])
                ->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->value])
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Les paramètres dynamiques n’ont pas pu être chargés.', [
                'exception' => $exception::class,
            ]);

            return [];
        }
    }

    /** @param array<string, mixed> $definition */
    private function normalize(mixed $value, array $definition): mixed
    {
        if ($definition['type'] === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($definition['type'] === 'number') {
            abort_unless(is_numeric($value), 422, 'La valeur doit être numérique.');

            return (int) $value;
        }

        $value = trim((string) $value);
        abort_if($value === '', 422, 'La valeur ne peut pas être vide.');

        if ($definition['type'] === 'select') {
            abort_unless(in_array($value, $definition['options'], true), 422, 'Valeur non autorisée.');
            $value = $value === 'none' ? null : $value;
        }

        if ($definition['type'] === 'email') {
            abort_unless(filter_var($value, FILTER_VALIDATE_EMAIL), 422, 'Adresse e-mail invalide.');
        }

        if ($definition['type'] === 'url') {
            abort_unless(filter_var($value, FILTER_VALIDATE_URL), 422, 'URL invalide.');
        }

        return $value;
    }

    private function synchronizeAliases(string $key, mixed $value): void
    {
        if ($key === 'FRONTEND_URL') {
            Config::set('services.stripe.frontend_url', $value);
            Config::set('services.visio.frontend_url', $value);
        }
    }

    private function serialize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_null($value) ? '' : (string) $value;
    }

    /** @param array<string, mixed> $definition */
    private function displayValue(mixed $value, array $definition): string
    {
        if (is_null($value) && in_array('none', $definition['options'], true)) {
            return 'none';
        }

        return $this->serialize($value);
    }
}

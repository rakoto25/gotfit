<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TurnstileService
{
    public function enabled(): bool
    {
        return (bool) config('services.turnstile.enabled', false);
    }

    public function configured(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    public function verify(?string $token, ?string $remoteIp): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (! $this->configured() || blank($token)) {
            return false;
        }

        try {
            return Http::asForm()
                ->timeout(8)
                ->retry(2, 150, throw: false)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                    'idempotency_key' => (string) Str::uuid(),
                ])
                ->json('success') === true;
        } catch (ConnectionException) {
            return false;
        }
    }
}

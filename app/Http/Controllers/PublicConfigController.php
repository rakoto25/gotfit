<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PublicConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'stripe_publishable_key' => config('services.stripe.key'),
            'turnstile_enabled' => (bool) config('services.turnstile.enabled', false),
            'turnstile_site_key' => config('services.turnstile.enabled', false) ? config('services.turnstile.site_key') : null,
            'google_client_id' => config('services.google.client_id'),
        ])->setPublic()->setMaxAge(300);
    }
}

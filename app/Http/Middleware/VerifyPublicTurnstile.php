<?php

namespace App\Http\Middleware;

use App\Services\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPublicTurnstile
{
    public function handle(Request $request, Closure $next): Response
    {
        $turnstile = app(TurnstileService::class);
        if ($turnstile->enabled() && ! $turnstile->configured()) {
            return response()->json(['message' => 'La vérification de sécurité est temporairement indisponible.'], 503);
        }
        if (! $turnstile->verify($request->input('turnstile_token'), $request->ip())) {
            return response()->json(['message' => 'Vérification de sécurité échouée ou expirée. Rechargez le captcha et réessayez.'], 422);
        }
        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanAccessCoachForum
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $accountStatus = $user->account_status
            ?? $user->newQuery()->whereKey($user->id)->value('account_status');

        if (! $user->hasRole('intervenant') || $accountStatus !== 'approved') {
            return response()->json([
                'status' => 403,
                'message' => 'Le forum est réservé aux coachs GotFit validés.',
            ], 403);
        }

        return $next($request);
    }
}

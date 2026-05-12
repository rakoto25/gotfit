<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsClient
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // ❌ non connecté
        if (!$user) {
            return response()->json([
                'message' => 'Non authentifié'
            ], 401);
        }

        // ❌ pas intervenant
        if (!$user->roles()->where('slug', 'client')->exists()) {
            return response()->json([
                'status' => 403,
                'message' => 'Accès refusé. Seuls les clients peuvent entrer.'
            ], 403);
        }

        return $next($request);
    }
}

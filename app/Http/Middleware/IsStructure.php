<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsStructure
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Non authentifié'
            ], 401);
        }

        if (!$user->roles()->where('slug', 'structure')->exists()) {
            return response()->json([
                'status' => 403,
                'message' => 'Accès refusé. Seules les structures peuvent entrer.'
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function challenge(TurnstileService $turnstile): JsonResponse
    {
        return response()->json([
            'provider' => 'turnstile',
            'enabled' => $turnstile->enabled(),
            'configured' => $turnstile->configured(),
            'site_key' => $turnstile->configured()
                ? config('services.turnstile.site_key')
                : null,
        ]);
    }

    public function login(Request $request, TurnstileService $turnstile): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'turnstile_token' => ['nullable', 'string', 'max:4096'],
        ]);

        if ($turnstile->enabled() && ! $turnstile->configured()) {
            return response()->json([
                'message' => 'Turnstile est activé mais sa configuration est incomplète.',
            ], 503);
        }

        if (! $turnstile->verify($validated['turnstile_token'] ?? null, $request->ip())) {
            return response()->json([
                'message' => 'La vérification de sécurité a échoué. Veuillez réessayer.',
            ], 422);
        }

        $credentials = [
            'email' => mb_strtolower(trim($validated['email'])),
            'password' => $validated['password'],
        ];

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole('admin')) {
            Auth::logout();

            return response()->json([
                'message' => 'Ce compte ne possède pas les droits administrateur.',
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => $user->load('roles'),
            'token' => $user->createToken('admin_console')->plainTextToken,
        ]);
    }
}

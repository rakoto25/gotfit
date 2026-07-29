<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SocialAuthController extends Controller
{
    /**
     * Authentifie un utilisateur à partir d'un jeton Google Identity Services.
     *
     * Le compte est créé automatiquement lors de la première connexion. Le rôle
     * demandé n'est appliqué qu'à la création : une reconnexion ne peut donc pas
     * servir à élever les permissions d'un compte existant.
     */
    public function google(Request $request): JsonResponse
    {
        if ($request->filled('siret')) {
            $request->merge([
                'siret' => preg_replace('/\D+/', '', (string) $request->input('siret')),
            ]);
        }

        $validated = $request->validate([
            'credential' => ['required', 'string', 'max:4096'],
            'role' => ['nullable', Rule::in(['client', 'intervenant'])],
            'device_name' => ['nullable', 'string', 'max:100'],
            'siret' => ['nullable', 'digits:14'],
        ]);

        $clientId = (string) config('services.google.client_id');

        if ($clientId === '') {
            return response()->json([
                'message' => 'La connexion Google n’est pas encore configurée sur le serveur.',
            ], 503);
        }

        try {
            $googleResponse = Http::acceptJson()
                ->timeout(8)
                ->retry(1, 200)
                ->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $validated['credential'],
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Google est momentanément indisponible. Veuillez réessayer.',
            ], 503);
        }

        if ($googleResponse->failed()) {
            return response()->json([
                'message' => 'Le jeton Google est invalide ou a expiré.',
            ], 401);
        }

        $identity = $googleResponse->json();
        $issuer = $identity['iss'] ?? null;
        $emailVerified = filter_var(
            $identity['email_verified'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $isTrustedIdentity =
            isset($identity['sub'], $identity['email'], $identity['exp']) &&
            hash_equals($clientId, (string) ($identity['aud'] ?? '')) &&
            in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true) &&
            (int) $identity['exp'] > now()->timestamp &&
            $emailVerified;

        if (! $isTrustedIdentity) {
            return response()->json([
                'message' => 'Cette identité Google ne peut pas être vérifiée pour Gotfit.',
            ], 401);
        }

        $googleId = (string) $identity['sub'];
        $email = Str::lower(trim((string) $identity['email']));
        $roleSlug = $validated['role'] ?? 'client';
        $isNewUser = false;

        if (
            isset($validated['siret'])
            && User::where('siret', $validated['siret'])
                ->where('email', '!=', $email)
                ->where(function ($query) use ($googleId) {
                    $query->whereNull('google_id')
                        ->orWhere('google_id', '!=', $googleId);
                })
                ->exists()
        ) {
            return response()->json([
                'message' => 'Ce numéro de SIRET est déjà utilisé.',
                'errors' => [
                    'siret' => ['Ce numéro de SIRET est déjà utilisé.'],
                ],
            ], 422);
        }

        $user = DB::transaction(function () use (
            $email,
            $googleId,
            $identity,
            $roleSlug,
            $validated,
            &$isNewUser
        ) {
            $user = User::query()
                ->where('google_id', $googleId)
                ->orWhere('email', $email)
                ->lockForUpdate()
                ->first();

            if ($user && $user->google_id && ! hash_equals($user->google_id, $googleId)) {
                abort(409, 'Cette adresse email est déjà liée à un autre compte Google.');
            }

            if (! $user) {
                $isNewUser = true;
                $user = User::create([
                    'name' => trim((string) ($identity['name'] ?? 'Utilisateur Gotfit')),
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'google_id' => $googleId,
                    'auth_provider' => 'google',
                    'google_avatar_url' => $identity['picture'] ?? null,
                    'email_verified_at' => now(),
                    'last_login_at' => now(),
                    'account_status' => $roleSlug === 'intervenant' ? 'pending' : 'approved',
                    'siret' => $roleSlug === 'intervenant'
                        ? ($validated['siret'] ?? null)
                        : null,
                ]);

                $role = Role::firstOrCreate(
                    ['slug' => $roleSlug],
                    [
                        'name' => $roleSlug === 'intervenant' ? 'Intervenant' : 'Client',
                        'description' => null,
                        'is_active' => true,
                    ]
                );

                $user->roles()->syncWithoutDetaching([$role->id]);
            } else {
                $updates = [
                    'google_id' => $user->google_id ?: $googleId,
                    'auth_provider' => 'google',
                    'google_avatar_url' => $identity['picture'] ?? $user->google_avatar_url,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                    'last_login_at' => now(),
                ];

                if (
                    isset($validated['siret'])
                    && $user->hasRole('intervenant')
                    && ! $user->siret
                ) {
                    $updates['siret'] = $validated['siret'];
                }

                $user->forceFill($updates)->save();
            }

            return $user;
        });

        $token = $user
            ->createToken($validated['device_name'] ?? 'gotfit-webapp')
            ->plainTextToken;

        $user->load('roles');
        $hasProfessionalDocument = $user->documents()
            ->whereIn('document_type', ['diploma', 'certification'])
            ->exists();
        $requiresProfessionalCompletion = $user->hasRole('intervenant')
            && (! $user->siret || ! $hasProfessionalDocument);

        return response()->json([
            'message' => $isNewUser
                ? 'Votre compte Gotfit a été créé avec Google.'
                : 'Connexion Google réussie.',
            'is_new_user' => $isNewUser,
            'token' => $token,
            'user' => $user->fresh()->load('roles'),
            'professional_profile' => [
                'requires_completion' => $requiresProfessionalCompletion,
                'missing_fields' => array_values(array_filter([
                    ! $user->siret ? 'siret' : null,
                    ! $hasProfessionalDocument ? 'diploma_or_certification' : null,
                ])),
            ],
        ], $isNewUser ? 201 : 200);
    }
}

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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SocialAuthController extends Controller
{
    /**
     * Authentifie un utilisateur à partir d'un jeton
     * Google Identity Services.
     *
     * Le compte est créé automatiquement lors de la première connexion.
     * Le rôle demandé est appliqué uniquement lors de la création.
     */
    public function google(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Nettoyage du SIRET
        |--------------------------------------------------------------------------
        */

        if ($request->filled('siret')) {
            $request->merge([
                'siret' => preg_replace(
                    '/\D+/',
                    '',
                    (string) $request->input('siret')
                ),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Validation de la requête
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'credential' => [
                'required',
                'string',
                'max:4096',
            ],

            'role' => [
                'nullable',
                Rule::in([
                    'client',
                    'intervenant',
                ]),
            ],

            'device_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'siret' => [
                'nullable',
                'digits:14',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Client ID Google configuré dans Laravel
        |--------------------------------------------------------------------------
        */

        $configuredClientId = (string) config(
            'services.google.client_id'
        );

        /*
         * Retire les espaces, guillemets et chevrons qui auraient pu
         * être copiés par erreur dans le fichier .env.
         */
        $clientId = trim(
            $configuredClientId,
            " \t\n\r\0\x0B\"'<>"
        );

        if ($clientId === '') {
            return response()->json([
                'message' =>
                    'La connexion Google n’est pas encore configurée sur le serveur.',
            ], 503);
        }

        if ($configuredClientId !== $clientId) {
            Log::warning(
                'Le Client ID Google contenait des caractères parasites.',
                [
                    'original_length' => strlen(
                        $configuredClientId
                    ),

                    'normalized_length' => strlen(
                        $clientId
                    ),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification du jeton auprès de Google
        |--------------------------------------------------------------------------
        */

        try {
            $googleResponse = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(
                    2,
                    200,
                    null,
                    false
                )
                ->get(
                    'https://oauth2.googleapis.com/tokeninfo',
                    [
                        'id_token' =>
                            $validated['credential'],
                    ]
                );
        } catch (ConnectionException $exception) {
            Log::warning(
                'Google tokeninfo est inaccessible.',
                [
                    'exception' => get_class(
                        $exception
                    ),

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return response()->json([
                'message' =>
                    'Google est momentanément indisponible. Veuillez réessayer.',
            ], 503);
        }

        if ($googleResponse->failed()) {
            Log::warning(
                'Google a refusé le jeton d’identité.',
                [
                    'status' =>
                        $googleResponse->status(),
                ]
            );

            return response()->json([
                'message' =>
                    'Le jeton Google est invalide ou a expiré.',
            ], 401);
        }

        $identity = $googleResponse->json();

        if (! is_array($identity)) {
            Log::warning(
                'La réponse Google tokeninfo est incorrecte.',
                [
                    'status' =>
                        $googleResponse->status(),
                ]
            );

            return response()->json([
                'message' =>
                    'La réponse reçue de Google est invalide.',
            ], 502);
        }

        /*
        |--------------------------------------------------------------------------
        | Normalisation des informations Google
        |--------------------------------------------------------------------------
        */

        $googleId = trim(
            (string) ($identity['sub'] ?? '')
        );

        $email = Str::lower(
            trim(
                (string) ($identity['email'] ?? '')
            )
        );

        $issuer = trim(
            (string) ($identity['iss'] ?? '')
        );

        $expiration = (int) (
            $identity['exp'] ?? 0
        );

        $emailVerified = filter_var(
            $identity['email_verified'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        /*
         * Le champ aud est normalement une chaîne.
         * Cette partie accepte aussi un tableau pour rester robuste.
         */
        $audienceClaim = $identity['aud'] ?? '';

        if (is_array($audienceClaim)) {
            $audiences = array_values(
                array_filter(
                    array_map(
                        static fn ($audience): string =>
                            trim((string) $audience),
                        $audienceClaim
                    ),
                    static fn (string $audience): bool =>
                        $audience !== ''
                )
            );
        } else {
            $audience = trim(
                (string) $audienceClaim
            );

            $audiences = $audience !== ''
                ? [$audience]
                : [];
        }

        /*
        |--------------------------------------------------------------------------
        | Vérifications de sécurité
        |--------------------------------------------------------------------------
        */

        $hasRequiredClaims =
            $googleId !== ''
            && $email !== ''
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) !== false
            && $expiration > 0;

        $audienceValid = false;

        foreach ($audiences as $audience) {
            if (
                $audience !== ''
                && hash_equals(
                    $clientId,
                    $audience
                )
            ) {
                $audienceValid = true;

                break;
            }
        }

        $issuerValid = in_array(
            $issuer,
            [
                'accounts.google.com',
                'https://accounts.google.com',
            ],
            true
        );

        $serverTimestamp = now()->timestamp;

        $expirationValid =
            $expiration > $serverTimestamp;

        /*
        |--------------------------------------------------------------------------
        | Journal sécurisé en cas d’échec
        |--------------------------------------------------------------------------
        |
        | Le jeton Google, l’adresse e-mail et le Client ID complet
        | ne sont jamais enregistrés dans les logs.
        |
        */

        if (
            ! $hasRequiredClaims
            || ! $audienceValid
            || ! $issuerValid
            || ! $expirationValid
            || ! $emailVerified
        ) {
            Log::warning(
                'Échec vérification identité Google.',
                [
                    'has_required_claims' =>
                        $hasRequiredClaims,

                    'audience_valid' =>
                        $audienceValid,

                    'issuer_valid' =>
                        $issuerValid,

                    'expiration_valid' =>
                        $expirationValid,

                    'email_verified' =>
                        $emailVerified,

                    'issuer' =>
                        $issuer,

                    'expiration' =>
                        $expiration,

                    'server_timestamp' =>
                        $serverTimestamp,

                    'expected_audience_length' =>
                        strlen($clientId),

                    'received_audience_lengths' =>
                        array_map(
                            static fn (
                                string $audience
                            ): int =>
                                strlen($audience),
                            $audiences
                        ),

                    'expected_audience_hash' =>
                        substr(
                            hash(
                                'sha256',
                                $clientId
                            ),
                            0,
                            16
                        ),

                    'received_audience_hashes' =>
                        array_map(
                            static fn (
                                string $audience
                            ): string =>
                                substr(
                                    hash(
                                        'sha256',
                                        $audience
                                    ),
                                    0,
                                    16
                                ),
                            $audiences
                        ),
                ]
            );
        }

        if (
            ! $hasRequiredClaims
            || ! $audienceValid
            || ! $issuerValid
            || ! $expirationValid
        ) {
            return response()->json([
                'message' =>
                    'Cette identité Google ne peut pas être vérifiée pour Gotfit.',
            ], 401);
        }

        if (! $emailVerified) {
            return response()->json([
                'message' =>
                    'Votre adresse e-mail Google doit être vérifiée avant de continuer.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Rôle demandé
        |--------------------------------------------------------------------------
        */

        $roleSlug =
            $validated['role'] ?? 'client';

        $isNewUser = false;

        /*
        |--------------------------------------------------------------------------
        | Vérification du SIRET
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['siret'])
            && User::where(
                'siret',
                $validated['siret']
            )
                ->where(
                    'email',
                    '!=',
                    $email
                )
                ->where(
                    function ($query) use (
                        $googleId
                    ) {
                        $query
                            ->whereNull(
                                'google_id'
                            )
                            ->orWhere(
                                'google_id',
                                '!=',
                                $googleId
                            );
                    }
                )
                ->exists()
        ) {
            return response()->json([
                'message' =>
                    'Ce numéro de SIRET est déjà utilisé.',

                'errors' => [
                    'siret' => [
                        'Ce numéro de SIRET est déjà utilisé.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Création ou mise à jour du compte
        |--------------------------------------------------------------------------
        */

        $user = DB::transaction(
            function () use (
                $email,
                $googleId,
                $identity,
                $roleSlug,
                $validated,
                &$isNewUser
            ) {
                $user = User::query()
                    ->where(
                        'google_id',
                        $googleId
                    )
                    ->orWhere(
                        'email',
                        $email
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    $user
                    && $user->google_id
                    && ! hash_equals(
                        (string) $user->google_id,
                        $googleId
                    )
                ) {
                    abort(
                        409,
                        'Cette adresse email est déjà liée à un autre compte Google.'
                    );
                }

                /*
                 * Création du compte.
                 */
                if (! $user) {
                    $isNewUser = true;

                    $name = trim(
                        (string) (
                            $identity['name']
                            ?? 'Utilisateur Gotfit'
                        )
                    );

                    if ($name === '') {
                        $name =
                            'Utilisateur Gotfit';
                    }

                    $user = User::create([
                        'name' =>
                            $name,

                        'email' =>
                            $email,

                        'password' =>
                            Hash::make(
                                Str::random(64)
                            ),

                        'google_id' =>
                            $googleId,

                        'auth_provider' =>
                            'google',

                        'google_avatar_url' =>
                            isset(
                                $identity['picture']
                            )
                                ? (string) $identity['picture']
                                : null,

                        'email_verified_at' =>
                            now(),

                        'last_login_at' =>
                            now(),

                        'account_status' =>
                            $roleSlug ===
                            'intervenant'
                                ? 'pending'
                                : 'approved',

                        'siret' =>
                            $roleSlug ===
                            'intervenant'
                                ? (
                                    $validated['siret']
                                    ?? null
                                )
                                : null,
                    ]);

                    $role = Role::firstOrCreate(
                        [
                            'slug' =>
                                $roleSlug,
                        ],
                        [
                            'name' =>
                                $roleSlug ===
                                'intervenant'
                                    ? 'Intervenant'
                                    : 'Client',

                            'description' =>
                                null,

                            'is_active' =>
                                true,
                        ]
                    );

                    $user
                        ->roles()
                        ->syncWithoutDetaching([
                            $role->id,
                        ]);
                } else {
                    /*
                     * Mise à jour d’un compte existant.
                     */
                    $updates = [
                        'google_id' =>
                            $user->google_id
                                ?: $googleId,

                        'auth_provider' =>
                            'google',

                        'google_avatar_url' =>
                            isset(
                                $identity['picture']
                            )
                                ? (string) $identity['picture']
                                : $user->google_avatar_url,

                        'email_verified_at' =>
                            $user->email_verified_at
                                ?: now(),

                        'last_login_at' =>
                            now(),
                    ];

                    if (
                        isset($validated['siret'])
                        && $user->hasRole(
                            'intervenant'
                        )
                        && ! $user->siret
                    ) {
                        $updates['siret'] =
                            $validated['siret'];
                    }

                    $user
                        ->forceFill($updates)
                        ->save();
                }

                return $user;
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Création du token Sanctum
        |--------------------------------------------------------------------------
        */

        $token = $user
            ->createToken(
                $validated['device_name']
                    ?? 'gotfit-webapp'
            )
            ->plainTextToken;

        $user->load('roles');

        /*
        |--------------------------------------------------------------------------
        | Vérification du profil professionnel
        |--------------------------------------------------------------------------
        */

        $hasProfessionalDocument = $user
            ->documents()
            ->whereIn(
                'document_type',
                [
                    'diploma',
                    'certification',
                ]
            )
            ->exists();

        $requiresProfessionalCompletion =
            $user->hasRole('intervenant')
            && (
                ! $user->siret
                || ! $hasProfessionalDocument
            );

        /*
        |--------------------------------------------------------------------------
        | Réponse finale
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' => $isNewUser
                ? 'Votre compte Gotfit a été créé avec Google.'
                : 'Connexion Google réussie.',

            'is_new_user' =>
                $isNewUser,

            'token' =>
                $token,

            'user' =>
                $user
                    ->fresh()
                    ->load('roles'),

            'professional_profile' => [
                'requires_completion' =>
                    $requiresProfessionalCompletion,

                'missing_fields' =>
                    array_values(
                        array_filter([
                            ! $user->siret
                                ? 'siret'
                                : null,

                            ! $hasProfessionalDocument
                                ? 'diploma_or_certification'
                                : null,
                        ])
                    ),
            ],
        ], $isNewUser ? 201 : 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class ConnexionController extends Controller
{
    /**
     * Connexion avec email et mot de passe.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);

        $email = $this->normalizeEmail(
            $validated['email']
        );

        $authenticated = Auth::attempt([
            'email' => $email,
            'password' => $validated['password'],
        ]);

        if (!$authenticated) {
            return response()->json([
                'message' =>
                    'Email ou mot de passe incorrect.',
            ], 401);
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' =>
                    'La connexion n’a pas pu être finalisée.',
            ], 401);
        }

        $token = $user
            ->createToken('auth_token')
            ->plainTextToken;

        return response()->json([
            'status' => 200,

            'message' =>
                'Connexion réussie.',

            'user' =>
                $user->load('roles'),

            'token' =>
                $token,
        ]);
    }

    /**
     * Envoyer un lien de réinitialisation.
     */
    public function forgotPassword(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        $email = $this->normalizeEmail(
            $validated['email']
        );

        /*
         * La réponse reste volontairement générique
         * afin de ne pas révéler si le compte existe.
         */
        $genericMessage =
            'Si un compte correspond à cette adresse, '
            . 'un lien de réinitialisation a été envoyé.';

        $user = User::query()
            ->whereRaw(
                'LOWER(email) = ?',
                [$email]
            )
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 200,
                'message' => $genericMessage,
            ]);
        }

        /*
         * Le token envoyé par email reste en clair,
         * mais seule sa version hachée est stockée.
         */
        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')
            ->updateOrInsert(
                [
                    'email' => $email,
                ],
                [
                    'token' =>
                        Hash::make($plainToken),

                    'created_at' =>
                        now(),
                ]
            );

        $frontendUrl = rtrim(
            (string) config(
                'app.frontend_url',
                'http://localhost:3000'
            ),
            '/'
        );

        $query = http_build_query([
            'token' => $plainToken,
            'email' => $email,
        ]);

        $resetUrl =
            $frontendUrl
            . '/auth/reset-password?'
            . $query;

        try {
            Mail::raw(
                implode(PHP_EOL . PHP_EOL, [
                    'Bonjour,',

                    'Une demande de réinitialisation '
                    . 'du mot de passe de votre compte '
                    . 'Gotfit a été reçue.',

                    'Cliquez sur le lien suivant pour '
                    . 'choisir un nouveau mot de passe :',

                    $resetUrl,

                    'Ce lien est valable pendant 60 minutes.',

                    'Si vous n’êtes pas à l’origine de '
                    . 'cette demande, vous pouvez ignorer '
                    . 'cet email.',

                    'L’équipe Gotfit',
                ]),
                function ($message) use ($email): void {
                    $message
                        ->to($email)
                        ->subject(
                            'Réinitialisation de votre mot de passe Gotfit'
                        );
                }
            );
        } catch (Throwable $exception) {
            /*
             * Le token est retiré si l’email
             * n’a pas pu être envoyé.
             */
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            report($exception);

            return response()->json([
                'message' =>
                    'Le service de messagerie est '
                    . 'temporairement indisponible. '
                    . 'Veuillez réessayer plus tard.',
            ], 503);
        }

        return response()->json([
            'status' => 200,
            'message' => $genericMessage,
        ]);
    }

    /**
     * Enregistrer le nouveau mot de passe.
     */
    public function resetPassword(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'token' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'confirmed',

                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ]);

        $email = $this->normalizeEmail(
            $validated['email']
        );

        $record = DB::table(
            'password_reset_tokens'
        )
            ->where('email', $email)
            ->first();

        if (
            !$record ||
            !is_string($record->token) ||
            !Hash::check(
                $validated['token'],
                $record->token
            )
        ) {
            return response()->json([
                'message' =>
                    'Le lien de réinitialisation '
                    . 'est invalide ou a expiré.',
            ], 422);
        }

        if (
            !$record->created_at ||
            now()->greaterThan(
                now()
                    ->parse($record->created_at)
                    ->addMinutes(60)
            )
        ) {
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            return response()->json([
                'message' =>
                    'Le lien de réinitialisation '
                    . 'a expiré. Demandez un nouveau lien.',
            ], 422);
        }

        $user = User::query()
            ->whereRaw(
                'LOWER(email) = ?',
                [$email]
            )
            ->first();

        if (!$user) {
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            return response()->json([
                'message' =>
                    'Le lien de réinitialisation '
                    . 'est invalide ou a expiré.',
            ], 422);
        }

        DB::transaction(
            function () use (
                $user,
                $email,
                $validated
            ): void {
                $user->forceFill([
                    'password' => Hash::make(
                        $validated['password']
                    ),

                    'remember_token' =>
                        Str::random(60),
                ])->save();

                /*
                 * Déconnecte toutes les anciennes
                 * sessions Sanctum de l’utilisateur.
                 */
                $user->tokens()->delete();

                DB::table(
                    'password_reset_tokens'
                )
                    ->where('email', $email)
                    ->delete();

                event(
                    new PasswordReset($user)
                );
            }
        );

        return response()->json([
            'status' => 200,

            'message' =>
                'Votre mot de passe a été '
                . 'réinitialisé avec succès.',
        ]);
    }

    /**
     * Déconnexion de la session actuelle.
     */
    public function logout(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' =>
                    'Utilisateur non connecté.',
            ], 401);
        }

        $currentToken =
            $user->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();
        }

        return response()->json([
            'status' => 200,
            'message' =>
                'Déconnexion réussie.',
        ]);
    }

    /**
     * Uniformiser les adresses email.
     */
    private function normalizeEmail(
        string $email
    ): string {
        return Str::lower(
            trim($email)
        );
    }
}

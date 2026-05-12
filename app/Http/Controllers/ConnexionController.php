<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ConnexionController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validation
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // 2. Tentative login
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        // 3. Récupérer user
        $user = Auth::user();

        // 4. Créer token (Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 200,
            'message' => 'Connexion réussie',
            'user' => $user->load('roles'),
            'token' => $token
        ]);
    }

    public function forgotPassword(Request $request)
    {
        // Validation
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        // Générer token
        $token = Str::random(60);

        // Supprimer ancien token
        PasswordResetToken::where('email', $request->email)->delete();

        // Enregistrer nouveau token
        PasswordResetToken::create([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        Mail::raw("Cliquez ici pour réinitialiser votre mot de passe : " , function ($message) use ($request) {
            $message->to($request->email)
                    ->subject('Réinitialisation du mot de passe');
        });

        return response()->json([
            'status' => 200,
            'message' => 'Lien de réinitialisation envoyé par email'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:6|confirmed'
        ]);

        // Vérifier token
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Token invalide'
            ], 400);
        }

        // Vérifier expiration (ex: 60 min)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            return response()->json([
                'message' => 'Token expiré'
            ], 400);
        }

        // Modifier mot de passe
        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Supprimer token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès'
        ]);
    }

    // DECONNEXION
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
    
            return response()->json([
                'status' => 200,
                'message' => 'Déconnexion réussie'
            ], 200);
        }
    
        return response()->json([
            'status' => 401,
            'message' => 'Utilisateur non connecté'
        ], 401);
    }
}

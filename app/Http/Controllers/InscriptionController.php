<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class InscriptionController extends Controller
{
    /**
     * Crée un compte depuis le formulaire public et ouvre immédiatement
     * une session Sanctum, comme le parcours d'inscription Google.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['client', 'intervenant', 'structure'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'role.in' => 'Le rôle choisi est invalide. L’inscription publique accepte uniquement client, intervenant ou structure.',
        ]);

        $roleSlug = $validated['role'] ?? 'client';

        $user = DB::transaction(function () use ($validated, $roleSlug): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                // Le client peut réserver immédiatement. Les comptes professionnels
                // restent en attente de validation par l'administrateur.
                'account_status' => in_array($roleSlug, ['intervenant', 'structure'], true)
                    ? 'pending'
                    : 'approved',
            ]);

            $role = Role::firstOrCreate(
                ['slug' => $roleSlug],
                [
                    'name' => ucfirst($roleSlug),
                    'description' => null,
                    'is_active' => true,
                ]
            );

            $user->roles()->syncWithoutDetaching([$role->id]);

            return $user;
        });

        $token = $user
            ->createToken($validated['device_name'] ?? 'gotfit-webapp')
            ->plainTextToken;

        return response()->json([
            'status' => 201,
            'message' => 'Inscription réussie',
            'token' => $token,
            'user' => $user->load('roles'),
        ], 201);
    }
}

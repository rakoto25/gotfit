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
        if ($request->filled('siret')) {
            $request->merge([
                'siret' => preg_replace('/\D+/', '', (string) $request->input('siret')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['client', 'intervenant', 'structure'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'siret' => [
                'required_if:role,intervenant',
                'nullable',
                'digits:14',
                'unique:users,siret',
            ],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'role.in' => 'Le rôle choisi est invalide. L’inscription publique accepte uniquement client, intervenant ou structure.',
            'siret.required_if' => 'Le numéro de SIRET est obligatoire pour inscrire un coach.',
            'siret.digits' => 'Le numéro de SIRET doit contenir exactement 14 chiffres.',
            'siret.unique' => 'Ce numéro de SIRET est déjà utilisé.',
        ]);

        $roleSlug = $validated['role'] ?? 'client';

        $user = DB::transaction(function () use ($validated, $roleSlug): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'siret' => $roleSlug === 'intervenant'
                    ? ($validated['siret'] ?? null)
                    : null,
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
            'professional_profile' => [
                'requires_completion' => $roleSlug === 'intervenant',
                'missing_fields' => $roleSlug === 'intervenant'
                    ? ['diploma_or_certification']
                    : [],
                'required_documents' => $roleSlug === 'intervenant'
                    ? ['diploma_or_certification']
                    : [],
            ],
        ], 201);
    }
}

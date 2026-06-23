<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class InscriptionController extends Controller
{
    /**
     * Inscription publique.
     *
     * Important : seuls les comptes client et intervenant peuvent être créés
     * depuis le formulaire public/mobile. Les comptes structure et admin sont
     * créés uniquement depuis le back-office administrateur.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => ['nullable', Rule::in(['client', 'intervenant', 'structure'])],
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ], [
            'role.in' => 'Le rôle choisi est invalide. L’inscription publique accepte uniquement client, intervenant ou structure.',
        ]);

        $roleSlug = $request->role ?? 'client';

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            // Le client peut réserver immédiatement. L'intervenant doit être validé par l'admin.
            'account_status' => in_array($roleSlug, ['intervenant', 'structure'], true) ? 'pending' : 'approved',
        ]);

        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug), 'description' => null, 'is_active' => true]
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        return response()->json([
            'status' => 200,
            'message' => 'Inscription réussie',
            'user' => $user->load('roles'),
        ]);
    }
}

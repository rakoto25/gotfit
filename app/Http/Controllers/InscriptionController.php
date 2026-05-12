<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InscriptionController extends Controller
{
    public function register(Request $request)
    {
        // 1. Validation
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'nullable|in:admin,intervenant,client',
        ]);

        // 2. Créer utilisateur
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 3. Déterminer rôle (client par défaut)
        $roleSlug = $request->role ?? 'client';

        $role = Role::where('slug', $roleSlug)->first();

        if ($role) {
            $user->roles()->attach($role->id);
        }

        // 4. Response
        return response()->json([   
            'status' => 200,   
            'message' => 'Inscription réussie',
            'user' => $user->load('roles')
        ]);

    }
}

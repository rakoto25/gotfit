<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use App\Models\BusinessSetting;
use App\Models\Payement;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\Role;

class AdminController extends Controller
{

    /**
     * Création d'un utilisateur depuis le back-office.
     *
     * À utiliser pour créer les comptes structure et admin. On peut aussi
     * créer un client ou un intervenant si l'équipe veut le faire manuellement.
     */
    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => ['required', Rule::in(['client', 'intervenant', 'structure', 'admin'])],
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'account_status' => ['nullable', Rule::in(['approved', 'pending', 'rejected', 'suspended'])],
        ]);

        $roleSlug = $request->role;

        $defaultStatus = match ($roleSlug) {
            'client', 'admin' => 'approved',
            default => 'pending',
        };

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            'account_status' => $request->account_status ?? $defaultStatus,
            'validated_by' => Auth::id(),
            'validated_at' => ($request->account_status ?? $defaultStatus) === 'approved' ? now() : null,
        ]);

        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug), 'description' => null, 'is_active' => true]
        );

        $user->roles()->sync([$role->id]);

        return response()->json([
            'status' => 201,
            'message' => 'Utilisateur créé avec succès par l’administrateur',
            'user' => $user->load('roles'),
        ], 201);
    }

    public function getUsers()
    {
        $users = User::with('roles')->latest()->get();

        return response()->json([
            'status' => 200,
            'users' => $users,
        ]);
    }

    public function dashboard()
    {
        $payments = Payement::query();

        return response()->json([
            'status' => 200,
            'kpis' => [
                'inscriptions' => User::count(),
                'intervenants_valides' => User::whereHas('roles', fn ($q) => $q->where('slug', 'intervenant'))
                    ->where('account_status', 'approved')->count(),
                'structures_validees' => User::whereHas('roles', fn ($q) => $q->where('slug', 'structure'))
                    ->where('account_status', 'approved')->count(),
                'annonces_publiees' => Annonce::where('status', 'valide')->count(),
                'reservations' => Reservation::count(),
                'reservations_payees' => Reservation::where('is_paid', true)->count(),
                'avis_en_attente' => Review::where('status', 'pending')->count(),
                'chiffre_affaires' => (clone $payments)->sum('amount'),
                'commissions_gotfit' => Payement::sum('commission') + Payement::sum('service_fee'),
                'revenus_intervenants' => Payement::sum('intervenant_amount'),
                'boosts_vendus' => Annonce::where('is_boosted', true)->count(),
            ],
        ]);
    }

    public function validateUser(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,suspended,pending',
            'rejection_reason' => 'nullable|string',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'account_status' => $request->status,
            'validated_by' => Auth::id(),
            'validated_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Statut utilisateur mis à jour',
            'user' => $user->load('roles'),
        ]);
    }

    public function businessSettings()
    {
        return response()->json([
            'status' => 200,
            'settings' => BusinessSetting::orderBy('key')->get(),
        ]);
    }

    public function updateBusinessSettings(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'numeric|min:0|max:100',
        ]);

        foreach ($request->settings as $key => $value) {
            BusinessSetting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value]
            );
        }

        return response()->json([
            'status' => 200,
            'message' => 'Paramètres financiers mis à jour',
            'settings' => BusinessSetting::orderBy('key')->get(),
        ]);
    }
}

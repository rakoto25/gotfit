<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use App\Models\BusinessSetting;
use App\Models\Payement;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DASHBOARD ADMIN
    |--------------------------------------------------------------------------
    */

    public function dashboard()
    {
        $payments = Payement::query();

        return response()->json([
            'status' => 200,
            'message' => 'Dashboard admin',
            'stats' => [
                'utilisateurs' => User::count(),

                'clients' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'client')
                        ->orWhere('name', 'Client');
                })->count(),

                'intervenants' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'intervenant')
                        ->orWhere('name', 'Intervenant');
                })->count(),

                'admins' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'admin')
                        ->orWhere('name', 'Admin');
                })->count(),

                'structures' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'structure')
                        ->orWhere('name', 'Structure');
                })->count(),

                'intervenants_valides' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'intervenant')
                        ->orWhere('name', 'Intervenant');
                })
                    ->when(Schema::hasColumn('users', 'account_status'), function ($query) {
                        $query->where('account_status', 'approved');
                    })
                    ->count(),

                'structures_validees' => User::whereHas('roles', function ($q) {
                    $q->where('slug', 'structure')
                        ->orWhere('name', 'Structure');
                })
                    ->when(Schema::hasColumn('users', 'account_status'), function ($query) {
                        $query->where('account_status', 'approved');
                    })
                    ->count(),

                'annonces' => Annonce::count(),

                'annonces_publiees' => Schema::hasColumn('annonces', 'status')
                    ? Annonce::where('status', 'valide')->count()
                    : Annonce::count(),

                'reservations' => Reservation::count(),

                'reservations_payees' => Schema::hasColumn('reservations', 'is_paid')
                    ? Reservation::where('is_paid', true)->count()
                    : 0,

                'avis_en_attente' => Schema::hasColumn('reviews', 'status')
                    ? Review::where('status', 'pending')->count()
                    : 0,

                'chiffre_affaires' => Schema::hasColumn('payements', 'amount')
                    ? (clone $payments)->sum('amount')
                    : 0,

                'commissions_gotfit' => (
                    Schema::hasColumn('payements', 'commission') ? Payement::sum('commission') : 0
                ) + (
                    Schema::hasColumn('payements', 'service_fee') ? Payement::sum('service_fee') : 0
                ),

                'revenus_intervenants' => Schema::hasColumn('payements', 'intervenant_amount')
                    ? Payement::sum('intervenant_amount')
                    : 0,

                'boosts_vendus' => Schema::hasColumn('annonces', 'is_boosted')
                    ? Annonce::where('is_boosted', true)->count()
                    : 0,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LISTE UTILISATEURS
    |--------------------------------------------------------------------------
    */

    public function getUsers()
    {
        $users = User::with('roles')
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'message' => 'Liste des utilisateurs',
            'users' => $users,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CRÉATION UTILISATEUR ADMIN
    |--------------------------------------------------------------------------
    */

    public function createUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],

            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],

            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'role' => ['nullable', 'string'],
            'role_name' => ['nullable', 'string'],

            'account_status' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ];

        if (Schema::hasColumn('users', 'phone')) {
            $userData['phone'] = $data['phone'] ?? null;
        }

        if (Schema::hasColumn('users', 'address')) {
            $userData['address'] = $data['address'] ?? null;
        }

        if (Schema::hasColumn('users', 'bio')) {
            $userData['bio'] = $data['bio'] ?? null;
        }

        if (Schema::hasColumn('users', 'account_status')) {
            $userData['account_status'] = $this->normalizeAccountStatus(
                $data['account_status'] ?? $data['status'] ?? 'approved'
            );
        }

        if (Schema::hasColumn('users', 'validated_by')) {
            $userData['validated_by'] = Auth::id();
        }

        if (Schema::hasColumn('users', 'validated_at')) {
            $userData['validated_at'] = now();
        }

        $user = User::create($userData);

        $roleId = $data['role_id'] ?? $this->getRoleIdFromName(
            $data['role'] ?? $data['role_name'] ?? 'client'
        );

        if ($roleId) {
            $user->roles()->sync([$roleId]);
        }

        return response()->json([
            'status' => 201,
            'message' => 'Utilisateur créé avec succès',
            'user' => $user->load('roles'),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | MODIFICATION UTILISATEUR ADMIN
    |--------------------------------------------------------------------------
    */

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],

            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'password' => ['nullable', 'string', 'min:6'],

            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],

            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'role' => ['nullable', 'string'],
            'role_name' => ['nullable', 'string'],

            // Important : on accepte string pour éviter "The selected status is invalid"
            'account_status' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],

            'rejection_reason' => ['nullable', 'string'],
        ]);

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (Schema::hasColumn('users', 'phone') && array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
        }

        if (Schema::hasColumn('users', 'address') && array_key_exists('address', $data)) {
            $user->address = $data['address'];
        }

        if (Schema::hasColumn('users', 'bio') && array_key_exists('bio', $data)) {
            $user->bio = $data['bio'];
        }

        if (Schema::hasColumn('users', 'account_status')) {
            if (array_key_exists('account_status', $data)) {
                $user->account_status = $this->normalizeAccountStatus($data['account_status']);
            }

            if (array_key_exists('status', $data)) {
                $user->account_status = $this->normalizeAccountStatus($data['status']);
            }
        }

        if (Schema::hasColumn('users', 'rejection_reason') && array_key_exists('rejection_reason', $data)) {
            $user->rejection_reason = $data['rejection_reason'];
        }

        if (Schema::hasColumn('users', 'validated_by')) {
            $user->validated_by = Auth::id();
        }

        if (Schema::hasColumn('users', 'validated_at')) {
            $user->validated_at = now();
        }

        $user->save();

        if (
            array_key_exists('role_id', $data) ||
            array_key_exists('role', $data) ||
            array_key_exists('role_name', $data)
        ) {
            $roleId = $data['role_id'] ?? $this->getRoleIdFromName(
                $data['role'] ?? $data['role_name'] ?? 'client'
            );

            if ($roleId) {
                $user->roles()->sync([$roleId]);
            }
        }

        return response()->json([
            'status' => 200,
            'message' => 'Utilisateur modifié avec succès',
            'user' => $user->load('roles'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUPPRESSION UTILISATEUR ADMIN
    |--------------------------------------------------------------------------
    */

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        if ((int) $user->id === (int) Auth::id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte admin.',
            ], 403);
        }

        $user->roles()->detach();
        $user->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Utilisateur supprimé avec succès',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION UTILISATEUR
    |--------------------------------------------------------------------------
    */

    public function validateUser(Request $request, $id)
    {
        $request->validate([
            // Important : string pour accepter active, actif, validé, pending, etc.
            'status' => ['required', 'string'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $user = User::findOrFail($id);

        $status = $this->normalizeAccountStatus($request->status);

        $updateData = [];

        if (Schema::hasColumn('users', 'account_status')) {
            $updateData['account_status'] = $status;
        }

        if (Schema::hasColumn('users', 'validated_by')) {
            $updateData['validated_by'] = Auth::id();
        }

        if (Schema::hasColumn('users', 'validated_at')) {
            $updateData['validated_at'] = now();
        }

        if (Schema::hasColumn('users', 'rejection_reason')) {
            $updateData['rejection_reason'] = $request->rejection_reason;
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Statut utilisateur mis à jour',
            'user' => $user->load('roles'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PARAMÈTRES BUSINESS
    |--------------------------------------------------------------------------
    */

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
            'settings' => ['required', 'array'],
            'settings.*' => ['numeric', 'min:0', 'max:100'],
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

    /*
    |--------------------------------------------------------------------------
    | HELPER RÔLE
    |--------------------------------------------------------------------------
    */

    private function getRoleIdFromName($roleName)
    {
        $roleName = strtolower(trim((string) $roleName));

        $role = Role::whereRaw('LOWER(slug) = ?', [$roleName])
            ->orWhereRaw('LOWER(name) = ?', [$roleName])
            ->first();

        if ($role) {
            return $role->id;
        }

        if (str_contains($roleName, 'admin')) {
            return 1;
        }

        if (str_contains($roleName, 'intervenant') || str_contains($roleName, 'coach')) {
            return 2;
        }

        if (str_contains($roleName, 'client')) {
            return 3;
        }

        if (str_contains($roleName, 'structure')) {
            return 4;
        }

        return 3;
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER STATUT
    |--------------------------------------------------------------------------
    */

    private function normalizeAccountStatus($status)
    {
        $status = strtolower(trim((string) $status));

        return match ($status) {
            'approved',
            'approve',
            'validé',
            'valide',
            'validée',
            'active',
            'actif',
            'actif/validé',
            '1',
            'true',
            'yes',
            'oui' => 'approved',

            'rejected',
            'reject',
            'refusé',
            'refuse',
            'refusée',
            'rejeté',
            'rejetée' => 'rejected',

            'suspended',
            'suspendu',
            'suspendue',
            'inactive',
            'inactif',
            'désactivé',
            'desactive',
            'disabled',
            'bloqué',
            'bloque' => 'suspended',

            'pending',
            'en attente',
            'attente',
            'à valider',
            'a valider',
            '0',
            'false',
            'non' => 'pending',

            default => 'pending',
        };
    }
}



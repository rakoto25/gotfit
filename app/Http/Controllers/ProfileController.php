<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $user->load('roles');

        return response()->json([
            'status' => 200,
            'message' => 'Profil récupéré avec succès',
            'user' => $this->formatUserProfile($user),
        ]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        Log::info('PROFILE UPDATE FILES', [
            'has_photo' => $request->hasFile('photo'),
            'has_cover_photo' => $request->hasFile('cover_photo'),
            'all_files' => array_keys($request->allFiles()),
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'password' => ['nullable', 'string', 'min:6'],

            'bio' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],

            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'fcm_token' => ['nullable', 'string'],
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'bio' => $request->bio,
            'phone' => $request->phone,
            'address' => $request->address,
        ];

        if (Schema::hasColumn('users', 'fcm_token')) {
            $data['fcm_token'] = $request->fcm_token;
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('photo')) {
            if (
                Schema::hasColumn('users', 'photo') &&
                $user->photo &&
                Storage::disk('public')->exists($user->photo)
            ) {
                Storage::disk('public')->delete($user->photo);
            }

            if (Schema::hasColumn('users', 'photo')) {
                $data['photo'] = $request->file('photo')->store('profiles', 'public');
            }
        }

        if ($request->hasFile('cover_photo')) {
            if (
                Schema::hasColumn('users', 'cover_photo') &&
                $user->cover_photo &&
                Storage::disk('public')->exists($user->cover_photo)
            ) {
                Storage::disk('public')->delete($user->cover_photo);
            }

            if (Schema::hasColumn('users', 'cover_photo')) {
                $data['cover_photo'] = $request->file('cover_photo')->store('covers', 'public');
            }
        }

        $user->update($data);
        $user->refresh();
        $user->load('roles');

        return response()->json([
            'status' => 200,
            'debug_cover_enabled' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => $this->formatUserProfile($user),
        ]);
    }

    public function publicIntervenants()
    {
        $roleHasName = Schema::hasColumn('roles', 'name');
        $roleHasSlug = Schema::hasColumn('roles', 'slug');

        $query = User::query()
            ->with('roles')
            ->whereHas('roles', function ($roleQuery) use ($roleHasName, $roleHasSlug) {
                $roleQuery->where(function ($q) use ($roleHasName, $roleHasSlug) {
                    if ($roleHasName) {
                        $q->where('name', 'Intervenant')
                            ->orWhere('name', 'intervenant')
                            ->orWhere('name', 'INTERVENANT');
                    }

                    if ($roleHasSlug) {
                        $q->orWhere('slug', 'intervenant')
                            ->orWhere('slug', 'Intervenant')
                            ->orWhere('slug', 'INTERVENANT');
                    }
                });
            });

        if (Schema::hasColumn('users', 'account_status')) {
            $query->where(function ($statusQuery) {
                $statusQuery
                    ->where('account_status', 'approved')
                    ->orWhere('account_status', 'active')
                    ->orWhereNull('account_status');
            });
        }

        $intervenants = $query
            ->latest()
            ->get()
            ->map(function ($user) {
                return $this->formatPublicIntervenant($user);
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Intervenants récupérés avec succès',
            'data' => $intervenants,
        ]);
    }

    private function formatUserProfile(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,

            'bio' => $this->getUserValue($user, 'bio'),
            'phone' => $this->getUserValue($user, 'phone'),
            'address' => $this->getUserValue($user, 'address'),

            'account_status' => $this->getUserValue($user, 'account_status'),
            'roles' => $user->roles,

            'photo' => $this->getUserValue($user, 'photo'),
            'photo_url' => $this->getPhotoUrl($user),

            'cover_photo' => $this->getUserValue($user, 'cover_photo'),
            'cover_photo_url' => $this->getCoverPhotoUrl($user),

            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function formatPublicIntervenant(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,

            'phone' => $this->getUserValue($user, 'phone'),
            'address' => $this->getUserValue($user, 'address'),
            'city' => $this->getUserValue($user, 'city'),
            'location' => $this->getUserValue($user, 'location'),

            'bio' => $this->getUserValue($user, 'bio'),

            'photo' => $this->getUserValue($user, 'photo'),
            'photo_url' => $this->getPhotoUrl($user),

            'cover_photo' => $this->getUserValue($user, 'cover_photo'),
            'cover_photo_url' => $this->getCoverPhotoUrl($user),

            'account_status' => $this->getUserValue($user, 'account_status'),

            'speciality' => $this->getFirstAvailableUserValue($user, [
                'speciality',
                'specialty',
                'service',
            ], 'Bien-être'),

            'specialty' => $this->getFirstAvailableUserValue($user, [
                'specialty',
                'speciality',
                'service',
            ], 'Bien-être'),

            'service' => $this->getFirstAvailableUserValue($user, [
                'service',
                'speciality',
                'specialty',
            ], 'Bien-être'),

            'rating' => $this->getUserValue($user, 'rating', 4.8),
            'reviews_count' => $this->getUserValue($user, 'reviews_count', 0),

            'roles' => $user->roles,

            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function getUserValue(User $user, string $column, $default = null)
    {
        if (!Schema::hasColumn('users', $column)) {
            return $default;
        }

        return $user->{$column} ?? $default;
    }

    private function getFirstAvailableUserValue(User $user, array $columns, $default = null)
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn('users', $column)) {
                continue;
            }

            if (!empty($user->{$column})) {
                return $user->{$column};
            }
        }

        return $default;
    }

    private function getPhotoUrl(User $user)
    {
        if (Schema::hasColumn('users', 'photo_url') && !empty($user->photo_url)) {
            return $user->photo_url;
        }

        if (Schema::hasColumn('users', 'photo') && !empty($user->photo)) {
            return asset('storage/' . $user->photo);
        }

        return null;
    }

    private function getCoverPhotoUrl(User $user)
    {
        if (Schema::hasColumn('users', 'cover_photo_url') && !empty($user->cover_photo_url)) {
            return $user->cover_photo_url;
        }

        if (Schema::hasColumn('users', 'cover_photo') && !empty($user->cover_photo)) {
            return asset('storage/' . $user->cover_photo);
        }

        return null;
    }
}

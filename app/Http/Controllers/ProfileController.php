<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'status' => 200,
            'message' => 'Profil récupéré avec succès',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'phone' => $user->phone,
                'address' => $user->address,
                'account_status' => $user->account_status,
                'roles' => $user->roles,

                'photo' => $user->photo,
                'photo_url' => $user->photo_url,

                'cover_photo' => $user->cover_photo,
                'cover_photo_url' => $user->cover_photo_url,
            ],
        ]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        // Log temporaire pour vérifier si React Native envoie bien les fichiers
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
            'fcm_token' => $request->fcm_token,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('photo')) {
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }

            $data['photo'] = $request->file('photo')->store('profiles', 'public');
        }

        if ($request->hasFile('cover_photo')) {
            if ($user->cover_photo && Storage::disk('public')->exists($user->cover_photo)) {
                Storage::disk('public')->delete($user->cover_photo);
            }

            $data['cover_photo'] = $request->file('cover_photo')->store('covers', 'public');
        }

        $user->update($data);
        $user->refresh();

        return response()->json([
            'status' => 200,
            'debug_cover_enabled' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'phone' => $user->phone,
                'address' => $user->address,
                'account_status' => $user->account_status,
                'roles' => $user->roles,

                'photo' => $user->photo,
                'photo_url' => $user->photo_url,

                'cover_photo' => $user->cover_photo,
                'cover_photo_url' => $user->cover_photo_url,
            ],
        ]);
    }
}
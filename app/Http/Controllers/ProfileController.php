<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show()
    {
        return response()->json([
            'status' => 200,
            'user' => Auth::user()
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'string',
            'bio' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpg,png,jpeg'
        ]);

        $data = [
            'name' => $request->name ?? $user->name,
            'bio' => $request->bio,
            'phone' => $request->phone,
            'address' => $request->address,
        ];

        // Photo upload
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('profiles', 'public');
        }

        $user->update($data);

        return response()->json([
            'status' => 200,
            'message' => 'Profil mis à jour avec succès',
            'user' => $user
        ]);
    }
}

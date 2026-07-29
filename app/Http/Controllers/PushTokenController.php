<?php

namespace App\Http\Controllers;

use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required_without:expo_push_token', 'nullable', 'string', 'max:255'],
            'expo_push_token' => ['required_without:token', 'nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $token = $data['token'] ?? $data['expo_push_token'];

        if (! preg_match('/^(Expo|Exponent)PushToken\[[^\]]+\]$/', $token)) {
            return response()->json([
                'status' => 422,
                'message' => 'Jeton Expo Push invalide.',
            ], 422);
        }

        $pushToken = PushToken::updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
                'device_name' => $data['device_name'] ?? 'gotfit-mobile',
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'status' => 200,
            'push_token' => $pushToken,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);

        PushToken::where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['status' => 200, 'deleted' => true]);
    }
}

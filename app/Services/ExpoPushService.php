<?php

namespace App\Services;

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = []
    ): void {
        $tokens = $user->pushTokens()->pluck('token')->values();

        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens->map(fn (string $token) => [
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'channelId' => 'messages',
            'priority' => 'high',
        ])->all();

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->post('https://exp.host/--/api/v2/push/send', $messages);

            if (! $response->successful()) {
                Log::warning('Expo Push a refusé la notification', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            foreach ((array) $response->json('data', []) as $index => $ticket) {
                if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    PushToken::where('token', $tokens->get($index))->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Notification Expo Push non envoyée', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

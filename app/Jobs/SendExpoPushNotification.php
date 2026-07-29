<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendExpoPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(
        private readonly int $userId,
        private readonly string $title,
        private readonly string $body,
        private readonly array $data = []
    ) {}

    public function handle(ExpoPushService $push): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $push->sendToUser($user, $this->title, $this->body, $this->data);
    }
}

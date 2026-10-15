<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ChatMessageNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = $this->message->loadMissing('sender');
        $senderName = $message->sender?->display_name ?: $message->sender?->name ?: 'Un utilisateur';

        return [
            'type' => 'message',
            'title' => 'Nouveau message de '.$senderName,
            'body' => $message->message
                ? Str::limit($message->message, 140)
                : ($message->media_type === 'video' ? 'Vidéo reçue' : 'Photo reçue'),
            'url' => '/messages?conversation_id='.$message->conversation_id,
            'conversation_id' => (int) $message->conversation_id,
            'message_id' => (int) $message->id,
            'actor_id' => (int) $message->sender_id,
            'actor_name' => $senderName,
        ];
    }
}

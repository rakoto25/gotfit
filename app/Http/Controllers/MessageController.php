<?php

namespace App\Http\Controllers;

use App\Models\Conversations;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function inbox()
    {
        $userId = Auth::id();
        $conversations = Conversations::with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->where('client_id', $userId)
            ->orWhere('intervenant_id', $userId)
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'conversations' => $conversations]);
    }

    public function createConversation($otherUserId)
    {
        $user = Auth::user();
        $currentId = $user->id;

        // Garde compatibilité avec l'ancien modèle : une conversation garde client_id + intervenant_id.
        $clientId = $user->hasRole('client') ? $currentId : $otherUserId;
        $intervenantId = $user->hasRole('intervenant') ? $currentId : $otherUserId;

        $conversation = Conversations::firstOrCreate([
            'client_id' => $clientId,
            'intervenant_id' => $intervenantId,
        ]);

        return response()->json(['status' => 200, 'conversation' => $conversation]);
    }

    public function getMessages($conversation_id)
    {
        $conversation = Conversations::findOrFail($conversation_id);
        $this->authorizeConversation($conversation);

        $messages = Message::where('conversation_id', $conversation_id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['status' => 200, 'messages' => $messages]);
    }

    public function sendMessage(Request $request, $conversation_id)
    {
        $request->validate(['message' => 'required|string']);
        $conversation = Conversations::findOrFail($conversation_id);
        $this->authorizeConversation($conversation);

        $message = Message::create([
            'conversation_id' => $conversation_id,
            'sender_id' => Auth::id(),
            'message' => $request->message,
        ]);

        return response()->json(['status' => 200, 'message' => $message]);
    }

    private function authorizeConversation(Conversations $conversation): void
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless(
            (int) $conversation->client_id === (int) $user->id ||
            (int) $conversation->intervenant_id === (int) $user->id,
            403,
            'Non autorisé'
        );
    }
}

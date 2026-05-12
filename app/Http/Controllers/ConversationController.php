<?php

namespace App\Http\Controllers;

use App\Models\Conversations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    public function inbox()
    {
        $user_id = Auth::id();

        $conversations = Conversations::where('client_id', $user_id)
            ->orWhere('intervenant_id', $user_id)
            ->with('messages')
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'conversations' => $conversations
        ]);
    }

    public function createConversation($intervenant_id)
    {
        $client_id = Auth::id();

        $conversation = Conversations::where('client_id', $client_id)
            ->where('intervenant_id', $intervenant_id)
            ->first();

        if (!$conversation) {
            $conversation = Conversations::create([
                'client_id' => $client_id,
                'intervenant_id' => $intervenant_id,
            ]);
        }

        return response()->json([
            'status' => 200,
            'conversation' => $conversation
        ]);
    }
}

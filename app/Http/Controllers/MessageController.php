<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function getMessages($conversation_id)
    {
        $messages = Message::where('conversation_id', $conversation_id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 200,
            'messages' => $messages
        ]);
    }
    
    public function sendMessage(Request $request, $conversation_id)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $message = Message::create([
            'conversation_id' => $conversation_id,
            'sender_id' => Auth::id(),
            'message' => $request->message
        ]);

        return response()->json([
            'status' => 200,
            'message' => $message
        ]);
    }
}

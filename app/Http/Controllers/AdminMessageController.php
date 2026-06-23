<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class AdminMessageController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('messages')) {
            return response()->json([
                'status' => 200,
                'messages' => [],
                'message' => 'La table messages n’existe pas encore.',
            ]);
        }

        $adminId = Auth::id();

        $messages = DB::table('messages')
            ->orderByDesc(Schema::hasColumn('messages', 'created_at') ? 'created_at' : 'id')
            ->get()
            ->map(function ($message) use ($adminId) {
                $senderId = $message->sender_id ?? null;
                $receiverId = $message->receiver_id ?? null;

                if (!$receiverId && isset($message->conversation_id)) {
                    $receiverId = $this->guessReceiverIdFromConversation(
                        $message->conversation_id,
                        $senderId,
                        $adminId
                    );
                }

                $sender = null;
                $receiver = null;

                if ($senderId) {
                    $sender = User::select('id', 'name', 'email')->find($senderId);
                }

                if ($receiverId) {
                    $receiver = User::select('id', 'name', 'email')->find($receiverId);
                }

                $text = $message->message
                    ?? $message->body
                    ?? $message->content
                    ?? '';

                return [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id ?? null,
                    'sender_id' => $senderId,
                    'receiver_id' => $receiverId,
                    'subject' => $message->subject ?? $message->title ?? 'Sans sujet',
                    'message' => $text,
                    'body' => $text,
                    'content' => $text,
                    'is_read' => (bool) ($message->is_read ?? false),
                    'read_at' => $message->read_at ?? null,
                    'replied_at' => $message->replied_at ?? null,
                    'status' => $message->status ?? null,
                    'created_at' => $message->created_at ?? null,
                    'updated_at' => $message->updated_at ?? null,
                    'sender' => $sender,
                    'receiver' => $receiver,
                ];
            });

        return response()->json([
            'status' => 200,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('messages')) {
            return response()->json([
                'status' => 500,
                'message' => 'La table messages n’existe pas.',
            ], 500);
        }

        $receiverId = $request->input('receiver_id')
            ?? $request->input('recipient_id')
            ?? $request->input('user_id')
            ?? $request->input('to_user_id');

        $messageText = $request->input('message')
            ?? $request->input('body')
            ?? $request->input('content');

        $subject = $request->input('subject')
            ?? $request->input('title')
            ?? 'Message admin';

        $validator = Validator::make([
            'receiver_id' => $receiverId,
            'subject' => $subject,
            'message' => $messageText,
        ], [
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Données invalides.',
                'errors' => $validator->errors(),
                'received' => $request->all(),
            ], 422);
        }

        $conversationId = $this->getOrCreateConversationId((int) $receiverId);

        if (Schema::hasColumn('messages', 'conversation_id') && !$conversationId) {
            return response()->json([
                'status' => 500,
                'message' => 'Impossible de créer ou récupérer une conversation.',
            ], 500);
        }

        $insertData = $this->buildMessageInsertData(
            (int) $receiverId,
            $subject,
            $messageText,
            $conversationId
        );

        $id = DB::table('messages')->insertGetId($insertData);

        return response()->json([
            'status' => 201,
            'message' => 'Message envoyé avec succès.',
            'data' => DB::table('messages')->where('id', $id)->first(),
        ], 201);
    }

    public function markAsRead($id)
    {
        if (!Schema::hasTable('messages')) {
            return response()->json([
                'status' => 404,
                'message' => 'Table messages introuvable.',
            ], 404);
        }

        $message = DB::table('messages')->where('id', $id)->first();

        if (!$message) {
            return response()->json([
                'status' => 404,
                'message' => 'Message introuvable.',
            ], 404);
        }

        $updateData = [];

        if (Schema::hasColumn('messages', 'is_read')) {
            $updateData['is_read'] = true;
        }

        if (Schema::hasColumn('messages', 'read_at')) {
            $updateData['read_at'] = now();
        }

        if (Schema::hasColumn('messages', 'updated_at')) {
            $updateData['updated_at'] = now();
        }

        if (!empty($updateData)) {
            DB::table('messages')->where('id', $id)->update($updateData);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Message marqué comme lu.',
        ]);
    }

    public function reply(Request $request, $id)
    {
        if (!Schema::hasTable('messages')) {
            return response()->json([
                'status' => 404,
                'message' => 'Table messages introuvable.',
            ], 404);
        }

        $original = DB::table('messages')->where('id', $id)->first();

        if (!$original) {
            return response()->json([
                'status' => 404,
                'message' => 'Message introuvable.',
            ], 404);
        }

        $receiverId = $request->input('receiver_id')
            ?? $request->input('recipient_id')
            ?? $request->input('user_id')
            ?? $request->input('to_user_id')
            ?? $original->sender_id
            ?? null;

        $messageText = $request->input('message')
            ?? $request->input('body')
            ?? $request->input('content');

        $subject = $request->input('subject')
            ?? $request->input('title')
            ?? 'Réponse admin';

        $validator = Validator::make([
            'receiver_id' => $receiverId,
            'subject' => $subject,
            'message' => $messageText,
        ], [
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Données invalides.',
                'errors' => $validator->errors(),
                'received' => $request->all(),
            ], 422);
        }

        $conversationId = $original->conversation_id
            ?? $this->getOrCreateConversationId((int) $receiverId);

        if (Schema::hasColumn('messages', 'conversation_id') && !$conversationId) {
            return response()->json([
                'status' => 500,
                'message' => 'Impossible de créer ou récupérer une conversation.',
            ], 500);
        }

        $insertData = $this->buildMessageInsertData(
            (int) $receiverId,
            $subject,
            $messageText,
            $conversationId
        );

        $replyId = DB::table('messages')->insertGetId($insertData);

        $originalUpdate = [];

        if (Schema::hasColumn('messages', 'replied_at')) {
            $originalUpdate['replied_at'] = now();
        }

        if (Schema::hasColumn('messages', 'updated_at')) {
            $originalUpdate['updated_at'] = now();
        }

        if (!empty($originalUpdate)) {
            DB::table('messages')->where('id', $id)->update($originalUpdate);
        }

        return response()->json([
            'status' => 201,
            'message' => 'Réponse envoyée avec succès.',
            'data' => DB::table('messages')->where('id', $replyId)->first(),
        ], 201);
    }

    public function destroy($id)
    {
        if (!Schema::hasTable('messages')) {
            return response()->json([
                'status' => 404,
                'message' => 'Table messages introuvable.',
            ], 404);
        }

        $message = DB::table('messages')->where('id', $id)->first();

        if (!$message) {
            return response()->json([
                'status' => 404,
                'message' => 'Message introuvable.',
            ], 404);
        }

        DB::table('messages')->where('id', $id)->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Message supprimé avec succès.',
        ]);
    }

    private function buildMessageInsertData(
        int $receiverId,
        ?string $subject,
        string $messageText,
        $conversationId = null
    ): array {
        $insertData = [];

        if (Schema::hasColumn('messages', 'conversation_id')) {
            $insertData['conversation_id'] = $conversationId;
        }

        if (Schema::hasColumn('messages', 'sender_id')) {
            $insertData['sender_id'] = Auth::id();
        }

        if (Schema::hasColumn('messages', 'receiver_id')) {
            $insertData['receiver_id'] = $receiverId;
        }

        if (Schema::hasColumn('messages', 'subject')) {
            $insertData['subject'] = $subject ?: 'Message admin';
        } elseif (Schema::hasColumn('messages', 'title')) {
            $insertData['title'] = $subject ?: 'Message admin';
        }

        if (Schema::hasColumn('messages', 'message')) {
            $insertData['message'] = $messageText;
        } elseif (Schema::hasColumn('messages', 'body')) {
            $insertData['body'] = $messageText;
        } elseif (Schema::hasColumn('messages', 'content')) {
            $insertData['content'] = $messageText;
        }

        if (Schema::hasColumn('messages', 'is_read')) {
            $insertData['is_read'] = true;
        }

        if (Schema::hasColumn('messages', 'read_at')) {
            $insertData['read_at'] = now();
        }

        if (Schema::hasColumn('messages', 'status')) {
            $insertData['status'] = 'sent';
        }

        if (Schema::hasColumn('messages', 'created_at')) {
            $insertData['created_at'] = now();
        }

        if (Schema::hasColumn('messages', 'updated_at')) {
            $insertData['updated_at'] = now();
        }

        return $insertData;
    }

    private function getOrCreateConversationId($receiverId)
    {
        if (!Schema::hasTable('conversations')) {
            return null;
        }

        $adminId = Auth::id();

        if (!$adminId || !$receiverId) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Priorité absolue : conversations.client_id
        |--------------------------------------------------------------------------
        */

        if (Schema::hasColumn('conversations', 'client_id')) {
            $query = DB::table('conversations')->where('client_id', $receiverId);

            if (Schema::hasColumn('conversations', 'intervenant_id')) {
                $query->where('intervenant_id', $adminId);
            }

            if (Schema::hasColumn('conversations', 'admin_id')) {
                $query->where('admin_id', $adminId);
            }

            $existing = $query->first();

            if ($existing) {
                return $existing->id;
            }

            $data = [
                'client_id' => $receiverId,
            ];

            if (Schema::hasColumn('conversations', 'intervenant_id')) {
                $data['intervenant_id'] = $adminId;
            }

            if (Schema::hasColumn('conversations', 'admin_id')) {
                $data['admin_id'] = $adminId;
            }

            if (Schema::hasColumn('conversations', 'sender_id')) {
                $data['sender_id'] = $adminId;
            }

            if (Schema::hasColumn('conversations', 'receiver_id')) {
                $data['receiver_id'] = $receiverId;
            }

            if (Schema::hasColumn('conversations', 'user_id')) {
                $data['user_id'] = $receiverId;
            }

            if (Schema::hasColumn('conversations', 'user_one_id')) {
                $data['user_one_id'] = $adminId;
            }

            if (Schema::hasColumn('conversations', 'user_two_id')) {
                $data['user_two_id'] = $receiverId;
            }

            if (Schema::hasColumn('conversations', 'status')) {
                $data['status'] = 'active';
            }

            if (Schema::hasColumn('conversations', 'type')) {
                $data['type'] = 'admin';
            }

            if (Schema::hasColumn('conversations', 'created_at')) {
                $data['created_at'] = now();
            }

            if (Schema::hasColumn('conversations', 'updated_at')) {
                $data['updated_at'] = now();
            }

            return DB::table('conversations')->insertGetId($data);
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback : conversations.user_one_id / user_two_id
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn('conversations', 'user_one_id') &&
            Schema::hasColumn('conversations', 'user_two_id')
        ) {
            $existing = DB::table('conversations')
                ->where(function ($q) use ($adminId, $receiverId) {
                    $q->where('user_one_id', $adminId)
                        ->where('user_two_id', $receiverId);
                })
                ->orWhere(function ($q) use ($adminId, $receiverId) {
                    $q->where('user_one_id', $receiverId)
                        ->where('user_two_id', $adminId);
                })
                ->first();

            if ($existing) {
                return $existing->id;
            }

            $data = [
                'user_one_id' => $adminId,
                'user_two_id' => $receiverId,
            ];

            if (Schema::hasColumn('conversations', 'created_at')) {
                $data['created_at'] = now();
            }

            if (Schema::hasColumn('conversations', 'updated_at')) {
                $data['updated_at'] = now();
            }

            return DB::table('conversations')->insertGetId($data);
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback : conversations.sender_id / receiver_id
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn('conversations', 'sender_id') &&
            Schema::hasColumn('conversations', 'receiver_id')
        ) {
            $existing = DB::table('conversations')
                ->where(function ($q) use ($adminId, $receiverId) {
                    $q->where('sender_id', $adminId)
                        ->where('receiver_id', $receiverId);
                })
                ->orWhere(function ($q) use ($adminId, $receiverId) {
                    $q->where('sender_id', $receiverId)
                        ->where('receiver_id', $adminId);
                })
                ->first();

            if ($existing) {
                return $existing->id;
            }

            $data = [
                'sender_id' => $adminId,
                'receiver_id' => $receiverId,
            ];

            if (Schema::hasColumn('conversations', 'created_at')) {
                $data['created_at'] = now();
            }

            if (Schema::hasColumn('conversations', 'updated_at')) {
                $data['updated_at'] = now();
            }

            return DB::table('conversations')->insertGetId($data);
        }

        return null;
    }

    private function guessReceiverIdFromConversation($conversationId, $senderId, $adminId)
    {
        if (!Schema::hasTable('conversations') || !$conversationId) {
            return null;
        }

        $conversation = DB::table('conversations')->where('id', $conversationId)->first();

        if (!$conversation) {
            return null;
        }

        if (isset($conversation->receiver_id) && $conversation->receiver_id) {
            return $conversation->receiver_id;
        }

        if (isset($conversation->client_id) && $conversation->client_id) {
            if ((int) $conversation->client_id !== (int) $senderId) {
                return $conversation->client_id;
            }
        }

        if (isset($conversation->intervenant_id) && $conversation->intervenant_id) {
            if ((int) $conversation->intervenant_id !== (int) $senderId) {
                return $conversation->intervenant_id;
            }
        }

        if (isset($conversation->admin_id) && $conversation->admin_id) {
            if ((int) $conversation->admin_id !== (int) $senderId) {
                return $conversation->admin_id;
            }
        }

        if (isset($conversation->user_two_id) && $conversation->user_two_id) {
            if ((int) $conversation->user_two_id !== (int) $senderId) {
                return $conversation->user_two_id;
            }
        }

        if (isset($conversation->user_one_id) && $conversation->user_one_id) {
            if ((int) $conversation->user_one_id !== (int) $senderId) {
                return $conversation->user_one_id;
            }
        }

        return $adminId;
    }
}

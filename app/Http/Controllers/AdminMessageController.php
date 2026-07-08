<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminMessageController extends Controller
{
    /**
     * Liste des messages administrateur.
     *
     * Filtres optionnels :
     * - ?broadcast=1 : uniquement les messages envoyés en diffusion
     * - ?receiver_id=23 : messages d'un destinataire précis
     * - ?conversation_id=10 : messages d'une conversation précise
     * - ?limit=100 : limiter le nombre de résultats
     */
    public function index(Request $request)
    {
        if (!Schema::hasTable('messages')) {
            return response()->json([
                'status' => 200,
                'messages' => [],
                'message' => 'La table messages n’existe pas encore.',
            ]);
        }

        $adminId = Auth::id();
        $orderColumn = Schema::hasColumn('messages', 'created_at') ? 'created_at' : 'id';

        $query = DB::table('messages')
            ->orderByDesc($orderColumn);

        if ($request->filled('conversation_id') && Schema::hasColumn('messages', 'conversation_id')) {
            $query->where('conversation_id', (int) $request->input('conversation_id'));
        }

        if ($request->filled('receiver_id') && Schema::hasColumn('messages', 'receiver_id')) {
            $query->where('receiver_id', (int) $request->input('receiver_id'));
        }

        if ($request->has('broadcast') && Schema::hasColumn('messages', 'is_admin_broadcast')) {
            $query->where('is_admin_broadcast', $request->boolean('broadcast'));
        }

        if ($request->filled('broadcast_group') && Schema::hasColumn('messages', 'broadcast_group')) {
            $query->where('broadcast_group', (string) $request->input('broadcast_group'));
        }

        if ($request->filled('limit')) {
            $query->limit(max(1, min((int) $request->input('limit'), 500)));
        }

        $messages = $query
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
                    'type' => $message->type ?? 'text',
                    'is_read' => (bool) ($message->is_read ?? false),
                    'read_at' => $message->read_at ?? null,
                    'replied_at' => $message->replied_at ?? null,
                    'status' => $message->status ?? null,
                    'is_admin_broadcast' => (bool) ($message->is_admin_broadcast ?? false),
                    'broadcast_group' => $message->broadcast_group ?? null,
                    'broadcast_target_role' => $message->broadcast_target_role ?? null,
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

    /**
     * Liste des coachs/intervenants qui peuvent recevoir une diffusion admin.
     *
     * Query optionnelle :
     * - ?only_approved=1 : ne retourne que les coachs approuvés
     */
    public function coaches(Request $request)
    {
        $coaches = $this->getCoachRecipients($request->boolean('only_approved', false))
            ->map(fn (User $coach) => $this->formatRecipient($coach))
            ->values();

        return response()->json([
            'status' => 200,
            'count' => $coaches->count(),
            'coaches' => $coaches,
        ]);
    }

    /**
     * Envoyer un message admin à un seul utilisateur.
     *
     * Compatibilité ajoutée :
     * si send_to_all_coaches / broadcast_to_coaches / target=coaches est envoyé,
     * cette méthode redirige automatiquement vers la diffusion à tous les coachs.
     */
    public function store(Request $request)
    {
        if ($this->requestAsksCoachBroadcast($request)) {
            return $this->broadcastToCoaches($request);
        }

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

        $messageText = $this->extractMessageText($request);

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
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Données invalides.',
                'errors' => $validator->errors(),
                'received' => $request->all(),
            ], 422);
        }

        if ((int) $receiverId === (int) Auth::id()) {
            return response()->json([
                'status' => 422,
                'message' => 'Vous ne pouvez pas envoyer un message admin à vous-même.',
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
            $conversationId,
            false
        );

        $id = DB::table('messages')->insertGetId($insertData);
        $this->touchConversation($conversationId);

        return response()->json([
            'status' => 201,
            'message' => 'Message envoyé avec succès.',
            'data' => DB::table('messages')->where('id', $id)->first(),
        ], 201);
    }

    /**
     * PRIORITÉ 6 – COMMUNICATION
     * Envoyer le même message administrateur à tous les coachs/intervenants.
     *
     * Champs acceptés :
     * - subject ou title : sujet optionnel
     * - message ou body ou content : contenu obligatoire
     * - only_approved : true pour limiter aux coachs approuvés
     * - exclude_ids : tableau d'IDs utilisateurs à ignorer
     * - dry_run : true pour tester sans créer de messages
     */
    public function broadcastToCoaches(Request $request)
    {
        if (!Schema::hasTable('messages')) {
            return response()->json([
                'status' => 500,
                'message' => 'La table messages n’existe pas.',
            ], 500);
        }

        $messageText = $this->extractMessageText($request);
        $subject = $request->input('subject')
            ?? $request->input('title')
            ?? 'Message GotFit';

        $validator = Validator::make([
            'subject' => $subject,
            'message' => $messageText,
            'only_approved' => $request->input('only_approved'),
            'exclude_ids' => $request->input('exclude_ids', []),
            'dry_run' => $request->input('dry_run'),
        ], [
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'only_approved' => ['nullable', 'boolean'],
            'exclude_ids' => ['nullable', 'array'],
            'exclude_ids.*' => ['integer', 'exists:users,id'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Données invalides.',
                'errors' => $validator->errors(),
                'received' => $request->all(),
            ], 422);
        }

        $onlyApproved = $request->boolean('only_approved', false);
        $dryRun = $request->boolean('dry_run', false);
        $excludeIds = collect($request->input('exclude_ids', []))
            ->map(fn ($id) => (int) $id)
            ->push((int) Auth::id())
            ->unique()
            ->values();

        $recipients = $this->getCoachRecipients($onlyApproved)
            ->reject(fn (User $coach) => $excludeIds->contains((int) $coach->id))
            ->values();

        if ($recipients->isEmpty()) {
            return response()->json([
                'status' => 200,
                'message' => 'Aucun coach/intervenant destinataire trouvé.',
                'sent_count' => 0,
                'failed_count' => 0,
                'recipients_count' => 0,
                'recipients' => [],
            ]);
        }

        if ($dryRun) {
            return response()->json([
                'status' => 200,
                'message' => 'Simulation terminée : aucun message n’a été créé.',
                'dry_run' => true,
                'recipients_count' => $recipients->count(),
                'recipients' => $recipients->map(fn (User $coach) => $this->formatRecipient($coach))->values(),
            ]);
        }

        $broadcastGroup = (string) Str::uuid();
        $sent = [];
        $failed = [];

        foreach ($recipients as $coach) {
            try {
                $messageId = DB::transaction(function () use ($coach, $subject, $messageText, $broadcastGroup) {
                    $conversationId = $this->getOrCreateConversationId((int) $coach->id);

                    if (Schema::hasColumn('messages', 'conversation_id') && !$conversationId) {
                        throw new \RuntimeException('Impossible de créer ou récupérer une conversation.');
                    }

                    $insertData = $this->buildMessageInsertData(
                        (int) $coach->id,
                        $subject,
                        $messageText,
                        $conversationId,
                        false,
                        [
                            'is_admin_broadcast' => true,
                            'broadcast_group' => $broadcastGroup,
                            'broadcast_target_role' => 'intervenant',
                        ]
                    );

                    $messageId = DB::table('messages')->insertGetId($insertData);
                    $this->touchConversation($conversationId);

                    return $messageId;
                });

                $sent[] = [
                    'message_id' => $messageId,
                    'recipient' => $this->formatRecipient($coach),
                ];
            } catch (\Throwable $exception) {
                report($exception);

                $failed[] = [
                    'recipient' => $this->formatRecipient($coach),
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $statusCode = count($sent) > 0 ? 201 : 500;

        return response()->json([
            'status' => $statusCode,
            'message' => count($failed) === 0
                ? 'Message envoyé à tous les coachs avec succès.'
                : 'Diffusion terminée avec certaines erreurs.',
            'broadcast_group' => $broadcastGroup,
            'target_role' => 'intervenant',
            'only_approved' => $onlyApproved,
            'recipients_count' => $recipients->count(),
            'sent_count' => count($sent),
            'failed_count' => count($failed),
            'sent' => $sent,
            'failed' => $failed,
        ], $statusCode);
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

        $messageText = $this->extractMessageText($request);

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
            'message' => ['required', 'string', 'max:5000'],
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
            $conversationId,
            false
        );

        $replyId = DB::table('messages')->insertGetId($insertData);
        $this->touchConversation($conversationId);

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
        $conversationId = null,
        bool $isRead = false,
        array $extra = []
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

        if (Schema::hasColumn('messages', 'type')) {
            $insertData['type'] = 'text';
        }

        if (Schema::hasColumn('messages', 'is_read')) {
            $insertData['is_read'] = $isRead;
        }

        if ($isRead && Schema::hasColumn('messages', 'read_at')) {
            $insertData['read_at'] = now();
        }

        if (Schema::hasColumn('messages', 'status')) {
            $insertData['status'] = 'sent';
        }

        foreach ($extra as $column => $value) {
            if (Schema::hasColumn('messages', $column)) {
                $insertData[$column] = $value;
            }
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
        | Schéma GotFit actuel : conversations.client_id / intervenant_id
        |--------------------------------------------------------------------------
        |
        | Pour un message admin vers un coach :
        | - client_id = coach destinataire
        | - intervenant_id = admin expéditeur
        |
        | Ainsi le coach voit la conversation dans sa messagerie existante,
        | car son id est présent dans client_id ou intervenant_id.
        */

        if (
            Schema::hasColumn('conversations', 'client_id') &&
            Schema::hasColumn('conversations', 'intervenant_id')
        ) {
            $existing = DB::table('conversations')
                ->where(function ($q) use ($adminId, $receiverId) {
                    $q->where('client_id', $receiverId)
                        ->where('intervenant_id', $adminId);
                })
                ->orWhere(function ($q) use ($adminId, $receiverId) {
                    $q->where('client_id', $adminId)
                        ->where('intervenant_id', $receiverId);
                })
                ->first();

            if ($existing) {
                return $existing->id;
            }

            $data = [
                'client_id' => $receiverId,
                'intervenant_id' => $adminId,
            ];

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
        | Fallback : conversations.client_id seul
        |--------------------------------------------------------------------------
        */

        if (Schema::hasColumn('conversations', 'client_id')) {
            $query = DB::table('conversations')->where('client_id', $receiverId);

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

            if (Schema::hasColumn('conversations', 'admin_id')) {
                $data['admin_id'] = $adminId;
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

    private function getCoachRecipients(bool $onlyApproved = false)
    {
        $query = User::with('roles')
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereRaw('LOWER(slug) IN (?, ?)', ['intervenant', 'coach'])
                    ->orWhereRaw('LOWER(name) IN (?, ?)', ['intervenant', 'coach']);
            })
            ->orderBy('name');

        if ($onlyApproved && Schema::hasColumn('users', 'account_status')) {
            $query->where('account_status', 'approved');
        }

        return $query->get();
    }

    private function formatRecipient(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'account_status' => $user->account_status ?? null,
            'roles' => $user->roles
                ? $user->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ])->values()
                : [],
        ];
    }

    private function requestAsksCoachBroadcast(Request $request): bool
    {
        if ($request->boolean('send_to_all_coaches')) {
            return true;
        }

        if ($request->boolean('broadcast_to_coaches')) {
            return true;
        }

        if ($request->boolean('to_all_coaches')) {
            return true;
        }

        $target = strtolower((string) ($request->input('target') ?? $request->input('broadcast_target') ?? ''));

        return in_array($target, ['coaches', 'coach', 'intervenants', 'intervenant'], true);
    }

    private function extractMessageText(Request $request): ?string
    {
        $messageText = $request->input('message')
            ?? $request->input('body')
            ?? $request->input('content');

        if ($messageText === null) {
            return null;
        }

        return trim((string) $messageText);
    }

    private function touchConversation($conversationId): void
    {
        if (
            $conversationId &&
            Schema::hasTable('conversations') &&
            Schema::hasColumn('conversations', 'updated_at')
        ) {
            DB::table('conversations')
                ->where('id', $conversationId)
                ->update(['updated_at' => now()]);
        }
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

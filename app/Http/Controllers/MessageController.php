<?php

namespace App\Http\Controllers;

use App\Models\Conversations;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class MessageController extends Controller
{
    /**
     * Liste des contacts disponibles pour démarrer une conversation.
     *
     * Accessible aux clients, intervenants et admins connectés.
     */
    public function contacts()
    {
        $currentUser = Auth::user();

        if (!$currentUser) {
            return response()->json([
                'status' => 401,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        $users = User::with('roles')
            ->where('id', '!=', $currentUser->id)
            ->where(function ($query) {
                /**
                 * Si la colonne account_status existe :
                 * - on garde les utilisateurs approved
                 * - on garde aussi les admins, même si pending
                 */
                if (Schema::hasColumn('users', 'account_status')) {
                    $query->where('account_status', 'approved')
                        ->orWhereHas('roles', function ($roleQuery) {
                            $roleQuery->where('slug', 'admin')
                                ->orWhere('name', 'Admin');
                        });
                }
            })
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $role = $user->roles->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'photo_url' => $user->photo_url ?? null,
                    'role' => $role?->slug ?? strtolower($role?->name ?? 'user'),
                ];
            });

        return response()->json([
            'status' => 200,
            'contacts' => $users,
        ]);
    }

    /**
     * Liste des conversations de l'utilisateur connecté.
     */
    public function inbox()
    {
        $userId = Auth::id();

        $conversations = Conversations::with([
                'client.roles',
                'intervenant.roles',
                'messages' => function ($query) {
                    $query->with([
                            'sender',
                            'parent.sender',
                            'reactions.user',
                        ])
                        ->latest()
                        ->limit(1);
                },
            ])
            ->where(function ($query) use ($userId) {
                $query->where('client_id', $userId)
                    ->orWhere('intervenant_id', $userId);
            })
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Créer ou récupérer une conversation avec un autre utilisateur.
     *
     * Ancien système conservé :
     * - client_id
     * - intervenant_id
     *
     * Pour le moment :
     * - Client connecté => client_id = lui, intervenant_id = autre utilisateur
     * - Intervenant/Admin connecté => client_id = autre utilisateur, intervenant_id = lui
     */
    public function createConversation($otherUserId)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        $otherUser = User::findOrFail($otherUserId);

        if ((int) $user->id === (int) $otherUser->id) {
            return response()->json([
                'status' => 422,
                'message' => 'Vous ne pouvez pas créer une conversation avec vous-même.',
            ], 422);
        }

        if ($this->userHasRole($user, 'client')) {
            $clientId = $user->id;
            $intervenantId = $otherUser->id;
        } else {
            $clientId = $otherUser->id;
            $intervenantId = $user->id;
        }

        $conversation = Conversations::firstOrCreate([
            'client_id' => $clientId,
            'intervenant_id' => $intervenantId,
        ]);

        $conversation->load([
            'client.roles',
            'intervenant.roles',
            'messages.sender',
            'messages.parent.sender',
            'messages.reactions.user',
        ]);

        return response()->json([
            'status' => 200,
            'conversation' => $conversation,
        ]);
    }

    /**
     * Récupérer les messages d'une conversation.
     */
    public function getMessages($conversation_id)
    {
        $conversation = Conversations::findOrFail($conversation_id);

        $this->authorizeConversation($conversation);

        $messages = Message::with([
                'sender',
                'parent.sender',
                'reactions.user',
            ])
            ->where('conversation_id', $conversation_id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 200,
            'messages' => $messages,
        ]);
    }

    /**
     * Envoyer un message texte, image, vidéo ou réponse à un message.
     *
     * Champs acceptés :
     * - message   : texte optionnel
     * - parent_id : id du message auquel on répond, optionnel
     * - media     : fichier image ou vidéo, optionnel
     *
     * Exemples :
     * - texte seul
     * - image seule
     * - vidéo seule
     * - texte + image
     * - texte + vidéo
     * - réponse avec texte/image/vidéo
     */
    public function sendMessage(Request $request, $conversation_id)
    {
        $request->validate([
            'message' => 'nullable|string|max:5000',
            'parent_id' => 'nullable|integer|exists:messages,id',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm,mkv|max:51200',
        ]);

        if (!$request->filled('message') && !$request->hasFile('media')) {
            return response()->json([
                'status' => 422,
                'message' => 'Le message, la photo ou la vidéo est obligatoire.',
            ], 422);
        }

        $conversation = Conversations::findOrFail($conversation_id);

        $this->authorizeConversation($conversation);

        /**
         * Sécurité :
         * Si parent_id est envoyé, il doit appartenir à la même conversation.
         */
        if ($request->filled('parent_id')) {
            $parentMessage = Message::where('id', $request->parent_id)
                ->where('conversation_id', $conversation_id)
                ->first();

            if (!$parentMessage) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Le message auquel vous répondez est invalide.',
                ], 422);
            }
        }

        $mediaUrl = null;
        $mediaType = null;
        $type = 'text';

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mime = $file->getMimeType();

            if (str_starts_with($mime, 'image/')) {
                $mediaType = 'image';
                $mediaUrl = $file->store('messages/images', 'public');
            } elseif (str_starts_with($mime, 'video/')) {
                $mediaType = 'video';
                $mediaUrl = $file->store('messages/videos', 'public');
            } else {
                return response()->json([
                    'status' => 422,
                    'message' => 'Type de fichier non autorisé.',
                ], 422);
            }

            $type = $request->filled('message') ? 'mixed' : $mediaType;
        }

        $message = Message::create([
            'conversation_id' => $conversation_id,
            'sender_id' => Auth::id(),
            'parent_id' => $request->parent_id,
            'message' => trim((string) $request->message),
            'type' => $type,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);

        $message->load([
            'sender',
            'parent.sender',
            'reactions.user',
        ]);

        return response()->json([
            'status' => 200,
            'message' => $message,
        ]);
    }

    /**
     * Ajouter ou modifier une réaction sur un message.
     *
     * Une seule réaction par utilisateur et par message.
     */
    public function reactToMessage(Request $request, $message_id)
    {
        $request->validate([
            'reaction' => 'required|string|in:like,dislike,love,haha,wow,sad,angry',
        ]);

        $message = Message::findOrFail($message_id);

        $conversation = Conversations::findOrFail($message->conversation_id);

        $this->authorizeConversation($conversation);

        $reaction = MessageReaction::updateOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => Auth::id(),
            ],
            [
                'reaction' => $request->reaction,
            ]
        );

        $message->load([
            'sender',
            'parent.sender',
            'reactions.user',
        ]);

        return response()->json([
            'status' => 200,
            'reaction' => $reaction,
            'message' => $message,
        ]);
    }

    /**
     * Supprimer ma réaction sur un message.
     */
    public function removeReaction($message_id)
    {
        $message = Message::findOrFail($message_id);

        $conversation = Conversations::findOrFail($message->conversation_id);

        $this->authorizeConversation($conversation);

        MessageReaction::where('message_id', $message->id)
            ->where('user_id', Auth::id())
            ->delete();

        $message->load([
            'sender',
            'parent.sender',
            'reactions.user',
        ]);

        return response()->json([
            'status' => 200,
            'message' => $message,
        ]);
    }

    /**
     * Vérifie si l'utilisateur connecté peut accéder à la conversation.
     */
    private function authorizeConversation(Conversations $conversation): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'Utilisateur non authentifié');
        }

        /**
         * Admin autorisé.
         */
        if ($this->userHasRole($user, 'admin')) {
            return;
        }

        abort_unless(
            (int) $conversation->client_id === (int) $user->id ||
            (int) $conversation->intervenant_id === (int) $user->id,
            403,
            'Non autorisé'
        );
    }

    /**
     * Helper rôle compatible avec ton système role_user.
     */
    private function userHasRole(User $user, string $role): bool
    {
        $role = strtolower($role);

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($role);
        }

        return $user->roles()
            ->where(function ($query) use ($role) {
                $query->whereRaw('LOWER(name) = ?', [$role])
                    ->orWhereRaw('LOWER(slug) = ?', [$role]);
            })
            ->exists();
    }
}

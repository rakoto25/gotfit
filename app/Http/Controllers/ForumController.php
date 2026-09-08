<?php

namespace App\Http\Controllers;

use App\Models\ForumChannel;
use App\Models\ForumComment;
use App\Models\ForumDiscussion;
use App\Models\ForumMention;
use App\Models\ForumNotification;
use App\Models\ForumReaction;
use App\Models\ForumReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ForumController extends Controller
{
    private const REACTIONS = ['like', 'love', 'helpful', 'celebrate'];

    public function channels()
    {
        return response()->json([
            'status' => 200,
            'channels' => ForumChannel::query()
                ->where('is_active', true)
                ->withCount('discussions')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function discussions(Request $request)
    {
        $data = $request->validate([
            'channel' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['recent', 'popular'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = ForumDiscussion::query()
            ->with([
                'channel:id,name,slug,description,icon,color,is_official',
                'author:id,name,photo,google_avatar_url,coach_title',
                'reactions:id,user_id,discussion_id,type',
            ])
            ->withCount(['comments', 'reactions'])
            ->whereHas('channel', fn ($channel) => $channel->where('is_active', true));

        if (! empty($data['channel'])) {
            $query->whereHas('channel', fn ($channel) => $channel->where('slug', $data['channel']));
        }

        if (! empty($data['q'])) {
            $search = trim($data['q']);
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhereHas('author', fn ($author) => $author->where('name', 'like', "%{$search}%"));
            });
        }

        $query->orderByDesc('is_pinned');
        if (($data['sort'] ?? 'recent') === 'popular') {
            $query->orderByDesc('comments_count')->orderByDesc('views_count');
        } else {
            $query->orderByDesc('last_activity_at')->orderByDesc('id');
        }

        $discussions = $query->paginate((int) ($data['per_page'] ?? 15));
        $discussions->getCollection()->transform(
            fn (ForumDiscussion $discussion) => $this->decorateDiscussion($discussion, $request->user())
        );

        return response()->json(['status' => 200, 'discussions' => $discussions]);
    }

    public function show(Request $request, ForumDiscussion $discussion)
    {
        $discussion->increment('views_count');
        $discussion->load([
            'channel:id,name,slug,description,icon,color,is_official',
            'author:id,name,photo,google_avatar_url,coach_title',
            'reactions:id,user_id,discussion_id,type',
        ])->loadCount(['comments', 'reactions']);

        $comments = ForumComment::query()
            ->where('discussion_id', $discussion->id)
            ->with([
                'author:id,name,photo,google_avatar_url,coach_title',
                'reactions:id,user_id,comment_id,type',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => 200,
            'discussion' => $this->decorateDiscussion($discussion, $request->user()),
            'comments' => $this->commentTree($comments, $request->user()),
        ]);
    }

    public function storeDiscussion(Request $request)
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:forum_channels,id'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'mention_ids' => ['nullable', 'array', 'max:20'],
            'mention_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
        $channel = ForumChannel::where('is_active', true)->findOrFail($data['channel_id']);

        if ($channel->is_official && ! $this->isAdmin($request->user())) {
            return response()->json([
                'status' => 403,
                'message' => 'Seule l’administration peut publier dans Annonces GotFit.',
            ], 403);
        }

        $discussion = DB::transaction(function () use ($request, $data) {
            $discussion = ForumDiscussion::create([
                'channel_id' => $data['channel_id'],
                'author_id' => $request->user()->id,
                'title' => trim($data['title']),
                'body' => trim($data['body']),
                'last_activity_at' => now(),
            ]);
            $this->syncMentions($request->user(), $discussion, null, $data['mention_ids'] ?? []);

            return $discussion;
        });

        $discussion->load(['channel', 'author', 'reactions'])->loadCount(['comments', 'reactions']);

        return response()->json([
            'status' => 201,
            'message' => 'Discussion créée.',
            'discussion' => $this->decorateDiscussion($discussion, $request->user()),
        ], 201);
    }

    public function updateDiscussion(Request $request, ForumDiscussion $discussion)
    {
        if (! $this->canEdit($request->user(), $discussion->author_id)) {
            return response()->json(['status' => 403, 'message' => 'Modification non autorisée.'], 403);
        }

        if ($discussion->is_locked && ! $this->isAdmin($request->user())) {
            return response()->json(['status' => 423, 'message' => 'Cette discussion est verrouillée.'], 423);
        }

        $data = $request->validate([
            'channel_id' => ['sometimes', 'integer', 'exists:forum_channels,id'],
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'body' => ['sometimes', 'required', 'string', 'max:10000'],
            'mention_ids' => ['nullable', 'array', 'max:20'],
            'mention_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $channelId = (int) ($data['channel_id'] ?? $discussion->channel_id);
        $channel = ForumChannel::where('is_active', true)->findOrFail($channelId);
        if ($channel->is_official && ! $this->isAdmin($request->user())) {
            return response()->json(['status' => 403, 'message' => 'Canal officiel réservé à l’administration.'], 403);
        }

        DB::transaction(function () use ($request, $discussion, $data, $channelId) {
            $discussion->update([
                'channel_id' => $channelId,
                'title' => array_key_exists('title', $data) ? trim($data['title']) : $discussion->title,
                'body' => array_key_exists('body', $data) ? trim($data['body']) : $discussion->body,
            ]);

            if (array_key_exists('mention_ids', $data)) {
                $discussion->mentions()->delete();
                $this->syncMentions($request->user(), $discussion, null, $data['mention_ids'] ?? []);
            }
        });

        $discussion->load(['channel', 'author', 'reactions'])->loadCount(['comments', 'reactions']);

        return response()->json([
            'status' => 200,
            'message' => 'Discussion mise à jour.',
            'discussion' => $this->decorateDiscussion($discussion, $request->user()),
        ]);
    }

    public function destroyDiscussion(Request $request, ForumDiscussion $discussion)
    {
        if (! $this->canEdit($request->user(), $discussion->author_id)) {
            return response()->json(['status' => 403, 'message' => 'Suppression non autorisée.'], 403);
        }

        $discussion->delete();

        return response()->json(['status' => 200, 'message' => 'Discussion supprimée.']);
    }

    public function storeComment(Request $request, ForumDiscussion $discussion)
    {
        if ($discussion->is_locked && ! $this->isAdmin($request->user())) {
            return response()->json(['status' => 423, 'message' => 'Cette discussion est verrouillée.'], 423);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:forum_comments,id'],
            'mention_ids' => ['nullable', 'array', 'max:20'],
            'mention_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = ForumComment::where('discussion_id', $discussion->id)->find($data['parent_id']);
            if (! $parent) {
                return response()->json(['status' => 422, 'message' => 'La réponse parente ne fait pas partie de cette discussion.'], 422);
            }
        }

        $comment = DB::transaction(function () use ($request, $discussion, $data, $parent) {
            $comment = ForumComment::create([
                'discussion_id' => $discussion->id,
                'author_id' => $request->user()->id,
                'parent_id' => $parent?->id,
                'body' => trim($data['body']),
            ]);
            $discussion->update(['last_activity_at' => now()]);

            $notified = $this->syncMentions(
                $request->user(),
                $discussion,
                $comment,
                $data['mention_ids'] ?? []
            );
            $this->notifyReplyRecipients($request->user(), $discussion, $comment, $parent, $notified);

            return $comment;
        });

        $comment->load(['author', 'reactions']);

        return response()->json([
            'status' => 201,
            'message' => 'Réponse publiée.',
            'comment' => $this->decorateComment($comment, $request->user()),
        ], 201);
    }

    public function updateComment(Request $request, ForumComment $comment)
    {
        $comment->loadMissing('discussion');
        if (! $this->canEdit($request->user(), $comment->author_id)) {
            return response()->json(['status' => 403, 'message' => 'Modification non autorisée.'], 403);
        }
        if ($comment->discussion->is_locked && ! $this->isAdmin($request->user())) {
            return response()->json(['status' => 423, 'message' => 'Cette discussion est verrouillée.'], 423);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'mention_ids' => ['nullable', 'array', 'max:20'],
            'mention_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($request, $comment, $data) {
            $comment->update(['body' => trim($data['body'])]);
            if (array_key_exists('mention_ids', $data)) {
                $comment->mentions()->delete();
                $this->syncMentions($request->user(), $comment->discussion, $comment, $data['mention_ids'] ?? []);
            }
        });

        $comment->load(['author', 'reactions']);

        return response()->json([
            'status' => 200,
            'message' => 'Réponse mise à jour.',
            'comment' => $this->decorateComment($comment, $request->user()),
        ]);
    }

    public function destroyComment(Request $request, ForumComment $comment)
    {
        if (! $this->canEdit($request->user(), $comment->author_id)) {
            return response()->json(['status' => 403, 'message' => 'Suppression non autorisée.'], 403);
        }

        $comment->delete();

        return response()->json(['status' => 200, 'message' => 'Réponse supprimée.']);
    }

    public function reactToDiscussion(Request $request, ForumDiscussion $discussion)
    {
        $result = $this->toggleReaction($request, $discussion, 'discussion_id');
        $discussion->load('reactions')->loadCount(['comments', 'reactions']);

        return response()->json($result + [
            'discussion' => $this->decorateDiscussion($discussion, $request->user()),
        ]);
    }

    public function reactToComment(Request $request, ForumComment $comment)
    {
        $result = $this->toggleReaction($request, $comment, 'comment_id');
        $comment->load(['author', 'reactions']);

        return response()->json($result + [
            'comment' => $this->decorateComment($comment, $request->user()),
        ]);
    }

    public function coaches(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $query = User::query()
            ->where('account_status', 'approved')
            ->whereHas('roles', fn ($role) => $role->where('slug', 'intervenant'))
            ->select('id', 'name', 'photo', 'google_avatar_url', 'coach_title')
            ->orderBy('name');

        if (! empty($data['q'])) {
            $query->where('name', 'like', '%'.trim($data['q']).'%');
        }

        return response()->json(['status' => 200, 'coaches' => $query->limit(12)->get()]);
    }

    public function notifications(Request $request)
    {
        $notifications = ForumNotification::query()
            ->where('user_id', $request->user()->id)
            ->with('actor:id,name,photo,google_avatar_url,coach_title')
            ->latest()
            ->paginate(30);

        return response()->json([
            'status' => 200,
            'notifications' => $notifications,
            'unread_count' => ForumNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'status' => 200,
            'unread_count' => ForumNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
        ]);
    }

    public function readNotification(Request $request, ForumNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?: now()]);

        return response()->json(['status' => 200, 'notification' => $notification]);
    }

    public function readAllNotifications(Request $request)
    {
        ForumNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['status' => 200, 'message' => 'Notifications marquées comme lues.']);
    }

    public function reportDiscussion(Request $request, ForumDiscussion $discussion)
    {
        return $this->createReport($request, ['discussion_id' => $discussion->id]);
    }

    public function reportComment(Request $request, ForumComment $comment)
    {
        return $this->createReport($request, ['comment_id' => $comment->id]);
    }

    private function toggleReaction(Request $request, Model $target, string $foreignKey): array
    {
        $data = $request->validate(['type' => ['required', Rule::in(self::REACTIONS)]]);
        $query = ForumReaction::where('user_id', $request->user()->id)->where($foreignKey, $target->getKey());
        $existing = $query->first();

        if ($existing && $existing->type === $data['type']) {
            $query->delete();

            return ['status' => 200, 'message' => 'Réaction retirée.', 'active' => false];
        }

        $query->delete();
        ForumReaction::create([
            'user_id' => $request->user()->id,
            $foreignKey => $target->getKey(),
            'type' => $data['type'],
        ]);

        return ['status' => 200, 'message' => 'Réaction enregistrée.', 'active' => true];
    }

    private function createReport(Request $request, array $target)
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(['spam', 'harassment', 'misinformation', 'inappropriate', 'other'])],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $attributes = ['reporter_id' => $request->user()->id, 'status' => 'pending'] + $target;
        $report = ForumReport::firstOrCreate($attributes, [
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        return response()->json([
            'status' => $report->wasRecentlyCreated ? 201 : 200,
            'message' => $report->wasRecentlyCreated ? 'Signalement transmis à la modération.' : 'Ce contenu a déjà été signalé.',
            'report' => $report,
        ], $report->wasRecentlyCreated ? 201 : 200);
    }

    private function syncMentions(User $actor, ForumDiscussion $discussion, ?ForumComment $comment, array $mentionIds): array
    {
        $ids = User::query()
            ->whereIn('id', array_unique(array_map('intval', $mentionIds)))
            ->where('account_status', 'approved')
            ->whereHas('roles', fn ($role) => $role->where('slug', 'intervenant'))
            ->pluck('id')
            ->reject(fn ($id) => (int) $id === (int) $actor->id)
            ->values()
            ->all();

        foreach ($ids as $id) {
            ForumMention::create([
                'author_id' => $actor->id,
                'mentioned_user_id' => $id,
                'discussion_id' => $discussion->id,
                'comment_id' => $comment?->id,
            ]);
            $this->notify(
                $id,
                $actor,
                'mention',
                $actor->name.' vous a mentionné',
                $comment?->body ?? $discussion->body,
                $discussion
            );
        }

        return array_map('intval', $ids);
    }

    private function notifyReplyRecipients(User $actor, ForumDiscussion $discussion, ForumComment $comment, ?ForumComment $parent, array $alreadyNotified): void
    {
        $recipients = array_unique(array_filter([
            (int) $discussion->author_id,
            $parent ? (int) $parent->author_id : null,
        ]));

        foreach ($recipients as $recipientId) {
            if ($recipientId === (int) $actor->id || in_array($recipientId, $alreadyNotified, true)) {
                continue;
            }
            $this->notify(
                $recipientId,
                $actor,
                $parent ? 'reply' : 'comment',
                $parent ? $actor->name.' a répondu à votre message' : $actor->name.' a répondu à votre discussion',
                $comment->body,
                $discussion
            );
        }
    }

    private function notify(int $userId, User $actor, string $type, string $title, string $body, ForumDiscussion $discussion): void
    {
        ForumNotification::create([
            'user_id' => $userId,
            'actor_id' => $actor->id,
            'type' => $type,
            'title' => $title,
            'body' => mb_substr(trim($body), 0, 500),
            'url' => '/forum-coachs/'.$discussion->id,
            'data' => ['discussion_id' => $discussion->id],
        ]);
    }

    private function decorateDiscussion(ForumDiscussion $discussion, User $viewer): ForumDiscussion
    {
        $reactions = $discussion->relationLoaded('reactions') ? $discussion->reactions : collect();
        $discussion->setAttribute('reaction_counts', $reactions->groupBy('type')->map->count());
        $discussion->setAttribute('viewer_reaction', $reactions->firstWhere('user_id', $viewer->id)?->type);
        $discussion->setAttribute('can_edit', $this->canEdit($viewer, $discussion->author_id));
        $discussion->setAttribute('can_moderate', $this->isAdmin($viewer));
        $discussion->unsetRelation('reactions');

        return $discussion;
    }

    private function decorateComment(ForumComment $comment, User $viewer): array
    {
        $reactions = $comment->relationLoaded('reactions') ? $comment->reactions : collect();

        return [
            'id' => $comment->id,
            'discussion_id' => $comment->discussion_id,
            'author_id' => $comment->author_id,
            'parent_id' => $comment->parent_id,
            'body' => $comment->body,
            'created_at' => $comment->created_at,
            'updated_at' => $comment->updated_at,
            'author' => $comment->author,
            'reaction_counts' => $reactions->groupBy('type')->map->count(),
            'viewer_reaction' => $reactions->firstWhere('user_id', $viewer->id)?->type,
            'can_edit' => $this->canEdit($viewer, $comment->author_id),
            'children' => [],
        ];
    }

    private function commentTree($comments, User $viewer): array
    {
        $nodes = [];
        foreach ($comments as $comment) {
            $nodes[$comment->id] = $this->decorateComment($comment, $viewer);
        }

        $tree = [];
        foreach (array_keys($nodes) as $id) {
            $parentId = $nodes[$id]['parent_id'];
            if ($parentId && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$nodes[$id];
            } else {
                $tree[] = &$nodes[$id];
            }
        }

        return array_values($tree);
    }

    private function canEdit(User $user, int $authorId): bool
    {
        return $this->isAdmin($user) || (int) $user->id === (int) $authorId;
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }
}

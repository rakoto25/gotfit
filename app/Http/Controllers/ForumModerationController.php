<?php

namespace App\Http\Controllers;

use App\Models\ForumChannel;
use App\Models\ForumDiscussion;
use App\Models\ForumReport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ForumModerationController extends Controller
{
    public function reports(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'resolved', 'dismissed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $reports = ForumReport::query()
            ->with([
                'reporter:id,name,email',
                'reviewer:id,name,email',
                'discussion:id,title,author_id',
                'discussion.author:id,name',
                'comment:id,discussion_id,author_id,body',
                'comment.author:id,name',
            ])
            ->when(! empty($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 30));

        return response()->json(['status' => 200, 'reports' => $reports]);
    }

    public function updateReport(Request $request, ForumReport $report)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'resolved', 'dismissed'])],
            'moderator_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->update([
            'status' => $data['status'],
            'moderator_note' => $data['moderator_note'] ?? null,
            'reviewed_by' => $data['status'] === 'pending' ? null : $request->user()->id,
            'reviewed_at' => $data['status'] === 'pending' ? null : now(),
        ]);

        return response()->json(['status' => 200, 'message' => 'Signalement mis à jour.', 'report' => $report->fresh()]);
    }

    public function storeChannel(Request $request)
    {
        $data = $this->validateChannel($request);
        $channel = ForumChannel::create($data + ['created_by' => $request->user()->id]);

        return response()->json(['status' => 201, 'message' => 'Canal créé.', 'channel' => $channel], 201);
    }

    public function updateChannel(Request $request, ForumChannel $channel)
    {
        $data = $this->validateChannel($request, $channel);
        $channel->update($data);

        return response()->json(['status' => 200, 'message' => 'Canal mis à jour.', 'channel' => $channel->fresh()]);
    }

    public function destroyChannel(ForumChannel $channel)
    {
        if ($channel->is_official) {
            return response()->json(['status' => 422, 'message' => 'Le canal officiel ne peut pas être supprimé.'], 422);
        }
        if ($channel->discussions()->exists()) {
            return response()->json(['status' => 422, 'message' => 'Déplacez ou supprimez les discussions avant de retirer ce canal.'], 422);
        }

        $channel->delete();

        return response()->json(['status' => 200, 'message' => 'Canal supprimé.']);
    }

    public function moderateDiscussion(Request $request, ForumDiscussion $discussion)
    {
        $data = $request->validate([
            'is_pinned' => ['sometimes', 'boolean'],
            'is_locked' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_pinned', $data)) {
            $discussion->is_pinned = $data['is_pinned'];
            $discussion->pinned_by = $data['is_pinned'] ? $request->user()->id : null;
            $discussion->pinned_at = $data['is_pinned'] ? now() : null;
        }
        if (array_key_exists('is_locked', $data)) {
            $discussion->is_locked = $data['is_locked'];
            $discussion->locked_by = $data['is_locked'] ? $request->user()->id : null;
            $discussion->locked_at = $data['is_locked'] ? now() : null;
        }
        $discussion->save();

        return response()->json(['status' => 200, 'message' => 'Modération appliquée.', 'discussion' => $discussion->fresh()]);
    }

    private function validateChannel(Request $request, ?ForumChannel $channel = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('forum_channels', 'slug')->ignore($channel?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_official' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $slug = Str::slug($data['slug'] ?? $data['name']);
        $slugAlreadyExists = ForumChannel::query()
            ->where('slug', $slug)
            ->when($channel, fn ($query) => $query->whereKeyNot($channel->id))
            ->exists();

        if ($slug === '' || $slugAlreadyExists) {
            throw ValidationException::withMessages([
                'slug' => [$slug === '' ? 'Le nom doit permettre de créer une adresse valide.' : 'Ce nom de canal est déjà utilisé.'],
            ]);
        }

        return [
            'name' => trim($data['name']),
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? $channel?->icon ?? 'messages-square',
            'color' => $data['color'] ?? $channel?->color ?? '#ea580c',
            'is_official' => $data['is_official'] ?? $channel?->is_official ?? false,
            'is_active' => $data['is_active'] ?? $channel?->is_active ?? true,
            'sort_order' => $data['sort_order'] ?? $channel?->sort_order ?? 100,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\CoachForumPost;
use Illuminate\Http\Request;

class CoachForumController extends Controller
{
    public function index()
    {
        $posts = CoachForumPost::with([
            'author:id,name,photo,google_avatar_url,coach_title',
        ])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'status' => 200,
            'posts' => $posts,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
        ]);

        $post = CoachForumPost::create([
            'user_id' => $request->user()->id,
            'content' => trim($data['content']),
        ])->load('author:id,name,photo,google_avatar_url,coach_title');

        return response()->json([
            'status' => 201,
            'message' => 'Message publié dans le forum des coachs.',
            'post' => $post,
        ], 201);
    }

    public function destroy(Request $request, CoachForumPost $post)
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            return response()->json([
                'status' => 403,
                'message' => 'Vous ne pouvez supprimer que vos propres messages.',
            ], 403);
        }

        $post->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Message supprimé.',
        ]);
    }
}

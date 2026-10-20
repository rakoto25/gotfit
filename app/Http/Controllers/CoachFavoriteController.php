<?php

namespace App\Http\Controllers;

use App\Models\CoachFavorite;
use App\Models\User;
use Illuminate\Http\Request;

class CoachFavoriteController extends Controller
{
    private const COACH_PUBLIC_COLUMNS = [
        'id',
        'name',
        'display_name',
        'photo',
        'cover_photo',
        'presentation_video',
        'presentation_video_duration_seconds',
        'bio',
        'address',
        'coach_title',
        'coach_short_description',
        'coach_speciality',
        'coach_experience_years',
        'coach_certifications',
        'coach_languages',
        'account_status',
    ];

    public function index(Request $request)
    {
        if (! $request->user()->hasRole('client')) {
            return response()->json(['status' => 403, 'message' => 'Accès réservé aux clients'], 403);
        }

        $favorites = CoachFavorite::query()
            ->with(['coach' => fn ($query) => $query->select(self::COACH_PUBLIC_COLUMNS)])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'favorites' => $favorites,
        ]);
    }

    public function store(Request $request, $coachId)
    {
        if (! $request->user()->hasRole('client')) {
            return response()->json(['status' => 403, 'message' => 'Accès réservé aux clients'], 403);
        }

        $coach = User::query()
            ->whereKey($coachId)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'intervenant'))
            ->firstOrFail();

        $favorite = CoachFavorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'coach_id' => $coach->id,
        ]);

        $favorite->load(['coach' => fn ($query) => $query->select(self::COACH_PUBLIC_COLUMNS)]);

        return response()->json([
            'status' => 200,
            'message' => 'Coach ajouté aux favoris',
            'favorite' => $favorite,
        ]);
    }

    public function destroy(Request $request, $coachId)
    {
        if (! $request->user()->hasRole('client')) {
            return response()->json(['status' => 403, 'message' => 'Accès réservé aux clients'], 403);
        }

        $favorite = CoachFavorite::query()
            ->where('user_id', $request->user()->id)
            ->where('coach_id', $coachId)
            ->firstOrFail();

        $favorite->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Coach retiré des favoris',
        ]);
    }
}

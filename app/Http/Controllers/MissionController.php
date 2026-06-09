<?php

namespace App\Http\Controllers;

use App\Models\Candidature;
use App\Models\Mission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MissionController extends Controller
{
    public function index()
    {
        $missions = Mission::with('structure:id,name,email')
            ->where('status', 'published')
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'missions' => $missions]);
    }

    public function myMissions()
    {
        $missions = Mission::with('candidatures.intervenant:id,name,email')
            ->where('structure_id', Auth::id())
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'missions' => $missions]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:100',
            'budget' => 'nullable|numeric|min:0',
            'mission_date' => 'nullable|date',
            'mission_time' => 'nullable',
            'location' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
        ]);

        $mission = Mission::create([
            'structure_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category,
            'budget' => $request->budget ?? 0,
            'mission_date' => $request->mission_date,
            'mission_time' => $request->mission_time,
            'location' => $request->location,
            'city' => $request->city,
            'address' => $request->address,
            'status' => 'published',
        ]);

        return response()->json(['status' => 200, 'message' => 'Mission publiée', 'mission' => $mission]);
    }

    public function apply(Request $request, $missionId)
    {
        $request->validate([
            'message' => 'nullable|string|max:2000',
            'proposed_price' => 'nullable|numeric|min:0',
        ]);

        $mission = Mission::where('status', 'published')->findOrFail($missionId);

        $candidature = Candidature::updateOrCreate(
            ['mission_id' => $mission->id, 'intervenant_id' => Auth::id()],
            [
                'message' => $request->message,
                'proposed_price' => $request->proposed_price,
                'status' => 'pending',
            ]
        );

        return response()->json(['status' => 200, 'message' => 'Candidature envoyée', 'candidature' => $candidature]);
    }

    public function candidatures($missionId)
    {
        $mission = Mission::where('structure_id', Auth::id())->findOrFail($missionId);
        $candidatures = $mission->candidatures()->with('intervenant:id,name,email,photo,bio')->latest()->get();

        return response()->json(['status' => 200, 'candidatures' => $candidatures]);
    }

    public function acceptCandidature($id)
    {
        $candidature = Candidature::with('mission')->findOrFail($id);

        if ((int) $candidature->mission->structure_id !== (int) Auth::id()) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $candidature->update(['status' => 'accepted']);
        $candidature->mission->update(['status' => 'assigned']);
        Candidature::where('mission_id', $candidature->mission_id)
            ->where('id', '!=', $candidature->id)
            ->update(['status' => 'rejected']);

        return response()->json(['status' => 200, 'message' => 'Candidature acceptée', 'candidature' => $candidature]);
    }
}

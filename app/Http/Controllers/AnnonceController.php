<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use App\Models\Message;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnonceController extends Controller
{
    public function getAllAnnonce()
    {
        $annonce = Annonce::latest()->get();

        return response()->json([
            'status' => 200,
            'annonces' => $annonce,
        ]);
    }

    public function detailAnnonce($id)
    {
        $annonce = Annonce::findOrFail($id);

        return response()->json([
            'status' => 200,
            'annonce' => $annonce
        ]);
    }

    public function store(Request $request)
    {
        $user_id = Auth::id();

        $request->validate([
            'titre' => 'required|string',
            'contenu' => 'required|string',
        ]);

        $annonce = Annonce::create([
            'titre' => $request->titre,
            'contenu' => $request->contenu,
            'user_id' => $user_id,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Annonce créée avec succès',
            'annonce' => $annonce
        ]);
    }

    public function update(Request $request, $id)
    {
        $user_id = Auth::id();

        $annonce = Annonce::findOrFail($id);

        if ($annonce->user_id !== $user_id) {
            return response()->json([
                'status' => 403,
                'message' => 'Non autorisé'
            ], 403);
        }

        $request->validate([
            'titre' => 'required|string',
            'contenu' => 'required|string',
        ]);

        $annonce->update([
            'titre' => $request->titre,
            'contenu' => $request->contenu,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Annonce modifiée avec succès',
            'annonce' => $annonce
        ]);
    }

    public function validerAnnonce(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:valide,refuse'
        ]);

        $annonce = Annonce::findOrFail($id);

        $annonce->update([
            'status' => $request->status
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Statut de l\'annonce mis à jour avec succès',
            'annonce' => $annonce
        ]);
    }

    public function refuserAnnonce($id)
    {
        $annonce = Annonce::findOrFail($id);

        $annonce->update([
            'status' => 'refuse'
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Annonce refusée avec succès',
            'annonce' => $annonce
        ]);
    }

    public function destroy($id)
    {
        $user_id = Auth::id();

        $annonce = Annonce::findOrFail($id);

        if ($annonce->user_id !== $user_id) {
            return response()->json([
                'status' => 403,
                'message' => 'Non autorisé'
            ], 403);
        }

        $annonce->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Annonce supprimée avec succès'
        ]);
    }

    public function reserver(Request $request, $id)
    {
        $user_id = Auth::id();
        $annonce = Annonce::findOrFail($id);

        // Vérifier si déjà réservée par cet utilisateur
        $existing = Reservation::where('client_id', $user_id)
            ->where('intervenant_id', $annonce->user_id)
            ->where('reservation_date', $request->reservation_date)
            ->where('reservation_time', $request->reservation_time)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 400,
                'message' => 'Vous avez déjà une réservation à cette heure'
            ], 400);
        }

        // Créer réservation
        $reservation = Reservation::create([
            'client_id' => $user_id,
            'intervenant_id' => $annonce->user_id,
            'reservation_date' => $request->reservation_date,
            'reservation_time' => $request->reservation_time,
            'guests' => $request->guests ?? 1,
            'note' => $request->note ?? null,
            'status' => 'attente',
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Réservation créée avec succès',
            'reservation' => $reservation
        ]);
    }
}

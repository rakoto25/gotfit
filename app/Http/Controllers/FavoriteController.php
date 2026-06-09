<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use App\Models\Favorite;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * Liste des favoris de l'utilisateur connecté.
     * GET /api/favorites
     */
    public function index()
    {
        $user_id = Auth::id();

        $favorites = Favorite::with('annonce')
            ->where('user_id', $user_id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'favorites' => $favorites,
        ]);
    }

    /**
     * Ajouter une annonce en favori.
     * POST /api/favorites/{annonce}
     */
    public function store($annonce)
    {
        $user_id = Auth::id();

        $annonceModel = Annonce::findOrFail($annonce);

        $favorite = Favorite::firstOrCreate([
            'user_id' => $user_id,
            'annonce_id' => $annonceModel->id,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Annonce ajoutée aux favoris',
            'favorite' => $favorite,
        ]);
    }

    /**
     * Supprimer un favori par ID du favori.
     * DELETE /api/favorites/{id}
     */
    public function destroy($id)
    {
        $user_id = Auth::id();

        $favorite = Favorite::where('user_id', $user_id)
            ->where('id', $id)
            ->firstOrFail();

        $favorite->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Favori supprimé avec succès',
        ]);
    }

    /**
     * Supprimer un favori par ID de l'annonce.
     * DELETE /api/favorites/annonce/{annonce}
     */
    public function destroyByAnnonce($annonce)
    {
        $user_id = Auth::id();

        $favorite = Favorite::where('user_id', $user_id)
            ->where('annonce_id', $annonce)
            ->firstOrFail();

        $favorite->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Annonce retirée des favoris',
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request, $reservationId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $reservation = Reservation::findOrFail($reservationId);

        if ((int) $reservation->client_id !== (int) Auth::id()) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if ($reservation->status !== 'realise' || !$reservation->is_paid) {
            return response()->json([
                'status' => 400,
                'message' => 'Vous pouvez laisser un avis uniquement après une réservation payée et réalisée.',
            ], 400);
        }

        $review = Review::updateOrCreate(
            ['reservation_id' => $reservation->id, 'client_id' => Auth::id()],
            [
                'intervenant_id' => $reservation->intervenant_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'status' => 'approved',
            ]
        );

        return response()->json(['status' => 200, 'message' => 'Avis enregistré', 'review' => $review]);
    }

    public function byIntervenant($intervenantId)
    {
        $reviews = Review::with('client:id,name,photo')
            ->where('intervenant_id', $intervenantId)
            ->where('status', 'approved')
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'reviews' => $reviews]);
    }

    public function moderate(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,pending',
            'rejection_reason' => 'nullable|string',
        ]);

        $review = Review::findOrFail($id);
        $review->update([
            'status' => $request->status,
            'moderated_by' => Auth::id(),
            'moderated_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json(['status' => 200, 'message' => 'Avis modéré', 'review' => $review]);
    }
}

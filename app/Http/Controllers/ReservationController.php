<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    public function getAllReservation()
    {
        $reservation = Reservation::latest()->get();

        return response()->json([
            'status' => 200,
            'reservation' => $reservation,
        ]);
    }

    public function getReservationByIntervenant()
    {
        $intervenant_id = Auth::id();

        $reservations = Reservation::where('intervenant_id', $intervenant_id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'reservations' => $reservations,
        ]);
    }

    public function getReservationByClient()
    {
        $client_id = Auth::id();

        $reservations = Reservation::where('client_id', $client_id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'reservations' => $reservations,
        ]);
    }

    public function validerReservation($id)
    {
        $reservation = Reservation::findOrFail($id);

        // option sécurité : seul l’intervenant peut valider
        if ($reservation->intervenant_id !== Auth::id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Non autorisé'
            ], 403);
        }

        $reservation->update([
            'status' => 'confirme'
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Réservation confirmée avec succès',
            'reservation' => $reservation
        ]);
    }

    public function refuserReservation($id)
    {
        $reservation = Reservation::findOrFail($id);

        if ($reservation->intervenant_id !== Auth::id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Non autorisé'
            ], 403);
        }

        $reservation->update([
            'status' => 'refuse'
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Réservation refusée',
            'reservation' => $reservation
        ]);
    }

    public function terminerReservation($id)
    {
        $reservation = Reservation::findOrFail($id);

        if ($reservation->intervenant_id !== Auth::id()) {
            return response()->json([
                'status' => 403,
                'message' => 'Non autorisé'
            ], 403);
        }

        $reservation->update([
            'status' => 'realise'
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Réservation marquée comme réalisée',
            'reservation' => $reservation
        ]);
    }

    public function filterByStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:attente,confirme,refuse,realise'
        ]);

        $reservations = Reservation::where('status', $request->status)
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'reservations' => $reservations,
        ]);
    }

    public function getReservationWithPayment($id)
    {
        $reservation = Reservation::with('payement')->findOrFail($id);

        return response()->json([
            'status' => 200,
            'reservation' => $reservation,
            'is_paid' => $reservation->is_paid,
        ]);
    }

    
}

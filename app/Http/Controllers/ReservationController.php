<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    public function getAllReservation()
    {
        $reservations = Reservation::with(['client', 'intervenant', 'annonce', 'payement'])
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'reservations' => $reservations]);
    }

    public function getReservationByIntervenant()
    {
        $reservations = Reservation::with(['client', 'intervenant', 'annonce', 'payement'])
            ->where('intervenant_id', Auth::id())
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'reservations' => $reservations]);
    }

    public function getReservationByClient()
    {
        $reservations = Reservation::with(['client', 'intervenant', 'annonce', 'payement', 'review'])
            ->where('client_id', Auth::id())
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'reservations' => $reservations]);
    }

    public function show($id)
    {
        $reservation = Reservation::with(['client', 'intervenant', 'annonce', 'payement', 'review'])->findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('admin') && (int) $reservation->client_id !== (int) $user->id && (int) $reservation->intervenant_id !== (int) $user->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        return response()->json(['status' => 200, 'reservation' => $reservation]);
    }

    public function validerReservation($id)
    {
        return $this->updateStatus($id, 'confirme', 'Réservation confirmée avec succès');
    }

    public function refuserReservation($id)
    {
        return $this->updateStatus($id, 'refuse', 'Réservation refusée');
    }

    public function terminerReservation($id)
    {
        $reservation = Reservation::findOrFail($id);

        if (!$reservation->is_paid) {
            return response()->json([
                'status' => 400,
                'message' => 'Impossible de terminer une réservation non payée.',
            ], 400);
        }

        return $this->updateStatus($id, 'realise', 'Réservation marquée comme réalisée');
    }

    public function filterByStatus(Request $request)
    {
        $request->validate(['status' => 'required|in:attente,confirme,refuse,realise']);

        $reservations = Reservation::with(['client', 'intervenant', 'annonce', 'payement'])
            ->where('intervenant_id', Auth::id())
            ->where('status', $request->status)
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'reservations' => $reservations]);
    }

    private function updateStatus($id, string $status, string $message)
    {
        $reservation = Reservation::with(['client', 'intervenant', 'annonce'])->findOrFail($id);

        if ((int) $reservation->intervenant_id !== (int) Auth::id()) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $reservation->update(['status' => $status]);
        $reservation->load(['client', 'intervenant', 'annonce', 'payement']);

        return response()->json(['status' => 200, 'message' => $message, 'reservation' => $reservation]);
    }
}

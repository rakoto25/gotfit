<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use App\Models\BusinessSetting;
use App\Models\Reservation;
use App\Notifications\ReservationStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnnonceController extends Controller
{
    public function index()
    {
        try {
            $annonces = Annonce::with('user:id,name,photo,bio,account_status')
                ->where('status', 'valide')
                ->orderByDesc('is_boosted')
                ->latest()
                ->get();

            return response()->json(['status' => 200, 'annonces' => $annonces]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Erreur serveur lors du chargement des annonces',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllAnnonce()
    {
        try {
            $annonces = Annonce::with('user:id,name,email,account_status')->latest()->get();
            return response()->json(['status' => 200, 'annonces' => $annonces]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Erreur serveur lors du chargement des annonces',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function detailAnnonce($id)
    {
        try {
            $annonce = Annonce::with([
                'user:id,name,photo,bio,phone,address,account_status',
                'reservations',
            ])->findOrFail($id);
            return response()->json(['status' => 200, 'annonce' => $annonce]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Annonce introuvable',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->account_status !== 'approved') {
            return response()->json([
                'status' => 403,
                'message' => 'Votre profil doit être validé par l’administration avant de publier une annonce.',
            ], 403);
        }

        $data = $this->validateAnnonce($request);
        $data['user_id'] = $user->id;
        $data['status'] = 'en_attente';

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('annonces', 'public');
        }

        $annonce = Annonce::create($data);

        return response()->json([
            'status' => 200,
            'message' => 'Annonce créée avec succès. Elle attend la validation admin.',
            'annonce' => $annonce,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user_id = Auth::id();
        $annonce = Annonce::findOrFail($id);

        if ((int) $annonce->user_id !== (int) $user_id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $data = $this->validateAnnonce($request, false);
        $data['status'] = 'en_attente';

        if ($request->hasFile('image')) {
            if ($annonce->image && Storage::disk('public')->exists($annonce->image)) {
                Storage::disk('public')->delete($annonce->image);
            }
            $data['image'] = $request->file('image')->store('annonces', 'public');
        }

        $annonce->update($data);

        return response()->json([
            'status' => 200,
            'message' => 'Annonce modifiée avec succès. Elle attend une nouvelle validation admin.',
            'annonce' => $annonce,
        ]);
    }

    public function validerAnnonce(Request $request, $id)
    {
        $request->validate(['status' => 'nullable|in:valide,refuse,en_attente,brouillon']);
        $annonce = Annonce::findOrFail($id);
        $annonce->update(['status' => $request->status ?? 'valide']);

        return response()->json([
            'status' => 200,
            'message' => 'Statut de l’annonce mis à jour avec succès',
            'annonce' => $annonce,
        ]);
    }

    public function refuserAnnonce($id)
    {
        $annonce = Annonce::findOrFail($id);
        $annonce->update(['status' => 'refuse']);

        return response()->json([
            'status' => 200,
            'message' => 'Annonce refusée avec succès',
            'annonce' => $annonce,
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $annonce = Annonce::findOrFail($id);

        if (!$user->hasRole('admin') && (int) $annonce->user_id !== (int) $user->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if ($annonce->image && Storage::disk('public')->exists($annonce->image)) {
            Storage::disk('public')->delete($annonce->image);
        }

        $annonce->delete();

        return response()->json(['status' => 200, 'message' => 'Annonce supprimée avec succès']);
    }

    public function reserver(Request $request, $id)
    {
        $user_id = Auth::id();

        $request->validate([
            'reservation_date' => 'required|date',
            'reservation_time' => 'required',
            'guests' => 'nullable|integer|min:1',
            'note' => 'nullable|string',
        ]);

        $annonce = Annonce::findOrFail($id);

        if ($annonce->status !== 'valide') {
            return response()->json(['status' => 400, 'message' => 'Cette annonce n’est pas encore disponible à la réservation'], 400);
        }

        if ((int) $annonce->user_id === (int) $user_id) {
            return response()->json(['status' => 400, 'message' => 'Vous ne pouvez pas réserver votre propre annonce'], 400);
        }

        $existing = Reservation::where('client_id', $user_id)
            ->where('reservation_date', $request->reservation_date)
            ->where('reservation_time', $request->reservation_time)
            ->whereNotIn('status', ['refuse', 'annule'])
            ->first();

        if ($existing) {
            if (!$existing->is_paid && in_array($existing->payment_status, ['unpaid', 'pending', null], true)) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Réservation déjà créée. Vous pouvez continuer le paiement.',
                    'already_exists' => true,
                    'reservation' => $existing->load(['annonce', 'client', 'intervenant']),
                ]);
            }

            return response()->json(['status' => 400, 'message' => 'Vous avez déjà une réservation à cette heure'], 400);
        }

        $coachConflict = Reservation::where('intervenant_id', $annonce->user_id)
            ->where('reservation_date', $request->reservation_date)
            ->where('reservation_time', $request->reservation_time)
            ->whereNotIn('status', ['refuse', 'annule'])
            ->whereNotIn('payment_status', ['failed', 'refunded'])
            ->first();

        if ($coachConflict) {
            return response()->json([
                'status' => 400,
                'message' => 'Ce créneau n’est plus disponible pour ce coach.',
            ], 400);
        }

        $price = (float) $annonce->price;
        $serviceFeeRate = (float) BusinessSetting::value('client_service_fee_rate', 5);
        $commissionRate = (float) BusinessSetting::value('intervenant_commission_rate', 12);
        $serviceFeeAmount = round($price * $serviceFeeRate / 100, 2);
        $commissionAmount = round($price * $commissionRate / 100, 2);
        $intervenantAmount = round($price - $commissionAmount, 2);
        $totalClientAmount = round($price + $serviceFeeAmount, 2);

        $reservation = Reservation::create([
            'annonce_id' => $annonce->id,
            'client_id' => $user_id,
            'intervenant_id' => $annonce->user_id,
            'reservation_date' => $request->reservation_date,
            'reservation_time' => $request->reservation_time,
            'guests' => $request->guests ?? 1,
            'note' => $request->note,
            'price' => $price,
            'service_fee_rate' => $serviceFeeRate,
            'service_fee_amount' => $serviceFeeAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'intervenant_amount' => $intervenantAmount,
            'total_client_amount' => $totalClientAmount,
            'currency' => 'eur',
            'status' => 'attente',
            'is_paid' => false,
            'payment_status' => 'unpaid',
            'prestation_status' => 'pending_payment',
            'payout_status' => 'pending',
        ]);

        $reservation->load(['annonce', 'client', 'intervenant']);
        $this->notifyReservationUsers($reservation, 'created');

        return response()->json([
            'status' => 200,
            'message' => 'Réservation créée avec succès. Le client doit maintenant payer.',
            'already_exists' => false,
            'reservation' => $reservation,
        ]);
    }

    public function boost(Request $request, $id)
    {
        $request->validate(['days' => 'nullable|integer|min:1|max:365']);
        $annonce = Annonce::findOrFail($id);

        if ((int) $annonce->user_id !== (int) Auth::id()) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $annonce->update([
            'is_boosted' => true,
            'boost_until' => now()->addDays($request->days ?? 7),
        ]);

        return response()->json(['status' => 200, 'message' => 'Annonce boostée', 'annonce' => $annonce]);
    }

    private function validateAnnonce(Request $request, bool $required = true): array
    {
        $rule = $required ? 'required' : 'sometimes|required';

        return $request->validate([
            'titre' => "$rule|string|max:255",
            'contenu' => "$rule|string",
            'category' => 'nullable|string|max:100',
            'type_prestation' => 'nullable|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'duration' => 'nullable|integer|min:1',
            'is_online' => 'nullable|boolean',
            'location' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'available_days' => 'nullable|array',
            'available_hours' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);
    }

    private function notifyReservationUsers(Reservation $reservation, string $event, ?string $message = null): void
    {
        foreach ([$reservation->client, $reservation->intervenant] as $user) {
            if ($user && $user->email) {
                try {
                    $user->notify(new ReservationStatusNotification($reservation, $event, $message));
                } catch (\Throwable $e) {
                    Log::warning('Notification réservation non envoyée', [
                        'reservation_id' => $reservation->id,
                        'user_id' => $user->id,
                        'event' => $event,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}

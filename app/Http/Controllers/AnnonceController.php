<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use App\Models\BusinessSetting;
use App\Models\Reservation;
use App\Notifications\ReservationStatusNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnnonceController extends Controller
{
    public function index()
    {
        try {
            $annonces = Annonce::with('user:id,name,display_name,photo,bio,account_status')
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
            $annonces = Annonce::with('user:id,name,display_name,email,account_status')->latest()->get();

            return response()->json(['status' => 200, 'annonces' => $annonces]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Erreur serveur lors du chargement des annonces',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function myAnnouncements(Request $request)
    {
        $annonces = Annonce::with('user:id,name,display_name,photo,bio,account_status')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'annonces' => $annonces,
        ]);
    }

    public function detailAnnonce($id)
    {
        try {
            $annonce = Annonce::with([
                'user:id,name,display_name,photo,bio,phone,address,account_status',
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

        if (! $user->hasRole('client') && ! $user->hasRole('intervenant')) {
            return response()->json([
                'status' => 403,
                'message' => 'Seuls les clients et les intervenants peuvent publier une annonce.',
            ], 403);
        }

        if ($user->hasRole('intervenant') && $user->account_status !== 'approved') {
            return response()->json([
                'status' => 403,
                'message' => 'Votre profil doit être validé par l’administration avant de publier une annonce.',
            ], 403);
        }

        $data = $this->forceVisio($this->validateAnnonce($request));

        if ($user->hasRole('intervenant')) {
            $this->validateCoachOffer($request);
        }

        $data['user_id'] = $user->id;
        $data['status'] = 'en_attente';
        $data['announcement_type'] = $user->hasRole('client')
            ? 'client_request'
            : 'coach_service';

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
        $user = Auth::user();
        $user_id = $user->id;
        $annonce = Annonce::findOrFail($id);

        if ((int) $annonce->user_id !== (int) $user_id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $data = $this->forceVisio($this->validateAnnonce($request, false));

        if ($annonce->announcement_type === 'coach_service' || $user->hasRole('intervenant')) {
            $this->validateCoachOffer($request, false);
        }

        $data['status'] = 'en_attente';
        $data['announcement_type'] = $annonce->announcement_type
            ?: ($user->hasRole('client') ? 'client_request' : 'coach_service');

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

        if (! $user->hasRole('admin') && (int) $annonce->user_id !== (int) $user->id) {
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
            'reservation_date' => 'required|date|after_or_equal:today',
            'reservation_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'guests' => 'nullable|integer|min:1',
            'note' => 'nullable|string|max:1000',
        ]);

        $reservationTime = strlen($request->reservation_time) === 5
            ? $request->reservation_time.':00'
            : $request->reservation_time;

        if (Carbon::parse($request->reservation_date.' '.$reservationTime)->isPast()) {
            return response()->json([
                'status' => 422,
                'message' => 'Le créneau doit être situé dans le futur.',
            ], 422);
        }

        $annonce = Annonce::findOrFail($id);

        if ($annonce->announcement_type === 'client_request') {
            return response()->json([
                'status' => 422,
                'message' => 'Cette annonce est une recherche de coach. Contactez le client depuis la messagerie pour lui proposer votre accompagnement.',
            ], 422);
        }

        if ($annonce->status !== 'valide') {
            return response()->json(['status' => 400, 'message' => 'Cette annonce n’est pas encore disponible à la réservation'], 400);
        }

        if ((int) $annonce->user_id === (int) $user_id) {
            return response()->json(['status' => 400, 'message' => 'Vous ne pouvez pas réserver votre propre annonce'], 400);
        }

        if (! $this->matchesCoachAvailability($annonce, $request->reservation_date, $reservationTime)) {
            return response()->json([
                'status' => 422,
                'message' => 'Choisissez uniquement un créneau indiqué par le coach pour cette prestation.',
            ], 422);
        }

        $existing = Reservation::where('client_id', $user_id)
            ->where('reservation_date', $request->reservation_date)
            ->where('reservation_time', $reservationTime)
            ->whereNotIn('status', ['refuse', 'annule'])
            ->first();

        if ($existing) {
            if (! $existing->is_paid && in_array($existing->payment_status, ['unpaid', 'pending', null], true)) {
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
            ->where('reservation_time', $reservationTime)
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
            'reservation_time' => $reservationTime,
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
            'payment_status' => 'pending',
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

    private function forceVisio(array $data): array
    {
        $data['type_prestation'] = 'visio';
        $data['is_online'] = true;
        $data['location'] = 'Visio GotFit';
        $data['city'] = null;
        $data['address'] = null;
        $data['latitude'] = null;
        $data['longitude'] = null;

        return $data;
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
            'duration' => 'nullable|integer|min:15|max:480',
            'is_online' => 'nullable|boolean',
            'location' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'available_days' => 'nullable|array',
            'available_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'available_hours' => 'nullable|array',
            'available_hours.*' => 'string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);
    }

    private function validateCoachOffer(Request $request, bool $required = true): void
    {
        $presence = $required ? 'required' : 'sometimes|required';

        $request->validate([
            'price' => [$presence, 'numeric', 'min:0.01'],
            'duration' => [$presence, 'integer', 'min:15', 'max:480'],
            'available_days' => [$presence, 'array', 'min:1'],
            'available_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'available_hours' => [$presence, 'array', 'min:1'],
            'available_hours.*' => ['string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d-(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ], [
            'price.required' => 'Indiquez le prix de la prestation.',
            'available_days.required' => 'Indiquez au moins un jour disponible.',
            'available_hours.required' => 'Indiquez au moins un créneau horaire.',
        ]);
    }

    private function matchesCoachAvailability(Annonce $annonce, string $date, string $time): bool
    {
        $days = array_values(array_filter((array) $annonce->available_days));
        $hours = array_values(array_filter((array) $annonce->available_hours));

        if ($days === [] || $hours === []) {
            return false;
        }

        $day = strtolower(Carbon::parse($date)->englishDayOfWeek);

        if (! in_array($day, $days, true)) {
            return false;
        }

        $requestedStart = Carbon::createFromFormat('H:i:s', $time);
        $requestedEnd = $requestedStart->copy()->addMinutes((int) ($annonce->duration ?: 60));

        foreach ($hours as $range) {
            if (! preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', (string) $range, $matches)) {
                continue;
            }

            $rangeStart = Carbon::createFromFormat('H:i', $matches[1]);
            $rangeEnd = Carbon::createFromFormat('H:i', $matches[2]);

            if ($rangeEnd->lessThanOrEqualTo($rangeStart)) {
                continue;
            }

            if ($requestedStart->greaterThanOrEqualTo($rangeStart) && $requestedEnd->lessThanOrEqualTo($rangeEnd)) {
                $offsetMinutes = $rangeStart->diffInMinutes($requestedStart);
                $duration = max(15, (int) ($annonce->duration ?: 60));

                if ($offsetMinutes % $duration === 0) {
                    return true;
                }
            }
        }

        return false;
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

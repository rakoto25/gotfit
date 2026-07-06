<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Notifications\ReservationStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $reservations = Reservation::with(['client.clientOnboarding', 'intervenant', 'annonce', 'payement'])
            ->withCount(['notes' => function ($query) {
                $query->where(function ($q) {
                    $q->where('visibility', 'shared')
                        ->orWhere('author_id', Auth::id());
                });
            }])
            ->where('intervenant_id', Auth::id())
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'reservations' => $reservations]);
    }

    public function getReservationByClient()
    {
        $reservations = Reservation::with(['client', 'intervenant', 'annonce', 'payement', 'review'])
            ->withCount(['notes' => function ($query) {
                $query->where(function ($q) {
                    $q->where('visibility', 'shared')
                        ->orWhere('author_id', Auth::id());
                });
            }])
            ->where('client_id', Auth::id())
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'reservations' => $reservations]);
    }

    public function show($id)
    {
        $reservation = Reservation::with(['client.clientOnboarding', 'intervenant', 'annonce', 'payement', 'review'])->findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('admin') && (int) $reservation->client_id !== (int) $user->id && (int) $reservation->intervenant_id !== (int) $user->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $visibleNotes = $reservation->notes()
            ->with(['author:id,name,email', 'intervenant:id,name,email'])
            ->when($user->hasRole('client'), function ($query) {
                $query->where(function ($q) {
                    $q->where('visibility', 'shared')
                        ->orWhere('author_id', Auth::id());
                });
            })
            ->when($user->hasRole('intervenant'), function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('visibility', 'shared')
                        ->orWhere('author_id', $user->id);
                });
            })
            ->orderByDesc('is_pinned')
            ->latest()
            ->get();

        return response()->json([
            'status' => 200,
            'reservation' => $reservation,
            'notes' => $visibleNotes,
        ]);
    }

    public function planning(Request $request)
    {
        $user = $request->user();

        $query = Reservation::with(['client:id,name,email', 'intervenant:id,name,email', 'annonce', 'payement'])
            ->when($request->filled('from'), fn ($q) => $q->whereDate('reservation_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('reservation_date', '<=', $request->to))
            ->whereNotIn('status', ['refuse', 'annule'])
            ->orderBy('reservation_date')
            ->orderBy('reservation_time');

        if ($user->hasRole('admin')) {
            $reservations = $query->get();
        } elseif ($user->hasRole('intervenant')) {
            $reservations = $query->where('intervenant_id', $user->id)->get();
        } else {
            $reservations = $query->where('client_id', $user->id)->get();
        }

        return response()->json([
            'status' => 200,
            'reservations' => $reservations->map(fn (Reservation $reservation) => $this->formatPlanningEvent($reservation))->values(),
        ]);
    }

    public function calendar($id)
    {
        $reservation = Reservation::with(['client', 'intervenant', 'annonce'])->findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('admin') && (int) $reservation->client_id !== (int) $user->id && (int) $reservation->intervenant_id !== (int) $user->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $ics = $this->buildCalendarIcs($reservation);
        $filename = 'gotfit-reservation-' . $reservation->id . '.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
        $reservation = Reservation::with(['client', 'intervenant', 'annonce', 'payement'])->findOrFail($id);

        if (!$reservation->is_paid) {
            return response()->json([
                'status' => 400,
                'message' => 'Impossible de terminer une réservation non payée.',
            ], 400);
        }

        if ((int) $reservation->intervenant_id !== (int) Auth::id()) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if (!$reservation->hasSessionPassed()) {
            return response()->json([
                'status' => 400,
                'message' => 'La séance ne peut être terminée qu’après son créneau.',
            ], 400);
        }

        $validationDelay = (int) config('services.stripe.validation_delay_hours', 72);

        $reservation->update([
            'status' => 'realise',
            'prestation_status' => 'pending_validation',
            'validation_deadline' => now()->addHours($validationDelay),
        ]);

        $reservation->load(['client', 'intervenant', 'annonce', 'payement']);
        $this->notifyReservationUsers($reservation, 'pending_validation');

        return response()->json([
            'status' => 200,
            'message' => 'Réservation marquée comme réalisée. Le client peut confirmer la prestation.',
            'reservation' => $reservation,
        ]);
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

        $event = match ($status) {
            'confirme' => 'confirmed',
            'refuse' => 'refused',
            default => 'updated',
        };

        $this->notifyReservationUsers($reservation, $event);

        return response()->json(['status' => 200, 'message' => $message, 'reservation' => $reservation]);
    }

    private function formatPlanningEvent(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'title' => $reservation->calendarTitle(),
            'start' => $reservation->scheduledAt()->toIso8601String(),
            'end' => $reservation->endsAt()->toIso8601String(),
            'status' => $reservation->status,
            'payment_status' => $reservation->payment_status,
            'prestation_status' => $reservation->prestation_status,
            'client' => $reservation->client,
            'intervenant' => $reservation->intervenant,
            'annonce' => $reservation->annonce,
            'calendar_url' => url('/api/reservation/' . $reservation->id . '/calendar.ics'),
        ];
    }

    private function buildCalendarIcs(Reservation $reservation): string
    {
        $startsAt = $reservation->scheduledAt()->utc();
        $endsAt = $reservation->endsAt()->utc();
        $title = $this->escapeIcsText($reservation->calendarTitle());
        $description = $this->escapeIcsText('Réservation GotFit #' . $reservation->id);
        $location = $this->escapeIcsText($reservation->annonce?->address ?: $reservation->annonce?->location ?: 'GotFit');

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//GotFit//Reservations//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:gotfit-reservation-' . $reservation->id . '@gotfit.tech',
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $startsAt->format('Ymd\THis\Z'),
            'DTEND:' . $endsAt->format('Ymd\THis\Z'),
            'SUMMARY:' . $title,
            'DESCRIPTION:' . $description,
            'LOCATION:' . $location,
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    private function escapeIcsText(?string $text): string
    {
        $text = (string) $text;

        return str_replace(
            ["\\", "\n", "\r", ',', ';'],
            ["\\\\", "\\n", '', "\\,", "\\;"],
            $text
        );
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

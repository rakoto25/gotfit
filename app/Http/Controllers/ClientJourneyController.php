<?php

namespace App\Http\Controllers;

use App\Models\ClientNote;
use App\Models\ClientOnboarding;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;

class ClientJourneyController extends Controller
{
    public function history(Request $request, $clientId)
    {
        $client = User::with('roles')->findOrFail($clientId);

        if (!$this->canAccessClient($request->user(), $client)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $reservations = Reservation::with(['annonce', 'intervenant:id,name,email,photo', 'payement', 'review'])
            ->where('client_id', $client->id)
            ->when($request->user()->hasRole('intervenant'), function ($query) use ($request) {
                $query->where('intervenant_id', $request->user()->id);
            })
            ->latest()
            ->get();

        $notesCount = ClientNote::where('client_id', $client->id)
            ->when($request->user()->hasRole('intervenant'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('visibility', 'shared')
                        ->orWhere('author_id', $request->user()->id);
                });
            })
            ->when($request->user()->hasRole('client'), function ($query) {
                $query->where(function ($q) {
                    $q->where('visibility', 'shared')
                        ->orWhere('author_id', auth()->id());
                });
            })
            ->count();

        return response()->json([
            'status' => 200,
            'client' => $client,
            'summary' => [
                'reservations_count' => $reservations->count(),
                'completed_reservations_count' => $reservations->where('status', 'realise')->count(),
                'paid_reservations_count' => $reservations->where('is_paid', true)->count(),
                'notes_count' => $notesCount,
            ],
            'reservations' => $reservations,
        ]);
    }

    public function notes(Request $request, $clientId)
    {
        $client = User::findOrFail($clientId);

        if (!$this->canAccessClient($request->user(), $client)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $notes = ClientNote::with(['author:id,name,email', 'intervenant:id,name,email', 'reservation:id,reservation_date,reservation_time,status'])
            ->where('client_id', $client->id)
            ->when($request->filled('visibility'), function ($query) use ($request) {
                $query->where('visibility', $request->visibility);
            })
            ->when($request->filled('reservation_id'), function ($query) use ($request) {
                $query->where('reservation_id', $request->reservation_id);
            })
            ->when($request->user()->hasRole('client'), function ($query) {
                $query->where(function ($q) {
                    $q->where('visibility', 'shared')
                        ->orWhere('author_id', auth()->id());
                });
            })
            ->when($request->user()->hasRole('intervenant'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('visibility', 'shared')
                        ->orWhere('author_id', $request->user()->id);
                });
            })
            ->orderByDesc('is_pinned')
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'notes' => $notes]);
    }

    public function storeNote(Request $request, $clientId)
    {
        $client = User::findOrFail($clientId);
        $user = $request->user();

        if (!$this->canAccessClient($user, $client)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $data = $request->validate([
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'visibility' => ['nullable', 'in:private,shared'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $reservation = null;
        if (!empty($data['reservation_id'])) {
            $reservation = Reservation::findOrFail($data['reservation_id']);

            if ((int) $reservation->client_id !== (int) $client->id) {
                return response()->json(['status' => 422, 'message' => 'Cette réservation ne correspond pas à ce client.'], 422);
            }

            if ($user->hasRole('intervenant') && (int) $reservation->intervenant_id !== (int) $user->id) {
                return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
            }
        }

        $intervenantId = $reservation?->intervenant_id;
        if (!$intervenantId && $user->hasRole('intervenant')) {
            $intervenantId = $user->id;
        }

        $note = ClientNote::create([
            'client_id' => $client->id,
            'intervenant_id' => $intervenantId,
            'reservation_id' => $reservation?->id,
            'author_id' => $user->id,
            'visibility' => $data['visibility'] ?? 'private',
            'title' => $data['title'] ?? null,
            'content' => $data['content'],
            'is_pinned' => $data['is_pinned'] ?? false,
        ]);

        $note->load(['author:id,name,email', 'intervenant:id,name,email', 'reservation:id,reservation_date,reservation_time,status']);

        return response()->json([
            'status' => 201,
            'message' => 'Note enregistrée avec succès',
            'note' => $note,
        ], 201);
    }

    public function updateNote(Request $request, $noteId)
    {
        $note = ClientNote::findOrFail($noteId);
        $user = $request->user();

        if (!$user->hasRole('admin') && (int) $note->author_id !== (int) $user->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $data = $request->validate([
            'visibility' => ['nullable', 'in:private,shared'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:5000'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $note->update($data);
        $note->load(['author:id,name,email', 'intervenant:id,name,email', 'reservation:id,reservation_date,reservation_time,status']);

        return response()->json([
            'status' => 200,
            'message' => 'Note mise à jour avec succès',
            'note' => $note,
        ]);
    }

    public function deleteNote(Request $request, $noteId)
    {
        $note = ClientNote::findOrFail($noteId);
        $user = $request->user();

        if (!$user->hasRole('admin') && (int) $note->author_id !== (int) $user->id) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $note->delete();

        return response()->json(['status' => 200, 'message' => 'Note supprimée avec succès']);
    }

    public function showOnboarding(Request $request, $clientId)
    {
        $client = User::findOrFail($clientId);

        if (!$this->canAccessClient($request->user(), $client)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $onboarding = (int) $request->user()->id === (int) $client->id
            ? ClientOnboarding::firstOrCreate(['client_id' => $client->id])
            : ClientOnboarding::where('client_id', $client->id)->first();

        return response()->json(['status' => 200, 'onboarding' => $onboarding]);
    }

    public function myOnboarding(Request $request)
    {
        if (!$request->user()->hasRole('client')) {
            return response()->json(['status' => 403, 'message' => 'Accès réservé aux clients'], 403);
        }

        return $this->showOnboarding($request, $request->user()->id);
    }

    public function saveMyOnboarding(Request $request)
    {
        if (!$request->user()->hasRole('client')) {
            return response()->json(['status' => 403, 'message' => 'Accès réservé aux clients'], 403);
        }

        $data = $request->validate([
            'goals' => ['nullable', 'array'],
            'level' => ['nullable', 'string', 'max:100'],
            'training_preferences' => ['nullable', 'array'],
            'availability' => ['nullable', 'array'],
            'health_constraints' => ['nullable', 'array'],
            'measurements' => ['nullable', 'array'],
            'lifestyle' => ['nullable', 'array'],
            'emergency_contact' => ['nullable', 'array'],
            'answers' => ['nullable', 'array'],
            'is_completed' => ['nullable', 'boolean'],
        ]);

        if (($data['is_completed'] ?? false) === true) {
            $data['completed_at'] = now();
        }

        $onboarding = ClientOnboarding::updateOrCreate(
            ['client_id' => $request->user()->id],
            $data
        );

        return response()->json([
            'status' => 200,
            'message' => 'Questionnaire onboarding enregistré avec succès',
            'onboarding' => $onboarding,
        ]);
    }

    private function canAccessClient(User $user, User $client): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('client') && (int) $user->id === (int) $client->id) {
            return true;
        }

        if ($user->hasRole('intervenant')) {
            return Reservation::where('client_id', $client->id)
                ->where('intervenant_id', $user->id)
                ->exists();
        }

        return false;
    }
}

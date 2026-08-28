<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\User;
use App\Models\VisioParticipant;
use App\Models\VisioSession;
use App\Services\ReservationVisioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VisioSessionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $sessions = VisioSession::with(['coach:id,name,email,photo'])
            ->withCount([
                'clientParticipants as reserved_participants_count' => function ($query) {
                    $query->whereIn('status', ['reserved', 'paid', 'joined', 'left']);
                },
            ])
            ->when(! $user || (! $user->hasRole('admin') && ! $request->boolean('mine')), function ($query) {
                $query->whereNull('reservation_id')
                    ->whereIn('status', ['open', 'confirmed', 'live']);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('coach_id'), function ($query) use ($request) {
                $query->where('coach_id', $request->coach_id);
            })
            ->when($request->boolean('upcoming', true), function ($query) {
                $query->where('start_at', '>=', now()->subHours(2));
            })
            ->when($user && $request->boolean('mine'), function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('coach_id', $user->id)
                        ->orWhereHas('participants', function ($participantQuery) use ($user) {
                            $participantQuery->where('user_id', $user->id);
                        });
                });
            })
            ->orderBy('start_at')
            ->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'status' => 200,
            'sessions' => $sessions,
        ]);
    }

    public function show(Request $request, $id)
    {
        $session = VisioSession::with(['coach:id,name,email,photo'])
            ->withCount([
                'clientParticipants as reserved_participants_count' => function ($query) {
                    $query->whereIn('status', ['reserved', 'paid', 'joined', 'left']);
                },
            ])
            ->findOrFail($id);

        if (! $this->canViewSession($request->user(), $session)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $user = $request->user();
        if ($user && (
            $this->canManageSession($user, $session)
            || VisioParticipant::where('visio_session_id', $session->id)->where('user_id', $user->id)->exists()
        )) {
            $session->load('participants.user:id,name,email,photo');
        }

        return response()->json([
            'status' => 200,
            'session' => $session,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->hasRole('intervenant') && ! $user->hasRole('admin')) {
            return response()->json([
                'status' => 403,
                'message' => 'Accès réservé aux coachs/intervenants',
            ], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:360'],
            'min_participants' => ['nullable', 'integer', 'min:1', 'max:2'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:2'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::in(['draft', 'open'])],
            'provider' => ['nullable', 'string', 'max:50'],
            'provider_room_id' => ['nullable', 'string', 'max:255'],
            'join_url' => ['nullable', 'url', 'max:500'],
        ]);

        $minParticipants = (int) ($data['min_participants'] ?? 1);
        $maxParticipants = (int) ($data['max_participants'] ?? VisioSession::MAX_CLIENT_PARTICIPANTS_V1);

        if ($maxParticipants !== null && $maxParticipants < $minParticipants) {
            return response()->json([
                'status' => 422,
                'message' => 'Le nombre maximum de participants doit être supérieur ou égal au minimum.',
            ], 422);
        }

        $session = VisioSession::create([
            'coach_id' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_at' => $data['start_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'min_participants' => $minParticipants,
            'max_participants' => $maxParticipants,
            // Les séances créées directement dans l’espace Visio sont gratuites.
            // Les séances payantes passent par Annonce -> Réservation -> Stripe.
            'price' => 0,
            'currency' => 'EUR',
            'status' => $data['status'] ?? 'open',
            'provider' => 'livekit',
            'provider_room_id' => $data['provider_room_id'] ?? null,
            'room_name' => $this->makeRoomName($data['title']),
            'join_url' => $data['join_url'] ?? null,
        ]);

        VisioParticipant::create([
            'visio_session_id' => $session->id,
            'user_id' => $user->id,
            'role' => 'coach',
            'status' => 'paid',
            'payment_status' => 'paid',
            'amount' => 0,
            'currency' => $session->currency,
            'paid_at' => now(),
        ]);

        $session->load(['coach:id,name,email,photo', 'participants.user:id,name,email,photo']);

        return response()->json([
            'status' => 201,
            'message' => 'Séance visio créée avec succès',
            'session' => $session,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $session = VisioSession::findOrFail($id);

        if (! $this->canManageSession($request->user(), $session)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        if (in_array($session->status, ['live', 'ended', 'cancelled'], true)) {
            return response()->json([
                'status' => 422,
                'message' => 'Cette séance ne peut plus être modifiée.',
            ], 422);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:360'],
            'min_participants' => ['nullable', 'integer', 'min:1', 'max:2'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:2'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', Rule::in(['draft', 'open'])],
            'provider' => ['nullable', 'string', 'max:50'],
            'provider_room_id' => ['nullable', 'string', 'max:255'],
            'join_url' => ['nullable', 'url', 'max:500'],
        ]);

        $minParticipants = (int) ($data['min_participants'] ?? $session->min_participants);
        $maxParticipants = array_key_exists('max_participants', $data)
            ? (int) $data['max_participants']
            : $session->effective_max_participants;

        if ($maxParticipants !== null && $maxParticipants < $minParticipants) {
            return response()->json([
                'status' => 422,
                'message' => 'Le nombre maximum de participants doit être supérieur ou égal au minimum.',
            ], 422);
        }

        $activeParticipants = $session->clientParticipants()
            ->whereIn('status', ['reserved', 'paid', 'joined', 'left'])
            ->count();

        if ($maxParticipants < $activeParticipants) {
            return response()->json([
                'status' => 422,
                'message' => 'Le maximum ne peut pas être inférieur au nombre de coachés déjà inscrits.',
                'active_participants_count' => $activeParticipants,
            ], 422);
        }

        $data['min_participants'] = $minParticipants;
        $data['max_participants'] = $maxParticipants;

        // Les visios autonomes restent gratuites ; les paiements sont centralisés
        // sur le parcours marketplace qui crée une réservation Stripe traçable.
        $data['provider'] = 'livekit';

        if (! $session->reservation_id) {
            $data['price'] = 0;
            $data['currency'] = 'EUR';
        } elseif (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $session->update($data);
        $this->refreshSessionStatus($session);

        $session->load(['coach:id,name,email,photo', 'participants.user:id,name,email,photo']);

        return response()->json([
            'status' => 200,
            'message' => 'Séance visio mise à jour avec succès',
            'session' => $session,
        ]);
    }

    public function reserve(Request $request, $id)
    {
        $session = VisioSession::with('participants')->findOrFail($id);
        $user = $request->user();

        if (! $user->hasRole('client')) {
            return response()->json(['status' => 403, 'message' => 'Accès réservé aux clients'], 403);
        }

        if ($session->reservation_id) {
            $reservation = Reservation::find($session->reservation_id);

            if (! $reservation || (int) $reservation->client_id !== (int) $user->id) {
                return response()->json(['status' => 403, 'message' => 'Cette visio est privée et liée à une autre réservation.'], 403);
            }

            if (! $reservation->is_paid && $reservation->payment_status !== 'paid') {
                return response()->json(['status' => 402, 'message' => 'Le paiement de la réservation doit être confirmé.'], 402);
            }

            app(ReservationVisioService::class)->syncPaidReservation($reservation);

            return response()->json([
                'status' => 200,
                'message' => 'Participant synchronisé avec la réservation payée.',
                'participant' => VisioParticipant::where('visio_session_id', $session->id)->where('user_id', $user->id)->first(),
                'session' => $session->fresh(['coach:id,name,email,photo', 'participants.user:id,name,email,photo', 'reservation']),
            ]);
        }

        if ((int) $session->coach_id === (int) $user->id) {
            return response()->json(['status' => 422, 'message' => 'Le coach ne peut pas réserver sa propre séance.'], 422);
        }

        if (! in_array($session->status, ['open', 'confirmed'], true)) {
            return response()->json(['status' => 422, 'message' => 'Cette séance visio n’est pas ouverte aux réservations.'], 422);
        }

        if ($session->start_at->isPast()) {
            return response()->json(['status' => 422, 'message' => 'Cette séance visio est déjà passée.'], 422);
        }

        if ($session->available_places <= 0) {
            return response()->json(['status' => 422, 'message' => 'Cette séance visio est complète.'], 422);
        }

        $participant = VisioParticipant::where('visio_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant && ! in_array($participant->status, ['cancelled', 'no_show'], true)) {
            return response()->json([
                'status' => 200,
                'message' => 'Vous êtes déjà inscrit à cette séance visio.',
                'participant' => $participant,
                'session' => $session->fresh(),
            ]);
        }

        $isFree = (float) $session->price <= 0;

        $participant = DB::transaction(function () use ($session, $user, $isFree) {
            $lockedSession = VisioSession::whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingParticipant = VisioParticipant::where('visio_session_id', $lockedSession->id)
                ->where('user_id', $user->id)
                ->first();

            if (
                ! $existingParticipant
                || in_array($existingParticipant->status, ['cancelled', 'no_show'], true)
            ) {
                if ($lockedSession->available_places <= 0) {
                    throw ValidationException::withMessages([
                        'session' => ['Cette séance visio est complète (deux coachés maximum).'],
                    ]);
                }
            }

            return VisioParticipant::updateOrCreate(
                [
                    'visio_session_id' => $lockedSession->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'participant',
                    'status' => $isFree ? 'paid' : 'reserved',
                    'payment_status' => $isFree ? 'paid' : 'unpaid',
                    'amount' => $lockedSession->price,
                    'currency' => $lockedSession->currency,
                    'paid_at' => $isFree ? now() : null,
                    'cancelled_at' => null,
                ]
            );
        });

        $this->refreshSessionStatus($session);

        return response()->json([
            'status' => 201,
            'message' => $isFree
                ? 'Inscription confirmée à la séance visio'
                : 'Réservation créée. Le paiement doit être confirmé pour valider la place.',
            'participant' => $participant->load('user:id,name,email,photo'),
            'session' => $session->fresh(['coach:id,name,email,photo', 'participants.user:id,name,email,photo']),
        ], 201);
    }

    public function markParticipantPaid(Request $request, $id, $participantId)
    {
        $session = VisioSession::findOrFail($id);

        if (! $this->canManageSession($request->user(), $session)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $data = $request->validate([
            'payment_intent_id' => ['nullable', 'string', 'max:255'],
        ]);

        $participant = VisioParticipant::where('visio_session_id', $session->id)
            ->where('role', 'participant')
            ->findOrFail($participantId);

        $participant->update([
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_intent_id' => $data['payment_intent_id'] ?? $participant->payment_intent_id,
            'paid_at' => now(),
        ]);

        $this->refreshSessionStatus($session);

        return response()->json([
            'status' => 200,
            'message' => 'Paiement participant validé avec succès',
            'participant' => $participant->fresh('user:id,name,email,photo'),
            'session' => $session->fresh(['participants.user:id,name,email,photo']),
        ]);
    }

    public function start(Request $request, $id)
    {
        $session = VisioSession::findOrFail($id);

        if (! $this->canManageSession($request->user(), $session)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $this->refreshSessionStatus($session);
        $paidCount = $session->fresh()->paid_participants_count;

        if ($paidCount < $session->min_participants) {
            return response()->json([
                'status' => 422,
                'message' => 'La visio nécessite au minimum '.$session->min_participants.' participants clients payés/validés.',
                'paid_participants_count' => $paidCount,
            ], 422);
        }

        if (in_array($session->status, ['ended', 'cancelled'], true)) {
            return response()->json(['status' => 422, 'message' => 'Cette séance ne peut plus démarrer.'], 422);
        }

        $closesAt = $session->start_at->copy()->addMinutes($session->duration_minutes)->addMinutes(30);

        if (now()->gt($closesAt)) {
            return response()->json([
                'status' => 422,
                'message' => 'La période de démarrage de cette séance est terminée.',
            ], 422);
        }

        $session->update([
            'status' => 'live',
            'started_at' => $session->started_at ?? now(),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Séance visio démarrée',
            'session' => $session->fresh(['coach:id,name,email,photo', 'participants.user:id,name,email,photo']),
        ]);
    }

    public function join(Request $request, $id)
    {
        $session = VisioSession::with(['reservation.annonce', 'participants'])->findOrFail($id);
        $user = $request->user();

        if ($session->reservation) {
            app(ReservationVisioService::class)->syncPaidReservation($session->reservation);
            $session->refresh()->load(['reservation.annonce', 'participants']);
        }

        $participant = VisioParticipant::where('visio_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $participant && ! $this->canManageSession($user, $session)) {
            return response()->json(['status' => 403, 'message' => 'Vous n’êtes pas autorisé à rejoindre cette séance visio.'], 403);
        }

        if ($participant && $participant->role === 'participant' && $participant->payment_status !== 'paid') {
            return response()->json(['status' => 402, 'message' => 'Le paiement doit être validé avant de rejoindre la visio.'], 402);
        }

        if (in_array($session->status, ['ended', 'cancelled'], true)) {
            return response()->json(['status' => 422, 'message' => 'Cette séance visio n’est plus accessible.'], 422);
        }

        if ($session->status === 'draft') {
            return response()->json(['status' => 422, 'message' => 'Cette séance visio est encore en préparation.'], 422);
        }

        $closesAt = $session->start_at->copy()->addMinutes($session->duration_minutes)->addMinutes(30);

        if (now()->gt($closesAt)) {
            return response()->json(['status' => 422, 'message' => 'La période d’accès à cette séance est terminée.'], 422);
        }

        if ($session->status === 'open' && $session->paid_participants_count >= $session->min_participants) {
            $session->update(['status' => 'confirmed']);
        }

        if ($participant) {
            $participant->update([
                'status' => 'joined',
                'joined_at' => $participant->joined_at ?? now(),
            ]);
        }

        $serverUrl = trim((string) config('services.visio.server_url'));
        if ($serverUrl === '' || ! preg_match('/^wss?:\/\//i', $serverUrl)) {
            return response()->json([
                'status' => 503,
                'message' => 'La visioconférence est temporairement indisponible : VISIO_SERVER_URL LiveKit est manquante ou invalide.',
            ], 503);
        }

        $token = $this->makeVideoToken($session, $user, $participant?->role ?? 'coach');

        return response()->json([
            'status' => 200,
            'message' => 'Accès visio autorisé',
            'provider' => $session->provider,
            'server_url' => $serverUrl,
            'room_name' => $session->room_name,
            'join_url' => $session->join_url,
            'token' => $token,
            'participant_token' => $token,
            'session' => $session->fresh(['coach:id,name,email,photo', 'participants.user:id,name,email,photo', 'reservation']),
        ]);
    }

    public function leave(Request $request, $id)
    {
        $session = VisioSession::findOrFail($id);
        $participant = VisioParticipant::where('visio_session_id', $session->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $participant->update([
            'status' => 'left',
            'left_at' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Sortie de visio enregistrée',
            'participant' => $participant->fresh(),
        ]);
    }

    public function end(Request $request, $id)
    {
        $session = VisioSession::findOrFail($id);

        if (! $this->canManageSession($request->user(), $session)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $session->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        VisioParticipant::where('visio_session_id', $session->id)
            ->where('status', 'joined')
            ->update([
                'status' => 'left',
                'left_at' => now(),
            ]);

        return response()->json([
            'status' => 200,
            'message' => 'Séance visio terminée',
            'session' => $session->fresh(['participants.user:id,name,email,photo']),
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $session = VisioSession::findOrFail($id);

        if (! $this->canManageSession($request->user(), $session)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $session->update([
            'status' => 'cancelled',
            'cancellation_reason' => $data['reason'] ?? null,
        ]);

        VisioParticipant::where('visio_session_id', $session->id)
            ->where('role', 'participant')
            ->whereIn('status', ['reserved', 'paid'])
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

        return response()->json([
            'status' => 200,
            'message' => 'Séance visio annulée',
            'session' => $session->fresh(['participants.user:id,name,email,photo']),
        ]);
    }

    public function participants(Request $request, $id)
    {
        $session = VisioSession::findOrFail($id);

        if (! $this->canManageSession($request->user(), $session)) {
            return response()->json(['status' => 403, 'message' => 'Non autorisé'], 403);
        }

        return response()->json([
            'status' => 200,
            'participants' => VisioParticipant::with('user:id,name,email,photo')
                ->where('visio_session_id', $session->id)
                ->orderBy('role')
                ->latest()
                ->get(),
        ]);
    }

    private function refreshSessionStatus(VisioSession $session): void
    {
        $session->refresh();

        if (in_array($session->status, ['draft', 'live', 'ended', 'cancelled'], true)) {
            return;
        }

        $paidCount = $session->paid_participants_count;
        $nextStatus = $paidCount >= $session->min_participants ? 'confirmed' : 'open';

        if ($session->status !== $nextStatus) {
            $session->update(['status' => $nextStatus]);
        }
    }

    private function canViewSession(?User $user, VisioSession $session): bool
    {
        if ($session->reservation_id) {
            if (! $user) {
                return false;
            }

            return $this->canManageSession($user, $session)
                || VisioParticipant::where('visio_session_id', $session->id)
                    ->where('user_id', $user->id)
                    ->exists();
        }

        if (in_array($session->status, ['open', 'confirmed', 'live'], true)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $this->canManageSession($user, $session)
            || VisioParticipant::where('visio_session_id', $session->id)
                ->where('user_id', $user->id)
                ->exists();
    }

    private function canManageSession(User $user, VisioSession $session): bool
    {
        return $user->hasRole('admin') || (int) $session->coach_id === (int) $user->id;
    }

    private function makeRoomName(string $title): string
    {
        $base = Str::slug($title) ?: 'visio-gotfit';

        do {
            $roomName = $base.'-'.Str::lower(Str::random(10));
        } while (VisioSession::where('room_name', $roomName)->exists());

        return $roomName;
    }

    private function makeVideoToken(VisioSession $session, User $user, string $role): string
    {
        $now = time();
        $provider = strtolower((string) ($session->provider ?: config('services.visio.provider', 'livekit')));

        if ($provider === 'livekit') {
            $apiKey = config('services.visio.api_key');
            $apiSecret = config('services.visio.api_secret');

            if (! $apiKey || ! $apiSecret) {
                abort(500, 'Configuration LiveKit manquante : VISIO_API_KEY ou VISIO_API_SECRET.');
            }

            $payload = [
                'iss' => $apiKey,
                'sub' => 'gotfit-user-'.$user->id.'-session-'.$session->id,
                'name' => $user->name,
                'iat' => $now,
                'nbf' => $now - 10,
                'exp' => $now + (int) config('services.visio.token_ttl', 3600),
                'video' => [
                    'room' => $session->room_name,
                    'roomJoin' => true,
                    'canPublish' => true,
                    'canSubscribe' => true,
                    'roomAdmin' => $role === 'coach',
                ],
                'metadata' => json_encode([
                    'user_id' => $user->id,
                    'session_id' => $session->id,
                    'role' => $role,
                ]),
            ];

            return $this->encodeJwt($payload, $apiSecret);
        }

        $secret = config('services.visio.secret') ?: config('app.key');

        if (Str::startsWith($secret, 'base64:')) {
            $secret = base64_decode(Str::after($secret, 'base64:'), true) ?: $secret;
        }

        $payload = [
            'iss' => config('app.name', 'GotFit'),
            'sub' => 'gotfit-user-'.$user->id.'-session-'.$session->id,
            'name' => $user->name,
            'role' => $role,
            'room' => $session->room_name,
            'session_id' => $session->id,
            'iat' => $now,
            'nbf' => $now - 10,
            'exp' => $now + (int) config('services.visio.token_ttl', 3600),
            'permissions' => [
                'can_join' => true,
                'can_publish' => true,
                'can_subscribe' => true,
                'can_admin' => $role === 'coach',
            ],
        ];

        return $this->encodeJwt($payload, $secret);
    }

    private function encodeJwt(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Pack;
use App\Models\PackSession;
use App\Models\PackSessionCancellation;
use App\Services\PackPayoutService;
use Illuminate\Http\Request;

class PackController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $packs = Pack::with(['offer', 'client:id,name', 'coach:id,name', 'sessions.payout', 'sessions.cancellations'])
            ->where(function ($query) use ($user) {
                $query->where('client_id', $user->id)->orWhere('coach_id', $user->id);
            })
            ->latest()
            ->get();

        return response()->json(['status' => 200, 'packs' => $packs]);
    }

    public function show(Request $request, Pack $pack)
    {
        $this->authorizePack($request, $pack);

        return response()->json([
            'status' => 200,
            'pack' => $pack->load(['offer', 'client:id,name', 'coach:id,name', 'sessions.payout', 'sessions.cancellations']),
        ]);
    }

    public function scheduleSession(Request $request, PackSession $session)
    {
        $data = $request->validate(['scheduled_at' => 'required|date|after:now']);
        $session->loadMissing('pack');
        abort_unless((int) $session->pack->coach_id === (int) $request->user()->id, 403);
        if ($session->status !== 'pending') {
            return response()->json(['status' => 409, 'message' => 'Cette séance ne peut plus être planifiée.'], 409);
        }

        $session->update(['scheduled_at' => $data['scheduled_at']]);

        return response()->json(['status' => 200, 'session' => $session->fresh('pack')]);
    }

    public function completeSession(Request $request, PackSession $session)
    {
        $session->loadMissing('pack');
        abort_unless((int) $session->pack->coach_id === (int) $request->user()->id, 403);

        if ($session->status !== 'pending') {
            return response()->json(['status' => 409, 'message' => 'Cette séance a déjà été déclarée.'], 409);
        }
        if ($session->scheduled_at?->isFuture()) {
            return response()->json(['status' => 409, 'message' => 'La séance planifiée n’est pas encore terminée.'], 409);
        }

        $session->update([
            'status' => 'awaiting_client_confirmation',
            'completed_at' => now(),
            'validation_deadline' => now()->addHours((int) config('services.stripe.pack_validation_delay_hours', 48)),
        ]);

        return response()->json(['status' => 200, 'session' => $session->fresh('pack')]);
    }

    public function validateSession(Request $request, PackSession $session, PackPayoutService $payouts)
    {
        $session->loadMissing('pack');
        abort_unless((int) $session->pack->client_id === (int) $request->user()->id, 403);

        if ($session->status === 'paid') {
            return response()->json(['status' => 200, 'already_paid' => true, 'session' => $session]);
        }
        if ($session->status !== 'awaiting_client_confirmation') {
            return response()->json(['status' => 409, 'message' => 'Cette séance n’attend pas de validation.'], 409);
        }

        $session->update([
            'status' => 'validated',
            'validated_at' => now(),
            'validated_by' => $request->user()->id,
        ]);

        try {
            $session = $payouts->payout($session->fresh());
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 202,
                'message' => 'Séance validée. Le reversement Stripe sera retenté.',
                'session' => $session->fresh(['pack', 'payout']),
            ], 202);
        }

        return response()->json(['status' => 200, 'session' => $session]);
    }

    public function disputeSession(Request $request, PackSession $session)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:2000']);
        $session->loadMissing('pack');
        abort_unless((int) $session->pack->client_id === (int) $request->user()->id, 403);

        if (! in_array($session->status, ['awaiting_client_confirmation', 'validated'], true)
            || $session->stripe_transfer_id) {
            return response()->json(['status' => 409, 'message' => 'Cette séance ne peut plus être contestée.'], 409);
        }

        $session->update([
            'status' => 'disputed',
            'payout_status' => 'blocked',
            'disputed_at' => now(),
            'dispute_reason' => $data['reason'],
        ]);
        $session->pack()->update(['status' => 'disputed']);

        return response()->json(['status' => 200, 'session' => $session->fresh('pack')]);
    }

    public function cancelSession(Request $request, PackSession $session, PackPayoutService $payouts)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:2000']);
        $session->loadMissing('pack');
        $user = $request->user();
        $isClient = (int) $session->pack->client_id === (int) $user->id;
        $isCoach = (int) $session->pack->coach_id === (int) $user->id;
        abort_unless($isClient || $isCoach, 403);

        if ($session->status !== 'pending' || ! $session->scheduled_at) {
            return response()->json(['status' => 409, 'message' => 'Cette séance planifiée ne peut pas être annulée.'], 409);
        }

        $hours = (int) BusinessSetting::value('pack_late_cancellation_hours', 24);
        $isLate = $isClient && now()->diffInMinutes($session->scheduled_at, false) <= ($hours * 60);
        $consumes = $isLate && (bool) BusinessSetting::value('pack_late_cancellation_consumes_session', 1);

        PackSessionCancellation::create([
            'pack_session_id' => $session->id,
            'cancelled_by' => $user->id,
            'actor_role' => $isClient ? 'client' : 'coach',
            'kind' => 'cancellation',
            'scheduled_at' => $session->scheduled_at,
            'is_late' => $isLate,
            'consumes_session' => $consumes,
            'reason' => $data['reason'] ?? null,
        ]);

        if (! $consumes) {
            $session->update(['scheduled_at' => null]);

            return response()->json([
                'status' => 200,
                'message' => 'Séance annulée et remise à disposition dans le pack.',
                'session' => $session->fresh(['pack', 'cancellations']),
            ]);
        }

        return $this->validateConsumedSession($session, $payouts, 'Annulation client tardive : séance consommée.');
    }

    public function noShowSession(Request $request, PackSession $session, PackPayoutService $payouts)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:2000']);
        $session->loadMissing('pack');
        abort_unless((int) $session->pack->coach_id === (int) $request->user()->id, 403);

        $grace = (int) BusinessSetting::value('pack_no_show_grace_minutes', 15);
        if ($session->status !== 'pending' || ! $session->scheduled_at
            || now()->lt($session->scheduled_at->copy()->addMinutes($grace))) {
            return response()->json(['status' => 409, 'message' => 'Le no-show ne peut pas encore être déclaré.'], 409);
        }

        $consumes = (bool) BusinessSetting::value('pack_no_show_consumes_session', 1);
        PackSessionCancellation::create([
            'pack_session_id' => $session->id,
            'cancelled_by' => $request->user()->id,
            'actor_role' => 'coach',
            'kind' => 'no_show',
            'scheduled_at' => $session->scheduled_at,
            'is_late' => true,
            'consumes_session' => $consumes,
            'reason' => $data['reason'] ?? 'Client absent',
        ]);

        if (! $consumes) {
            $session->update(['scheduled_at' => null]);

            return response()->json(['status' => 200, 'session' => $session->fresh(['pack', 'cancellations'])]);
        }

        return $this->validateConsumedSession($session, $payouts, 'No-show client : séance consommée.');
    }

    public function resolveSession(Request $request, PackSession $session, PackPayoutService $payouts)
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate(['decision' => 'required|in:validate,cancel']);
        if ($session->status !== 'disputed') {
            return response()->json(['status' => 409, 'message' => 'Cette séance n’est pas contestée.'], 409);
        }

        if ($data['decision'] === 'cancel') {
            $session->update(['status' => 'cancelled', 'payout_status' => 'cancelled']);

            return response()->json(['status' => 200, 'session' => $session->fresh('pack')]);
        }

        $session->update([
            'status' => 'validated', 'payout_status' => 'pending',
            'validated_at' => now(), 'validated_by' => $request->user()->id,
        ]);
        $session = $payouts->payout($session->fresh());
        $session->pack()->where('status', 'disputed')->update(['status' => 'active']);

        return response()->json(['status' => 200, 'session' => $session->fresh('pack')]);
    }

    private function authorizePack(Request $request, Pack $pack): void
    {
        $user = $request->user();
        if ($user->hasRole('admin')) {
            return;
        }
        abort_unless((int) $pack->client_id === (int) $user->id || (int) $pack->coach_id === (int) $user->id, 403);
    }

    private function validateConsumedSession(
        PackSession $session,
        PackPayoutService $payouts,
        string $message
    ) {
        $session->update([
            'status' => 'validated',
            'completed_at' => now(),
            'validated_at' => now(),
            'validated_by' => null,
        ]);

        try {
            $session = $payouts->payout($session->fresh());
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 202,
                'message' => $message.' Le reversement Stripe sera retenté.',
                'session' => $session->fresh(['pack', 'payout', 'cancellations']),
            ], 202);
        }

        return response()->json([
            'status' => 200,
            'message' => $message,
            'session' => $session->fresh(['pack', 'payout', 'cancellations']),
        ]);
    }
}

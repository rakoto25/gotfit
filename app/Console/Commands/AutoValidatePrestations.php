<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;

class AutoValidatePrestations extends Command
{
    protected $signature = 'gotfit:auto-validate-prestations';

    protected $description = 'Valide automatiquement les prestations payées sans litige après le délai configuré.';

    public function handle(): int
    {
        $reservations = Reservation::query()
            ->where('is_paid', true)
            ->where('payment_status', 'paid')
            ->whereIn('prestation_status', ['paid', 'pending_validation'])
            ->whereNull('validated_at')
            ->whereNotNull('validation_deadline')
            ->where('validation_deadline', '<=', now())
            ->whereNull('stripe_transfer_id')
            ->get();

        foreach ($reservations as $reservation) {
            $reservation->update([
                'prestation_status' => 'validated',
                'validated_at' => now(),
                'validated_by' => null,
                'resolution_note' => 'Validation automatique après délai sans litige.',
            ]);
        }

        $this->info($reservations->count() . ' prestation(s) validée(s) automatiquement.');

        return self::SUCCESS;
    }
}

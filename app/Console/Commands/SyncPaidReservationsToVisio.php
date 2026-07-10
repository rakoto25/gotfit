<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\ReservationVisioService;
use Illuminate\Console\Command;

class SyncPaidReservationsToVisio extends Command
{
    protected $signature = 'gotfit:sync-paid-visio {--reservation=}';
    protected $description = 'Crée et synchronise les séances visio des réservations en ligne payées.';

    public function handle(ReservationVisioService $service): int
    {
        $query = Reservation::with('annonce')
            ->where(function ($q) {
                $q->where('is_paid', true)->orWhere('payment_status', 'paid');
            });

        if ($id = $this->option('reservation')) {
            $query->whereKey($id);
        }

        $synced = 0;
        $query->chunkById(100, function ($reservations) use ($service, &$synced) {
            foreach ($reservations as $reservation) {
                if ($service->syncPaidReservation($reservation)) {
                    $synced++;
                }
            }
        });

        $this->info($synced . ' réservation(s) visio synchronisée(s).');
        return self::SUCCESS;
    }
}

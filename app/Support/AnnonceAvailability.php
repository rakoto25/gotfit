<?php

namespace App\Support;

use App\Models\Annonce;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AnnonceAvailability
{
    public static function assertAvailable(?Annonce $annonce, string $date, string $time): void
    {
        $start = Carbon::parse($date.' '.$time);
        $day = strtolower($start->englishDayOfWeek);
        $minute = $start->hour * 60 + $start->minute;
        $duration = (int) ($annonce?->duration ?: 60);

        if ($annonce && in_array($day, $annonce->available_days ?? [], true) && $start->second === 0) {
            foreach ($annonce->available_hours ?? [] as $range) {
                if (! is_string($range) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/', $range, $parts)) {
                    continue;
                }
                $from = (int) $parts[1] * 60 + (int) $parts[2];
                $to = (int) $parts[3] * 60 + (int) $parts[4];
                if ($minute >= $from && $minute + $duration <= $to) {
                    return;
                }
            }
        }

        throw ValidationException::withMessages([
            'reservation_time' => 'Choisissez un créneau proposé par le coach, assez long pour la séance.',
        ]);
    }
}

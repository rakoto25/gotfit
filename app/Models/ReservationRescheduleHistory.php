<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationRescheduleHistory extends Model
{
    protected $fillable = [
        'reservation_id',
        'changed_by',
        'old_reservation_date',
        'old_reservation_time',
        'new_reservation_date',
        'new_reservation_time',
        'note',
        'source',
        'coach_notified_at',
    ];

    protected $casts = [
        'old_reservation_date' => 'date',
        'new_reservation_date' => 'date',
        'coach_notified_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

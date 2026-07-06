<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisioSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'title',
        'description',
        'start_at',
        'duration_minutes',
        'min_participants',
        'max_participants',
        'price',
        'currency',
        'status',
        'provider',
        'provider_room_id',
        'room_name',
        'join_url',
        'started_at',
        'ended_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'min_participants' => 'integer',
        'max_participants' => 'integer',
        'price' => 'decimal:2',
    ];

    protected $appends = [
        'paid_participants_count',
        'available_places',
        'is_confirmed_by_minimum',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function participants()
    {
        return $this->hasMany(VisioParticipant::class);
    }

    public function clientParticipants()
    {
        return $this->hasMany(VisioParticipant::class)->where('role', 'participant');
    }

    public function paidParticipants()
    {
        return $this->hasMany(VisioParticipant::class)
            ->where('role', 'participant')
            ->whereIn('status', ['paid', 'joined', 'left'])
            ->where('payment_status', 'paid');
    }

    public function getPaidParticipantsCountAttribute(): int
    {
        if ($this->relationLoaded('paidParticipants')) {
            return $this->paidParticipants->count();
        }

        return $this->paidParticipants()->count();
    }

    public function getAvailablePlacesAttribute(): ?int
    {
        if (!$this->max_participants) {
            return null;
        }

        $reservedCount = $this->clientParticipants()
            ->whereIn('status', ['reserved', 'paid', 'joined', 'left'])
            ->count();

        return max(0, $this->max_participants - $reservedCount);
    }

    public function getIsConfirmedByMinimumAttribute(): bool
    {
        return $this->paid_participants_count >= $this->min_participants;
    }
}

<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'annonce_id',
        'client_id',
        'intervenant_id',
        'visio_session_id',
        'reservation_date',
        'reservation_time',
        'guests',
        'note',
        'price',
        'service_fee_rate',
        'service_fee_amount',
        'commission_rate',
        'commission_amount',
        'intervenant_amount',
        'total_client_amount',
        'currency',
        'status',
        'is_paid',
        'payment_status',
        'payment_intent_id',
        'stripe_charge_id',
        'paid_at',
        'prestation_status',
        'validated_at',
        'validated_by',
        'validation_deadline',
        'disputed_at',
        'dispute_reason',
        'resolved_at',
        'resolution_note',
        'refunded_at',
        'refund_reason',
        'transferred_at',
        'stripe_transfer_id',
        'payout_status',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'validated_at' => 'datetime',
        'validation_deadline' => 'datetime',
        'disputed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'refunded_at' => 'datetime',
        'transferred_at' => 'datetime',
        'price' => 'decimal:2',
        'service_fee_rate' => 'decimal:2',
        'service_fee_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'intervenant_amount' => 'decimal:2',
        'total_client_amount' => 'decimal:2',
    ];

    public function scheduledAt(): Carbon
    {
        $date = $this->reservation_date instanceof Carbon
            ? $this->reservation_date->format('Y-m-d')
            : Carbon::parse($this->reservation_date)->format('Y-m-d');

        return Carbon::parse($date . ' ' . $this->reservation_time);
    }

    public function endsAt(): Carbon
    {
        $duration = (int) ($this->annonce?->duration ?: 60);

        return $this->scheduledAt()->copy()->addMinutes(max($duration, 15));
    }

    public function hasSessionPassed(): bool
    {
        return now()->greaterThanOrEqualTo($this->endsAt());
    }

    public function calendarTitle(): string
    {
        return $this->annonce?->titre ?: 'Séance GotFit';
    }

    public function annonce()
    {
        return $this->belongsTo(Annonce::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function intervenant()
    {
        return $this->belongsTo(User::class, 'intervenant_id');
    }

    public function visioSession()
    {
        return $this->belongsTo(VisioSession::class, 'visio_session_id');
    }

    public function payement()
    {
        return $this->hasOne(Payement::class, 'reservation_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function notes()
    {
        return $this->hasMany(ClientNote::class);
    }
}

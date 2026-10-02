<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'coach_id', 'client_id', 'title', 'description',
        'session_count', 'amount_total', 'currency', 'status', 'expires_at',
        'stripe_checkout_session_id', 'stripe_payment_intent_id', 'paid_at', 'cancelled_at',
    ];

    protected $casts = [
        'session_count' => 'integer',
        'amount_total' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = ['amount_major', 'is_expired'];

    public function conversation()
    {
        return $this->belongsTo(Conversations::class);
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function message()
    {
        return $this->hasOne(Message::class);
    }

    public function pack()
    {
        return $this->hasOne(Pack::class);
    }

    public function getAmountMajorAttribute(): float
    {
        return $this->amount_total / 100;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === 'expired'
            || ($this->status === 'sent' && $this->expires_at?->isPast());
    }
}

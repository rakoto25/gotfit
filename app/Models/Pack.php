<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id', 'client_id', 'coach_id', 'stripe_payment_intent_id', 'stripe_charge_id',
        'amount_total', 'wallet_amount_used', 'stripe_amount_paid', 'refunded_amount',
        'commission_rate', 'commission_amount', 'coach_net_amount',
        'amount_transferred', 'session_count', 'completed_sessions', 'currency', 'status', 'paid_at',
    ];

    protected $casts = [
        'amount_total' => 'integer', 'wallet_amount_used' => 'integer',
        'stripe_amount_paid' => 'integer', 'refunded_amount' => 'integer',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'integer', 'coach_net_amount' => 'integer',
        'amount_transferred' => 'integer', 'session_count' => 'integer',
        'completed_sessions' => 'integer', 'paid_at' => 'datetime',
    ];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function sessions()
    {
        return $this->hasMany(PackSession::class)->orderBy('sequence');
    }

    public function payouts()
    {
        return $this->hasMany(PackPayout::class);
    }
}

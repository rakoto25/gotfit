<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payement extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'reservation_id', 'payment_intent_id', 'amount', 'service_fee',
        'commission_rate', 'commission', 'intervenant_amount', 'net_amount',
        'intervenant_id', 'client_id', 'currency', 'status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission' => 'decimal:2',
        'intervenant_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function intervenant()
    {
        return $this->belongsTo(User::class, 'intervenant_id');
    }
}

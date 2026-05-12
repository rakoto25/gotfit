<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'intervenant_id',
        'reservation_date',
        'reservation_time',
        'guests',
        'note',
        'status',
    ];

    // CLIENT (celui qui réserve)
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    // INTERVENANT (prestataire / agent)
    public function intervenant()
    {
        return $this->belongsTo(User::class, 'intervenant_id');
    }

    public function payement()
    {
        return $this->hasOne(Payement::class, 'intervenant_id', 'intervenant_id');
    }
}
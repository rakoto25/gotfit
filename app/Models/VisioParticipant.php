<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisioParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'visio_session_id',
        'user_id',
        'role',
        'status',
        'payment_status',
        'amount',
        'currency',
        'payment_intent_id',
        'paid_at',
        'joined_at',
        'left_at',
        'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(VisioSession::class, 'visio_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

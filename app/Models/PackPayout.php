<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'pack_session_id', 'pack_id', 'coach_id', 'client_id', 'stripe_transfer_id',
        'amount', 'currency', 'status', 'failure_reason', 'transferred_at',
    ];

    protected $casts = ['amount' => 'integer', 'transferred_at' => 'datetime'];

    public function session()
    {
        return $this->belongsTo(PackSession::class, 'pack_session_id');
    }

    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }
}

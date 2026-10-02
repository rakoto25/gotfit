<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'pack_id', 'sequence', 'amount_due', 'status', 'payout_status', 'scheduled_at', 'completed_at',
        'validation_deadline', 'validated_at', 'validated_by', 'disputed_at',
        'dispute_reason', 'stripe_transfer_id', 'transferred_at', 'payout_error',
    ];

    protected $casts = [
        'sequence' => 'integer', 'amount_due' => 'integer', 'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'validation_deadline' => 'datetime', 'validated_at' => 'datetime',
        'disputed_at' => 'datetime', 'transferred_at' => 'datetime',
    ];

    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function payout()
    {
        return $this->hasOne(PackPayout::class);
    }

    public function cancellations()
    {
        return $this->hasMany(PackSessionCancellation::class)->latest();
    }
}

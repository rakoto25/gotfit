<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id', 'pack_id', 'payment_intent_id', 'type', 'amount',
        'balance_after', 'idempotency_key', 'metadata',
    ];

    protected $casts = ['amount' => 'integer', 'balance_after' => 'integer', 'metadata' => 'array'];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }
}

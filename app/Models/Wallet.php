<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'balance', 'currency'];

    protected $casts = ['balance' => 'integer'];

    protected $appends = ['balance_major'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class)->latest();
    }

    public function getBalanceMajorAttribute(): float
    {
        return $this->balance / 100;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeEvent extends Model
{
    protected $fillable = ['event_id', 'type', 'status', 'error', 'processed_at'];

    protected $casts = ['processed_at' => 'datetime'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackSessionCancellation extends Model
{
    use HasFactory;

    protected $fillable = [
        'pack_session_id', 'cancelled_by', 'actor_role', 'kind', 'scheduled_at',
        'is_late', 'consumes_session', 'reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'is_late' => 'boolean',
        'consumes_session' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(PackSession::class, 'pack_session_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

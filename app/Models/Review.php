<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id', 'client_id', 'intervenant_id', 'rating',
        'comment', 'status', 'moderated_by', 'moderated_at', 'rejection_reason'
    ];

    protected $casts = [
        'moderated_at' => 'datetime',
        'rating' => 'integer',
    ];

    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function client() { return $this->belongsTo(User::class, 'client_id'); }
    public function intervenant() { return $this->belongsTo(User::class, 'intervenant_id'); }
}

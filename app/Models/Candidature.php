<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidature extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id', 'intervenant_id', 'message', 'proposed_price', 'status'
    ];

    protected $casts = [
        'proposed_price' => 'decimal:2',
    ];

    public function mission() { return $this->belongsTo(Mission::class); }
    public function intervenant() { return $this->belongsTo(User::class, 'intervenant_id'); }
}

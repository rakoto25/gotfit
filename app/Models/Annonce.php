<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Annonce extends Model
{
    use HasFactory;

    public const DEFAULT_MAX_PARTICIPANTS = 2;

    public const MAX_PARTICIPANTS = 4;

    protected $fillable = [
        'titre', 'contenu', 'user_id', 'status', 'announcement_type', 'reserved_by',
        'category', 'type_prestation', 'price', 'duration', 'max_participants', 'is_online',
        'location', 'city', 'address', 'latitude', 'longitude',
        'available_days', 'available_hours', 'image', 'is_boosted', 'boost_until',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration' => 'integer',
        'max_participants' => 'integer',
        'is_online' => 'boolean',
        'is_boosted' => 'boolean',
        'available_days' => 'array',
        'available_hours' => 'array',
        'boost_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}

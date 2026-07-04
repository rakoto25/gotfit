<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientOnboarding extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'goals',
        'level',
        'training_preferences',
        'availability',
        'health_constraints',
        'measurements',
        'lifestyle',
        'emergency_contact',
        'answers',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'goals' => 'array',
        'training_preferences' => 'array',
        'availability' => 'array',
        'health_constraints' => 'array',
        'measurements' => 'array',
        'lifestyle' => 'array',
        'emergency_contact' => 'array',
        'answers' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}

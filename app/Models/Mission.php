<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mission extends Model
{
    use HasFactory;

    protected $fillable = [
        'structure_id', 'title', 'description', 'category', 'budget',
        'mission_date', 'mission_time', 'location', 'city', 'address', 'status'
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'mission_date' => 'date',
    ];

    public function structure() { return $this->belongsTo(User::class, 'structure_id'); }
    public function candidatures() { return $this->hasMany(Candidature::class); }
}

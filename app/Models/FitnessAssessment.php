<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FitnessAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'form_id',
        'answers',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'coach_notes',
    ];

    protected $casts = [
        'answers' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function form()
    {
        return $this->belongsTo(FitnessAssessmentForm::class, 'form_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

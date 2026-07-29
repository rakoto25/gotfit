<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FitnessAssessmentForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'version',
        'questions',
        'is_active',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'questions' => 'array',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $builder) {
                $builder->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assessments()
    {
        return $this->hasMany(FitnessAssessment::class, 'form_id');
    }
}

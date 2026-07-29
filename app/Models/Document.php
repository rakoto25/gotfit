<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'document_type', 'file_path', 'status',
        'document_number', 'issuing_organization', 'issued_at', 'expires_at',
        'validated_by', 'validated_at', 'rejection_reason',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'validated_at' => 'datetime',
    ];

    protected $appends = [
        'file_url',
        'is_expired',
    ];

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path
            ? Storage::disk('public')->url($this->file_path)
            : null;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}

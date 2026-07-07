<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Les champs modifiables.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'photo',
        'cover_photo',
        'presentation_video',
        'presentation_video_duration_seconds',
        'bio',
        'coach_title',
        'coach_short_description',
        'coach_speciality',
        'coach_experience_years',
        'coach_certifications',
        'coach_languages',
        'phone',
        'address',
        'account_status',
        'validated_by',
        'validated_at',
        'rejection_reason',
        'fcm_token',
        'stripe_account_id',
        'stripe_onboarding_completed',
    ];

    /**
     * Les champs cachés dans les réponses JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Les champs ajoutés automatiquement dans les réponses JSON.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'photo_url',
        'cover_photo_url',
        'presentation_video_url',
    ];

    /**
     * Les casts.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'validated_at' => 'datetime',
        'stripe_onboarding_completed' => 'boolean',
        'presentation_video_duration_seconds' => 'integer',
        'coach_experience_years' => 'integer',
    ];

    /**
     * URL complète de la photo de profil pour l'application mobile.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }

        return asset('storage/' . $this->photo);
    }

    /**
     * URL complète de la photo de couverture pour l'application mobile.
     */
    public function getCoverPhotoUrlAttribute(): ?string
    {
        if (!$this->cover_photo) {
            return null;
        }

        return asset('storage/' . $this->cover_photo);
    }

    /**
     * URL complète de la vidéo de présentation du coach.
     */
    public function getPresentationVideoUrlAttribute(): ?string
    {
        if (!$this->presentation_video) {
            return null;
        }

        return asset('storage/' . $this->presentation_video);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function annonces(): HasMany
    {
        return $this->hasMany(Annonce::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function isIntervenant(): bool
    {
        return $this->hasRole('intervenant');
    }

    public function isClient(): bool
    {
        return $this->hasRole('client');
    }

    public function isStructure(): bool
    {
        return $this->hasRole('structure');
    }

    public function reservationsAsClient(): HasMany
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }

    public function reservationsAsIntervenant(): HasMany
    {
        return $this->hasMany(Reservation::class, 'intervenant_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class, 'structure_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'intervenant_id');
    }

    public function clientNotes(): HasMany
    {
        return $this->hasMany(ClientNote::class, 'client_id');
    }

    public function authoredClientNotes(): HasMany
    {
        return $this->hasMany(ClientNote::class, 'author_id');
    }

    public function clientOnboarding()
    {
        return $this->hasOne(ClientOnboarding::class, 'client_id');
    }

    public function visioSessionsAsCoach(): HasMany
    {
        return $this->hasMany(VisioSession::class, 'coach_id');
    }

    public function visioParticipations(): HasMany
    {
        return $this->hasMany(VisioParticipant::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nom exact de la table.
     */
    protected $table = 'messages';

    /**
     * Champs autorisés en création / modification.
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'receiver_id',
        'parent_id',
        'subject',
        'message',
        'type',
        'media_url',
        'media_type',
        'is_read',
        'read_at',
        'replied_at',
        'status',
        'is_admin_broadcast',
        'broadcast_group',
        'broadcast_target_role',
    ];

    /**
     * Casts pour la communication admin et les statuts de lecture.
     */
    protected $casts = [
        'is_read' => 'boolean',
        'is_admin_broadcast' => 'boolean',
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Ajouter automatiquement l'URL complète du média dans les réponses API.
     */
    protected $appends = [
        'media_full_url',
        'is_edited',
        'is_deleted',
    ];

    /**
     * Conversation liée à ce message.
     *
     * Important :
     * On précise explicitement 'conversation_id',
     * sinon Laravel peut chercher une mauvaise clé.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversations::class, 'conversation_id');
    }

    /**
     * Utilisateur qui a envoyé le message.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Utilisateur destinataire du message.
     *
     * Utilisé surtout pour les diffusions administrateur vers tous les coachs.
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Message parent si ce message est une réponse.
     *
     * Exemple :
     * Jean répond au message de Marc.
     * Le nouveau message aura parent_id = id du message de Marc.
     */
    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id')->with('sender');
    }

    /**
     * Réponses liées à ce message.
     */
    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    /**
     * Réactions du message : like, dislike, love, etc.
     */
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    /**
     * URL complète du média.
     *
     * media_url stocke par exemple :
     * messages/videos/video.mp4
     *
     * media_full_url retourne :
     * http://187.77.181.212/storage/messages/videos/video.mp4
     */
    public function getMediaFullUrlAttribute()
    {
        if (! $this->media_url) {
            return null;
        }

        if (str_starts_with($this->media_url, 'http')) {
            return $this->media_url;
        }

        return asset('storage/'.$this->media_url);
    }

    public function getIsEditedAttribute(): bool
    {
        return $this->edited_at !== null;
    }

    public function getIsDeletedAttribute(): bool
    {
        return $this->trashed();
    }
}

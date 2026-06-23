<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

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
        'parent_id',
        'message',
        'type',
        'media_url',
        'media_type',
    ];

    /**
     * Ajouter automatiquement l'URL complète du média dans les réponses API.
     */
    protected $appends = [
        'media_full_url',
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
        if (!$this->media_url) {
            return null;
        }

        if (str_starts_with($this->media_url, 'http')) {
            return $this->media_url;
        }

        return asset('storage/' . $this->media_url);
    }
}

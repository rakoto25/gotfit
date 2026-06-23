<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversations extends Model
{
    use HasFactory;

    /**
     * Nom exact de la table.
     */
    protected $table = 'conversations';

    /**
     * Champs autorisés en création / modification.
     */
    protected $fillable = [
        'client_id',
        'intervenant_id',
    ];

    /**
     * Relation avec le client.
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Relation avec l'intervenant.
     */
    public function intervenant()
    {
        return $this->belongsTo(User::class, 'intervenant_id');
    }

    /**
     * Messages de la conversation.
     *
     * Important :
     * On précise explicitement 'conversation_id',
     * sinon Laravel peut chercher 'conversations_id'.
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    /**
     * Dernier message de la conversation.
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }
}

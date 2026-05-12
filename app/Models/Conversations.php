<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversations extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'intervenant_id'
    ];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}

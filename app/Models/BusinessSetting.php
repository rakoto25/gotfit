<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'description'];

    public static function value(string $key, $default = null)
    {
        return static::where('key', $key)->value('value') ?? $default;
    }
}

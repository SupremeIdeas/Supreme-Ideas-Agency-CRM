<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserHeaderPref extends Model
{
    protected $fillable = ['user_id', 'items', 'merchant_button'];

    protected function casts(): array
    {
        return ['items' => 'array', 'merchant_button' => 'boolean'];
    }
}

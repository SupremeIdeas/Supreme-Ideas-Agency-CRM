<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A Naara Pro unlock slot spent on a handbook (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookProSlot extends Model
{
    public $timestamps = false;

    protected $table = 'handbook_pro_slots';

    protected $fillable = ['user_id', 'pro_term', 'handbook_id', 'used_at'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A mood tag (Quick-start, Deep dive, Toolkit, Inspire, Companion) (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookMood extends Model
{
    protected $table = 'handbook_moods';

    protected $fillable = ['slug', 'name'];

    public function handbooks(): BelongsToMany
    {
        return $this->belongsToMany(Handbook::class, 'handbook_handbook_mood');
    }
}

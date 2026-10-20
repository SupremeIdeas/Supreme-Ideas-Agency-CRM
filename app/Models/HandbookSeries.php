<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A country series ("The Ghana Series") (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookSeries extends Model
{
    protected $table = 'handbook_series';

    protected $fillable = ['country_code', 'name', 'description'];

    public function handbooks(): HasMany
    {
        return $this->hasMany(Handbook::class, 'series_id')->orderBy('series_slot');
    }
}

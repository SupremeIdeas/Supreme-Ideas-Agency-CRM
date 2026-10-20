<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A store category (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookCategory extends Model
{
    protected $table = 'handbook_categories';

    protected $fillable = ['slug', 'name'];

    public function handbooks(): BelongsToMany
    {
        return $this->belongsToMany(Handbook::class, 'handbook_category_handbook');
    }
}

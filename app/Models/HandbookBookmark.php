<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A reader bookmark (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookBookmark extends Model
{
    protected $table = 'handbook_bookmarks';

    protected $fillable = ['user_id', 'handbook_id', 'locator', 'label'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A published edition (history; the current one is on the handbook) (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookEdition extends Model
{
    protected $table = 'handbook_editions';

    protected $fillable = ['handbook_id', 'edition', 'epub_path', 'pdf_path', 'notes', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}

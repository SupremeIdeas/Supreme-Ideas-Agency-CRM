<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Where a reader is up to (synced across devices) (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookReadingState extends Model
{
    public $timestamps = false;

    protected $table = 'handbook_reading_state';

    protected $fillable = ['user_id', 'handbook_id', 'locator', 'percent', 'updated_at'];

    protected function casts(): array
    {
        return ['percent' => 'decimal:2', 'updated_at' => 'datetime'];
    }
}

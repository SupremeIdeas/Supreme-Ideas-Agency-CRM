<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One download, for rate limits and anomaly checks (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookDownload extends Model
{
    public $timestamps = false;

    protected $table = 'handbook_downloads';

    protected $fillable = ['user_id', 'handbook_id', 'format', 'edition', 'stamped', 'ip_hash', 'created_at'];

    protected function casts(): array
    {
        return ['stamped' => 'boolean', 'created_at' => 'datetime'];
    }
}

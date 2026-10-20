<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The audiobook of a handbook, sold separately (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookAudiobook extends Model
{
    protected $table = 'handbook_audiobooks';

    protected $fillable = ['handbook_id', 'price_usd', 'stream_enabled', 'download_enabled', 'is_enabled', 'total_seconds'];

    protected function casts(): array
    {
        return ['price_usd' => 'decimal:2', 'stream_enabled' => 'boolean', 'download_enabled' => 'boolean', 'is_enabled' => 'boolean'];
    }

    public function handbook(): BelongsTo
    {
        return $this->belongsTo(Handbook::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(HandbookAudioTrack::class, 'audiobook_id')->orderBy('position');
    }
}

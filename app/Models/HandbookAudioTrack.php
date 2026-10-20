<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One audiobook chapter file (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookAudioTrack extends Model
{
    protected $table = 'handbook_audio_tracks';

    protected $fillable = ['audiobook_id', 'position', 'chapter_title', 'file_path', 'seconds'];

    public function audiobook(): BelongsTo
    {
        return $this->belongsTo(HandbookAudiobook::class, 'audiobook_id');
    }
}

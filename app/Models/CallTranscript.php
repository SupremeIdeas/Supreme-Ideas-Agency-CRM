<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallTranscript extends Model
{
    protected $fillable = ['call_recording_id', 'transcript_text', 'transcript_source'];

    protected $hidden = ['transcript_source'];

    public function recording(): BelongsTo
    {
        return $this->belongsTo(CallRecording::class, 'call_recording_id');
    }
}

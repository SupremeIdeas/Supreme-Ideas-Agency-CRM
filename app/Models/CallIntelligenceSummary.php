<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallIntelligenceSummary extends Model
{
    protected $fillable = ['call_recording_id', 'sentiment', 'talk_listen_ratio', 'keywords', 'coaching_notes', 'summary_text'];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'talk_listen_ratio' => 'float'];
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(CallRecording::class, 'call_recording_id');
    }
}

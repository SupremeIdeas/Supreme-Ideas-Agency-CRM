<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A person's audiobook purchase (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookAudioEntitlement extends Model
{
    protected $table = 'handbook_audio_entitlements';

    protected $fillable = ['user_id', 'audiobook_id', 'source_ref', 'price_paid_usd'];

    protected function casts(): array
    {
        return ['price_paid_usd' => 'decimal:2'];
    }

    public function audiobook(): BelongsTo
    {
        return $this->belongsTo(HandbookAudiobook::class, 'audiobook_id');
    }
}

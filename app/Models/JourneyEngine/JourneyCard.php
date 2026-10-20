<?php

namespace App\Models\JourneyEngine;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One suggestion or alert shown to one user, with the evidence that justified it (shown on "Why am I seeing this?"). */
class JourneyCard extends Model
{
    public const SHOWN = 'shown';

    public const CLICKED = 'clicked';

    public const DISMISSED = 'dismissed';

    public const SNOOZED = 'snoozed';

    public const CONVERTED = 'converted';

    public const EXPIRED = 'expired';

    protected $fillable = ['user_id', 'rule_key', 'subject_key', 'priority', 'state', 'evidence', 'shown_count', 'dismiss_count', 'first_shown_at', 'last_shown_at', 'acted_at', 'snooze_until', 'holdout'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'first_shown_at' => 'datetime', 'last_shown_at' => 'datetime', 'acted_at' => 'datetime', 'snooze_until' => 'datetime', 'holdout' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

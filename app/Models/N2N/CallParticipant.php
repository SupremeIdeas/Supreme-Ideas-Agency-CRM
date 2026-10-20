<?php

namespace App\Models\N2N;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One member's presence in a group call (N2N Batch 11, MASTER-ONLY). */
class CallParticipant extends Model
{
    public const HOST = 'host';

    public const SPEAKER = 'speaker';

    public const LISTENER = 'listener';

    protected $table = 'n2n_call_participants';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['speaking' => 'boolean', 'pending_at' => 'datetime', 'joined_at' => 'datetime', 'left_at' => 'datetime', 'banned_at' => 'datetime', 'last_beat_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(GroupCall::class, 'group_call_id');
    }

    /** In the room right now (not left, not waiting for approval, not removed). */
    public function present(): bool
    {
        return $this->left_at === null && $this->pending_at === null && $this->banned_at === null && $this->joined_at !== null;
    }
}

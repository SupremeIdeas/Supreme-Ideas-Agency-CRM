<?php

namespace App\Models\N2N;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One 1:1 voice call (N2N Batch 10, MASTER-ONLY). */
class Call extends Model
{
    public const RINGING = 'ringing';

    public const ACTIVE = 'active';

    protected $table = 'n2n_calls';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['suppressed' => 'boolean', 'video' => 'boolean', 'relay_used' => 'boolean', 'ring_expires_at' => 'datetime', 'answered_at' => 'datetime', 'ends_at' => 'datetime', 'ended_at' => 'datetime', 'caller_beat_at' => 'datetime', 'callee_beat_at' => 'datetime'];
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function isParticipant(int $userId): bool
    {
        return $this->caller_id === $userId || $this->callee_id === $userId;
    }

    public function peerOf(int $userId): int
    {
        return $this->caller_id === $userId ? $this->callee_id : $this->caller_id;
    }

    public function live(): bool
    {
        return in_array($this->state, [self::RINGING, self::ACTIVE], true);
    }
}

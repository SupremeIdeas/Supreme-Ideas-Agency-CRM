<?php

namespace App\Models\N2N;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One group call room (N2N Batch 11, MASTER-ONLY). */
class GroupCall extends Model
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected $table = 'n2n_group_calls';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'last_active_at' => 'datetime'];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class, 'group_call_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(CallLink::class, 'group_call_id');
    }

    public function isOpen(): bool
    {
        return $this->state === self::OPEN;
    }
}

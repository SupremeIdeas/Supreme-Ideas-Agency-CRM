<?php

namespace App\Models\N2N;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A user-to-user conversation (N2N, MASTER-ONLY). status `request` until the recipient accepts a first message from a non-connection. */
class Conversation extends Model
{
    public const REQUEST = 'request';

    public const ACTIVE = 'active';

    protected $table = 'n2n_conversations';

    protected $fillable = ['type', 'status', 'requested_by', 'pair_key', 'last_message_id', 'last_message_at', 'disappear_after', 'disappear_by'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public static function pairKey(int $a, int $b): string
    {
        return min($a, $b).':'.max($a, $b);
    }

    public function isParticipant(int $userId): bool
    {
        return $this->participants->contains('user_id', $userId) || $this->participants()->where('user_id', $userId)->exists();
    }
}

<?php

namespace App\Models\N2N;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One member's view of a conversation: read marker and (B12) per-viewer mute/archive/pin/theme. N2N, MASTER-ONLY. */
class Participant extends Model
{
    protected $table = 'n2n_participants';

    protected $fillable = ['conversation_id', 'user_id', 'last_read_message_id', 'muted_until', 'archived_at', 'pinned_at', 'pinned_message_id', 'theme_json', 'marked_unread_at', 'cleared_before_id', 'cleared_at', 'removed_at', 'locked_at'];

    protected function casts(): array
    {
        return ['muted_until' => 'datetime', 'archived_at' => 'datetime', 'pinned_at' => 'datetime', 'marked_unread_at' => 'datetime', 'cleared_at' => 'datetime', 'removed_at' => 'datetime', 'locked_at' => 'datetime', 'theme_json' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}

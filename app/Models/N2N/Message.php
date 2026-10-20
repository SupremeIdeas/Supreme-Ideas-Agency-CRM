<?php

namespace App\Models\N2N;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** One N2N message (MASTER-ONLY). Text is rendered as text everywhere: never as HTML. */
class Message extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_messages';

    protected $fillable = ['conversation_id', 'sender_id', 'client_id', 'type', 'body', 'reply_to_id', 'deliver_at', 'cost_credits', 'ledger_ref', 'suppressed', 'deleted_for_all_at', 'created_at', 'quote_json', 'forwarded', 'edited_at', 'edit_log', 'expires_at'];

    protected function casts(): array
    {
        return ['deliver_at' => 'datetime', 'created_at' => 'datetime', 'deleted_for_all_at' => 'datetime', 'suppressed' => 'boolean', 'cost_credits' => 'decimal:2', 'quote_json' => 'array', 'forwarded' => 'boolean', 'edited_at' => 'datetime', 'expires_at' => 'datetime', 'edit_log' => 'array'];
    }

    /**
     * What ONE member may see: not a dropped message (unless their own), nothing at or before their "clear chat" mark, nothing they chose to delete for themselves.
     * Every per-member read of messages goes through this so a cleared or hidden message cannot resurface in search, media, stars, export or the list preview.
     */
    public function scopeForViewer(Builder $q, int $userId): Builder
    {
        return $q->where(fn ($w) => $w->where('n2n_messages.suppressed', false)->orWhere('n2n_messages.sender_id', $userId))
            ->where(fn ($w) => $w->whereNull('n2n_messages.expires_at')->orWhere('n2n_messages.expires_at', '>', now()))   // a disappearing message is gone for both people the moment it expires
            ->whereRaw('n2n_messages.id > COALESCE((SELECT p.cleared_before_id FROM n2n_participants p WHERE p.conversation_id = n2n_messages.conversation_id AND p.user_id = ?), 0)', [$userId])
            ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('n2n_message_hides as h')->whereColumn('h.message_id', 'n2n_messages.id')->where('h.user_id', $userId));
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(MessageReceipt::class, 'message_id');
    }
}

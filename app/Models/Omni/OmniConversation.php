<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One thread between a workspace and a contact on one channel (MASTER-ONLY). */
class OmniConversation extends Model
{
    public const WINDOW_HOURS = 24;

    protected $table = 'omni_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'last_inbound_at' => 'datetime', 'unread' => 'integer'];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(OmniContact::class, 'contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OmniMessage::class, 'conversation_id');
    }

    /** True while a free-form reply is allowed: the customer wrote within the last 24 hours. */
    public function windowOpen(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subHours(self::WINDOW_HOURS));
    }
}

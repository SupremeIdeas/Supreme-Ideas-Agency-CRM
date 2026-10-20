<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace integration's webhook (MASTER-ONLY, Business Suite Prompt 18): one URL, one event type, signed with its own secret. The secret is shown once when
 * the subscription is created and is encrypted at rest. After repeated failed deliveries the subscription is switched to `failed` and the owner sees it in the
 * developer portal; nothing is dropped silently.
 */
class WebhookSubscription extends Model
{
    public const ACTIVE = 'active';

    public const FAILED = 'failed';

    public const DISABLED = 'disabled';

    /** Event types an integration can subscribe to. Extend as new event-producing prompts land. */
    public const EVENTS = ['call.completed', 'message.received', 'call.recording_ready'];

    /** Consecutive failed deliveries (each already retried with backoff) before the subscription is switched off. */
    public const FAILURE_LIMIT = 3;

    protected $fillable = ['api_client_id', 'wallet_group_id', 'event_type', 'target_url', 'secret', 'status', 'consecutive_failures', 'last_status_code', 'last_error', 'last_delivery_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'last_delivery_at' => 'datetime', 'consecutive_failures' => 'integer'];
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}

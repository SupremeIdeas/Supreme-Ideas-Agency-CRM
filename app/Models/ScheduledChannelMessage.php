<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A shared-inbox reply queued for later (MASTER-ONLY, Business Suite). The payload is an OutgoingMessage as JSON; files are already stored privately. */
class ScheduledChannelMessage extends Model
{
    public const PENDING = 'pending';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['wallet_group_id', 'message_thread_id', 'user_id', 'idempotency_key', 'payload', 'send_at', 'status', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'send_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'message_thread_id');
    }

    /** A short line for the timeline: what is waiting to go out. */
    public function summary(): string
    {
        $p = $this->payload ?? [];
        $parts = [];
        $text = trim((string) ($p['text'] ?? ''));
        if ($text !== '') {
            $parts[] = \Illuminate\Support\Str::limit($text, 80);
        }
        foreach (($p['files'] ?? []) as $f) {
            $parts[] = \App\Services\Inbox\ChannelSender::label((string) ($f['kind'] ?? ''));
        }
        foreach (['location' => 'Location', 'contact' => 'Contact', 'poll' => 'Poll'] as $k => $l) {
            if (! empty($p[$k])) {
                $parts[] = $l;
            }
        }

        return implode(' + ', $parts) ?: 'Message';
    }
}

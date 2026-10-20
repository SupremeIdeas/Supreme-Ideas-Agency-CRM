<?php

namespace App\Services\Messaging;

use App\Models\ConversationEvent;
use App\Models\InboundMessage;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Models\VoiceCall;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The unified conversation timeline (Brand-Growth Phase 9, MASTER-ONLY). Every Naara chat message, SMS (sent and received) and call is written ONCE per person as a
 * `conversation_events` row keyed by (person, source row), so the same thread reads as one ordered list whichever channel carried each line. Writes are idempotent
 * upserts; the source tables stay the truth and `messaging:reconcile` rebuilds any missing or stale row from them. This class records and reads: it never sends or charges.
 */
class ConversationTimeline
{
    /** One stable spelling for "the other side": a Naara ID upper-cased, a phone number as digits with a leading plus. */
    public static function peerKey(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^NR-/i', $raw) === 1) {
            return strtoupper($raw);
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits === '' ? mb_strtolower($raw) : '+'.$digits;
    }

    public function record(int $userId, string $peer, string $channel, string $kind, string $direction, string $status, ?string $summary, string $sourceType, int $sourceId, ?Carbon $at = null): ConversationEvent
    {
        return ConversationEvent::query()->updateOrCreate(
            ['user_id' => $userId, 'source_type' => $sourceType, 'source_id' => $sourceId],
            ['peer_key' => self::peerKey($peer), 'channel' => $channel, 'kind' => $kind, 'direction' => $direction, 'status' => $status, 'summary' => $summary !== null ? mb_substr($summary, 0, 240) : null, 'occurred_at' => $at ?? now()],
        );
    }

    public function fromOutboundSms(OutboundMessage $m): ConversationEvent
    {
        return $this->record((int) $m->user_id, (string) $m->to_number, 'sms', 'message', 'out', (string) $m->status, $m->body ?: ($m->attachment_url ? 'Attachment' : null), 'outbound_sms', (int) $m->id, $m->created_at);
    }

    public function fromInboundSms(InboundMessage $m): ConversationEvent
    {
        return $this->record((int) $m->user_id, (string) $m->from_number, 'sms', 'message', 'in', 'received', $m->body ?: ($m->attachment_url ? 'Attachment' : ($m->voicemail_path ? 'Voicemail' : null)), 'inbound_sms', (int) $m->id, $m->received_at ?? $m->created_at);
    }

    public function fromCall(VoiceCall $c): ConversationEvent
    {
        $mins = $c->duration_seconds ? ' ('.max(1, (int) ceil($c->duration_seconds / 60)).' min)' : '';

        return $this->record((int) $c->user_id, (string) $c->destination, 'call', 'call', 'out', (string) $c->status, 'Call'.$mins, 'voice_call', (int) $c->id, $c->created_at);
    }

    /** One person's timeline with one peer, oldest first, across every channel. @return Collection<int, ConversationEvent> */
    public function with(User $user, string $peer, int $limit = 200): Collection
    {
        return ConversationEvent::query()->where('user_id', $user->id)->where('peer_key', self::peerKey($peer))
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit(max(1, min($limit, 500)))->get()->reverse()->values();
    }
}

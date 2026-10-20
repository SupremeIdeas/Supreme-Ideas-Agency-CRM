<?php

namespace App\Services\N2N\Messaging;

use App\Models\InboundMessage;
use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\Messaging\ConversationTimeline;

/**
 * Keeps the unified conversation timeline current (Brand-Growth Phase 9, MASTER-ONLY). Registered from N2NServiceProvider so no shared file carries it: a source row being
 * saved writes its timeline line as a side effect. It never throws into a money or messaging path (a failure is reported and `messaging:reconcile` rebuilds the line).
 */
class ConversationEventRecorder
{
    public static function register(): void
    {
        $t = fn () => app(ConversationTimeline::class);
        OutboundMessage::saved(fn (OutboundMessage $m) => self::safe(fn () => $t()->fromOutboundSms($m)));
        InboundMessage::saved(fn (InboundMessage $m) => self::safe(fn () => $t()->fromInboundSms($m)));
        VoiceCall::saved(fn (VoiceCall $c) => self::safe(fn () => $t()->fromCall($c)));
        Message::created(fn (Message $m) => self::safe(fn () => self::n2n($m)));
    }

    /** One line for each of the two people in the conversation, each pointing at the other's Naara ID. */
    public static function n2n(Message $m): void
    {
        $ids = Participant::query()->where('conversation_id', $m->conversation_id)->pluck('user_id')->map(fn ($i) => (int) $i)->all();
        $naara = User::query()->whereIn('id', $ids)->pluck('naara_id', 'id');
        $summary = $m->type === 'text' || $m->type === null ? (string) $m->body : ucfirst((string) $m->type);
        foreach ($ids as $uid) {
            $other = collect($ids)->first(fn ($i) => $i !== $uid);
            $peer = $other ? (string) ($naara[$other] ?? '') : '';
            if ($peer === '') {
                continue;
            }
            app(ConversationTimeline::class)->record($uid, $peer, 'n2n', 'message', $uid === (int) $m->sender_id ? 'out' : 'in', 'sent', $summary, 'n2n_message', (int) $m->id, $m->created_at);
        }
    }

    private static function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

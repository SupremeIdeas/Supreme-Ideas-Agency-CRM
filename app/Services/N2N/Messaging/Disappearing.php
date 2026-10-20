<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Attachment;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Media\MediaService;
use App\Services\N2N\Realtime\Realtime;
use App\Support\Auditor;

/**
 * Disappearing messages (N2N, MASTER-ONLY). Either member of a chat can set a timer; it applies to NEW messages only. A message past its time is invisible to both
 * people at once (Message::scopeForViewer), and n2n:expire-messages then deletes the row and its stored media for real. This removes messages from Naara's chat, it
 * cannot stop someone who already read a message from copying or photographing it, and the copy in a staff report (captured when a report is made) is kept.
 */
class Disappearing
{
    public const OPTIONS = [0 => 'Off', 86400 => '24 hours', 604800 => '7 days', 7776000 => '90 days'];

    public function __construct(private readonly MessageService $messages) {}

    public static function enabled(): bool
    {
        return (bool) Setting::getValue('n2n.disappearing_enabled', true);
    }

    public static function label(?int $seconds): string
    {
        return self::OPTIONS[(int) $seconds] ?? 'Off';
    }

    /** @throws MessagingException */
    public function set(User $user, int $conversationId, int $seconds): Conversation
    {
        if (! self::enabled()) {
            throw new MessagingException('invalid', 'Disappearing messages are not available right now.');
        }
        if (! array_key_exists($seconds, self::OPTIONS)) {
            throw new MessagingException('invalid', 'Choose Off, 24 hours, 7 days or 90 days.');
        }
        $c = $this->messages->mine($user, $conversationId);
        $c->forceFill(['disappear_after' => $seconds ?: null, 'disappear_by' => $seconds ? $user->id : null])->save();
        Auditor::log('n2n.disappearing_set', Conversation::class, $c->id, ['seconds' => $seconds, 'by' => $user->id]);
        $other = (int) Participant::query()->where('conversation_id', $c->id)->where('user_id', '!=', $user->id)->value('user_id');
        if ($other > 0) {
            Realtime::publish($other, 'conversation.updated', ['conversation_id' => $c->id]);
        }

        return $c;
    }

    /** When a message sent now into this chat expires, or null. */
    public static function expiryFor(Conversation $c): ?\Illuminate\Support\Carbon
    {
        return $c->disappear_after ? now()->addSeconds((int) $c->disappear_after) : null;
    }

    /** The choices offered by "Send and disappear after...", in seconds. */
    public const SEND_CHOICES = [3600, 86400, 604800];

    /** When one message expires: the chat's own timer, or the sender's per-message choice, whichever comes first. A choice that is not on the list is ignored. */
    public static function expiryForMessage(Conversation $c, ?int $after): ?\Illuminate\Support\Carbon
    {
        $chat = self::expiryFor($c);
        $own = $after !== null && self::enabled() && in_array($after, self::SEND_CHOICES, true) ? now()->addSeconds($after) : null;
        if ($chat !== null && $own !== null) {
            return $own->lt($chat) ? $own : $chat;
        }

        return $own ?? $chat;
    }

    /** Delete expired messages for good (rows, receipts/reactions/polls by cascade, and stored media). @return int how many */
    public function purge(int $limit = 500): int
    {
        $ids = Message::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('id')->limit(max(1, $limit))->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }
        $media = app(MediaService::class);
        foreach (Attachment::query()->whereIn('message_id', $ids)->where('status', '!=', 'deleted')->get() as $a) {
            $media->deleteObjects($a);
        }
        Attachment::query()->whereIn('message_id', $ids)->delete();
        Message::query()->whereIn('id', $ids)->delete();

        return $ids->count();
    }
}

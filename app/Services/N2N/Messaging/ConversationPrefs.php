<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Attachment;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\MessageHide;
use App\Models\N2N\Participant;
use App\Models\N2N\Star;
use App\Models\User;
use App\Services\N2N\Media\MediaService;
use App\Support\N2N\N2NSettings;
use Carbon\CarbonInterface;

/**
 * Per-member conversation preferences (N2N Batch 12, MASTER-ONLY): mute, archive, pin the chat, star and pin messages, search inside a
 * chat, and the chat theme. EVERYTHING here is private to the member who sets it; nothing is published to the other person, and every call
 * resolves the conversation through MessageService::mine() so "missing" and "not yours" look identical.
 */
class ConversationPrefs
{
    /** Mute lengths offered in the UI. `always` is a far-future date, so one column covers every case. */
    public const MUTE = ['8h' => 8, '1w' => 168, 'always' => null];

    public function __construct(private readonly MessageService $messages) {}

    public function participant(User $user, int $conversationId): Participant
    {
        $this->messages->mine($user, $conversationId);

        return Participant::query()->where('conversation_id', $conversationId)->where('user_id', $user->id)->firstOrFail();
    }

    // ---- mute / archive / pin chat -------------------------------------------------------------------------

    /** @param  string|null  $length  '8h' | '1w' | 'always' | null to unmute */
    public function mute(User $user, int $conversationId, ?string $length): Participant
    {
        $p = $this->participant($user, $conversationId);
        if ($length === null) {
            $p->forceFill(['muted_until' => null])->save();

            return $p;
        }
        if (! array_key_exists($length, self::MUTE)) {
            throw new MessagingException('invalid', 'Choose 8 hours, 1 week or always.');
        }
        $p->forceFill(['muted_until' => self::MUTE[$length] === null ? now()->addYears(100) : now()->addHours(self::MUTE[$length])])->save();

        return $p;
    }

    public static function isMuted(?Participant $p): bool
    {
        return $p !== null && $p->muted_until !== null && $p->muted_until->isFuture();
    }

    /** A private "mark as unread" flag (cleared when the chat is opened). */
    public function markUnread(User $user, int $conversationId, bool $on = true): Participant
    {
        $p = $this->participant($user, $conversationId);
        $p->forceFill(['marked_unread_at' => $on ? now() : null])->save();

        return $p;
    }

    public function archive(User $user, int $conversationId, bool $on = true): Participant
    {
        $p = $this->participant($user, $conversationId);
        $p->forceFill(['archived_at' => $on ? now() : null, 'pinned_at' => $on ? null : $p->pinned_at])->save();   // an archived chat is not also pinned

        return $p;
    }

    public function pinChat(User $user, int $conversationId, bool $on = true): Participant
    {
        $p = $this->participant($user, $conversationId);
        if ($on) {
            $max = N2NSettings::int('max_pinned_chats', 3);
            $already = Participant::query()->where('user_id', $user->id)->whereNotNull('pinned_at')->where('id', '!=', $p->id)->count();
            if ($p->pinned_at === null && $already >= $max) {
                throw new MessagingException('limit', "You can pin up to {$max} chats. Unpin one first.");
            }
            $p->forceFill(['pinned_at' => $p->pinned_at ?? now(), 'archived_at' => null])->save();
        } else {
            $p->forceFill(['pinned_at' => null])->save();
        }

        return $p;
    }

    // ---- private history controls ----------------------------------------------------------------------------
    // These only change what THIS member sees. The other person's chat is untouched and the stored rows stay (moderation, legal hold, erasure).

    /** "Clear chat": hide every message up to now from this member's view; the chat stays in their list, empty. */
    public function clearChat(User $user, int $conversationId, bool $remove = false): Participant
    {
        $p = $this->participant($user, $conversationId);
        $latest = (int) Message::query()->where('conversation_id', $conversationId)->max('id');
        $p->forceFill([
            'cleared_before_id' => max((int) $p->cleared_before_id, $latest), 'cleared_at' => now(), 'removed_at' => $remove ? now() : null,
            'last_read_message_id' => max((int) $p->last_read_message_id, $latest), 'marked_unread_at' => null, 'pinned_message_id' => null,
            'pinned_at' => $remove ? null : $p->pinned_at,
        ])->save();
        Star::query()->where('user_id', $user->id)->whereIn('message_id', Message::query()->where('conversation_id', $conversationId)->select('id'))->delete();

        return $p;
    }

    /** "Delete chat": clear it AND take it off the list until a newer message arrives. */
    public function deleteChat(User $user, int $conversationId): Participant
    {
        return $this->clearChat($user, $conversationId, true);
    }

    /** "Delete for me": hide one message from this member's own view. */
    public function hideMessage(User $user, int $messageId): void
    {
        $m = $this->visibleMessage($user, $messageId);
        MessageHide::query()->firstOrCreate(['user_id' => $user->id, 'message_id' => $m->id], ['created_at' => now()]);
        Star::query()->where('user_id', $user->id)->where('message_id', $m->id)->delete();
        Participant::query()->where('conversation_id', $m->conversation_id)->where('user_id', $user->id)->where('pinned_message_id', $m->id)->update(['pinned_message_id' => null]);
    }

    // ---- stars and the pinned message ----------------------------------------------------------------------

    /** The message, only if this member may see it in a chat they belong to. */
    public function visibleMessage(User $user, int $messageId): Message
    {
        $m = Message::query()->forViewer($user->id)->find($messageId);
        if ($m === null || ! $m->conversation?->isParticipant($user->id) || $m->deleted_for_all_at !== null) {
            throw new MessagingException('not_found', 'That message is not available.');
        }

        return $m;
    }

    public function star(User $user, int $messageId, bool $on = true): bool
    {
        $m = $this->visibleMessage($user, $messageId);
        if ($on) {
            Star::query()->firstOrCreate(['user_id' => $user->id, 'message_id' => $m->id], ['created_at' => now()]);
        } else {
            Star::query()->where('user_id', $user->id)->where('message_id', $m->id)->delete();
        }

        return $on;
    }

    /** @return list<int> */
    public function starredIds(User $user, int $conversationId): array
    {
        return Star::query()->where('user_id', $user->id)->whereIn('message_id', Message::query()->where('conversation_id', $conversationId)->select('id'))->pluck('message_id')->map(fn ($i) => (int) $i)->all();
    }

    /** @return list<array{id: int, mine: bool, body: string, at: CarbonInterface}> */
    public function starred(User $user, int $conversationId): array
    {
        $this->messages->mine($user, $conversationId);

        return Message::query()->where('conversation_id', $conversationId)->whereIn('id', Star::query()->where('user_id', $user->id)->select('message_id'))
            ->whereNull('deleted_for_all_at')->forViewer($user->id)->orderByDesc('id')->limit(200)->get()
            ->map(fn (Message $m) => ['id' => $m->id, 'mine' => (int) $m->sender_id === $user->id, 'body' => $this->label($m), 'at' => $m->created_at])->all();
    }

    public function pinMessage(User $user, int $conversationId, ?int $messageId): Participant
    {
        $p = $this->participant($user, $conversationId);
        if ($messageId !== null) {
            $m = $this->visibleMessage($user, $messageId);
            if ((int) $m->conversation_id !== $conversationId) {
                throw new MessagingException('not_found', 'That message is not in this chat.');
            }
        }
        $p->forceFill(['pinned_message_id' => $messageId])->save();

        return $p;
    }

    /** The member's pinned message, or null (also when it has since been deleted for everyone). @return array{id: int, mine: bool, body: string}|null */
    public function pinnedMessage(User $user, Participant $p): ?array
    {
        if ($p->pinned_message_id === null) {
            return null;
        }
        $m = Message::query()->where('id', $p->pinned_message_id)->where('conversation_id', $p->conversation_id)->whereNull('deleted_for_all_at')
            ->forViewer($user->id)->first();

        return $m === null ? null : ['id' => $m->id, 'mine' => (int) $m->sender_id === $user->id, 'body' => $this->label($m)];
    }

    // ---- search in a conversation --------------------------------------------------------------------------

    /** @return list<array{id: int, mine: bool, body: string, at: CarbonInterface}> newest first; only what this member may see */
    public function search(User $user, int $conversationId, string $q, int $limit = 50): array
    {
        $this->messages->mine($user, $conversationId);
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return Message::query()->where('conversation_id', $conversationId)->whereNull('deleted_for_all_at')->forViewer($user->id)
            ->whereRaw("body LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($q, 0, 100)).'%'])->orderByDesc('id')->limit(max(1, min(100, $limit)))->get()
            ->map(fn (Message $m) => ['id' => $m->id, 'mine' => (int) $m->sender_id === $user->id, 'body' => $this->label($m), 'at' => $m->created_at])->all();
    }

    private function label(Message $m): string
    {
        $body = trim((string) $m->body);

        return $body !== '' ? mb_substr($body, 0, 200) : ['image' => 'Photo', 'voice' => 'Voice message', 'video' => 'Video'][$m->type] ?? 'Message';
    }

    // ---- theme ---------------------------------------------------------------------------------------------

    /** @return array{accent: string, wallpaper: string, dim: int, photo_id: ?int} */
    public function theme(User $user, int $conversationId): array
    {
        return ChatThemes::normalise($this->participant($user, $conversationId)->theme_json);
    }

    /** Save the member's own theme for this chat. The other person's view is untouched. */
    public function setTheme(User $user, int $conversationId, array $in): array
    {
        $p = $this->participant($user, $conversationId);
        $current = ChatThemes::normalise($p->theme_json);
        $theme = ChatThemes::validate($in + $current);
        $theme['photo_id'] = $this->ownedPhotoId($user, $p, array_key_exists('photo_id', $in) ? $in['photo_id'] : $current['photo_id']);
        $p->forceFill(['theme_json' => $theme])->save();

        return $theme;
    }

    public function resetTheme(User $user, int $conversationId): void
    {
        $p = $this->participant($user, $conversationId);
        $this->dropPhoto($p);
        $p->forceFill(['theme_json' => null])->save();
    }

    /** A photo id is only kept if it is this member's own wallpaper upload for this chat. */
    private function ownedPhotoId(User $user, Participant $p, mixed $id): ?int
    {
        if ($id === null || (int) $id <= 0) {
            return null;
        }
        $a = Attachment::query()->where('id', (int) $id)->where('uploader_id', $user->id)->where('status', Attachment::ATTACHED)->whereNull('message_id')->first();

        return $a !== null && ($a->meta['purpose'] ?? null) === 'wallpaper' && (int) ($a->meta['conversation_id'] ?? 0) === (int) $p->conversation_id ? $a->id : null;
    }

    /** Store the member's own wallpaper photo for this chat (one per chat; a new one replaces the old). */
    public function setPhoto(User $user, int $conversationId, \Illuminate\Http\UploadedFile $file): array
    {
        $p = $this->participant($user, $conversationId);
        $att = app(MediaService::class)->stage($user, $file, 'image');
        if ($att->kind !== 'image') {
            app(MediaService::class)->deleteObjects($att);
            throw new MessagingException('type_not_allowed', 'A wallpaper has to be a photo.');
        }
        $att->forceFill(['status' => Attachment::ATTACHED, 'expires_at' => null, 'message_id' => null, 'meta' => ['purpose' => 'wallpaper', 'conversation_id' => $conversationId]])->save();
        $this->dropPhoto($p);
        $theme = ChatThemes::normalise($p->theme_json);
        $theme['photo_id'] = $att->id;
        $p->forceFill(['theme_json' => $theme])->save();

        return $theme;
    }

    public function removePhoto(User $user, int $conversationId): void
    {
        $p = $this->participant($user, $conversationId);
        $this->dropPhoto($p);
        $theme = ChatThemes::normalise($p->theme_json);
        $theme['photo_id'] = null;
        $p->forceFill(['theme_json' => $theme])->save();
    }

    private function dropPhoto(Participant $p): void
    {
        $old = ChatThemes::normalise($p->theme_json)['photo_id'];
        if ($old !== null && ($a = Attachment::query()->where('id', $old)->where('uploader_id', $p->user_id)->first()) !== null) {
            app(MediaService::class)->deleteObjects($a);
        }
    }

    /** Unmute-aware check used by the send pipeline: should this person be notified out loud? */
    public static function loud(Conversation $c, int $userId): bool
    {
        $p = Participant::query()->where('conversation_id', $c->id)->where('user_id', $userId)->first();

        return ! self::isMuted($p);
    }
}

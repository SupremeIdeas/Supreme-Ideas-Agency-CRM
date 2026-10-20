<?php

namespace App\Services\N2N\Messaging;

use App\Models\Contact;
use App\Models\MessageThread;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Models\User;

/**
 * The unified inbox (N2N, MASTER-ONLY): Naara conversations and Naara Line SMS threads behind ONE entry shape, with the screenshot filters
 * Unread / Known / Unknown / Naara / Line / Links / Requests and full-text search over the member's own conversations only.
 * The two models stay separate; only this DTO joins them.
 */
class Inbox
{
    public const SORTS = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'name' => 'Name A to Z', 'unread' => 'Unread first'];

    public const FILTERS = ['all', 'unread', 'known', 'unknown', 'naara', 'line', 'links', 'requests', 'images', 'videos', 'archived', 'locked'];

    /** @return list<array<string, mixed>> */
    public function entries(User $user, string $filter = 'all', string $q = '', string $sort = 'newest', bool $unlocked = false): array
    {
        $filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
        $entries = $this->naara($user, $filter, $q, $unlocked);
        if (in_array($filter, ['all', 'unread', 'line', 'unknown'], true)) {   // a Line thread is never "known", a request, or a Naara link
            $entries = array_merge($entries, $this->line($user, $filter, $q));
        }
        $sort = array_key_exists($sort, self::SORTS) ? $sort : 'newest';
        usort($entries, match ($sort) {   // pinned chats always lead
            'oldest' => fn ($a, $b) => [$b['pinned'], $a['at']] <=> [$a['pinned'], $b['at']],
            'name' => fn ($a, $b) => $b['pinned'] <=> $a['pinned'] ?: strcasecmp(ltrim((string) $a['title'], '@+'), ltrim((string) $b['title'], '@+')),
            'unread' => fn ($a, $b) => [$b['pinned'], (int) $b['unread'] > 0, $b['at']] <=> [$a['pinned'], (int) $a['unread'] > 0, $a['at']],
            default => fn ($a, $b) => [$b['pinned'], $b['at']] <=> [$a['pinned'], $a['at']],
        });

        return $entries;
    }

    /** @return list<array<string, mixed>> */
    private function naara(User $user, string $filter, string $q, bool $unlocked = false): array
    {
        if ($filter === 'line' || ($filter === 'locked' && ! $unlocked)) {
            return [];   // locked chats show only after the PIN is entered
        }
        $parts = Participant::query()->where('user_id', $user->id)->when($filter === 'locked', fn ($q) => $q->whereNotNull('locked_at'), fn ($q) => $q->whereNull('locked_at')->when($filter === 'archived', fn ($q2) => $q2->whereNotNull('archived_at'), fn ($q2) => $q2->whereNull('archived_at')))->get()->keyBy('conversation_id');
        if ($parts->isEmpty()) {
            return [];
        }
        $convs = Conversation::query()->whereIn('id', $parts->keys())->whereNotNull('last_message_id')->get();
        $matchIds = null;
        if (in_array($filter, ['images', 'videos'], true)) {
            $kind = $filter === 'images' ? 'image' : 'video';
            $matchIds = Message::query()->whereIn('conversation_id', $convs->pluck('id'))->forViewer($user->id)->where('type', $kind)->whereNull('deleted_for_all_at')->pluck('conversation_id')->unique()->all();
        }
        if ($filter === 'links' || trim($q) !== '') {
            $m = Message::query()->whereIn('conversation_id', $convs->pluck('id'))->forViewer($user->id);
            if ($filter === 'links') {
                $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
                $m->where('body', 'like', '%'.$this->like($host).'%');   // the Links filter shows Naara-issued links only
            }
            if (trim($q) !== '') {
                $m->where('body', 'like', '%'.$this->like(trim($q)).'%');
            }
            $matchIds = array_values(array_intersect($matchIds ?? $m->pluck('conversation_id')->unique()->all(), $m->pluck('conversation_id')->unique()->all()));
        }
        $others = Participant::query()->whereIn('conversation_id', $convs->pluck('id'))->where('user_id', '!=', $user->id)->with('user:id,name,username,naara_id,avatar')->get()->keyBy('conversation_id');
        $known = Contact::query()->where('user_id', $user->id)->whereNotNull('linked_user_id')->pluck('linked_user_id')->flip();

        $out = [];
        foreach ($convs as $c) {
            if ($matchIds !== null && ! in_array($c->id, $matchIds, true)) {
                continue;
            }
            $other = $others[$c->id]?->user;
            $isRequest = $c->status === Conversation::REQUEST && (int) $c->requested_by !== $user->id;
            $isKnown = $other !== null && isset($known[$other->id]);
            $unread = $this->unread($c->id, $user->id, (int) $parts[$c->id]->last_read_message_id);
            if ($parts[$c->id]->marked_unread_at !== null) {
                $unread = max($unread, 1);   // the member's own "mark as unread"
            }
            if (($filter === 'unread' && $unread === 0) || ($filter === 'known' && ! $isKnown) || ($filter === 'unknown' && $isKnown) || ($filter === 'requests' && ! $isRequest) || ($filter !== 'requests' && $isRequest && $filter !== 'all') || ($filter === 'archived' && $isRequest)) {
                continue;
            }
            // The newest message THIS person may see: a dropped (blocked / not-allowed) message is invisible to its would-be recipient.
            $last = Message::query()->where('conversation_id', $c->id)->forViewer($user->id)->orderByDesc('id')->first();
            if ($last === null) {
                // Cleared but not removed: the chat stays in the list, empty. "Delete chat" removes it until a newer message arrives.
                if (($parts[$c->id]->cleared_at !== null || $c->disappear_after) && $parts[$c->id]->removed_at === null && $filter !== 'requests' && ! $isRequest && trim($q) === '' && in_array($filter, ['all', 'naara', 'known', 'unknown', 'archived'], true)) {
                    $out[] = [
                        'kind' => 'naara', 'id' => $c->id, 'title' => $other?->name ?: ($other?->username ? '@'.$other->username : 'Naara member'), 'username' => $other?->username, 'naara_id' => $other?->naara_id,
                        'preview' => $parts[$c->id]->cleared_at !== null ? 'Chat cleared' : 'Messages disappeared', 'mine' => false, 'at' => ($parts[$c->id]->cleared_at ?? $c->last_message_at ?? now())->timestamp, 'unread' => 0, 'line' => 'naara', 'request' => false, 'known' => $isKnown, 'pinned' => $parts[$c->id]->pinned_at !== null ? 1 : 0,
                        'muted' => $parts[$c->id]->muted_until !== null && $parts[$c->id]->muted_until->isFuture(), 'archived' => $parts[$c->id]->archived_at !== null, 'marked' => false,
                    ];
                }
                continue;
            }
            $out[] = [
                'kind' => 'naara', 'id' => $c->id, 'title' => $other?->name ?: ($other?->username ? '@'.$other->username : 'Naara member'), 'username' => $other?->username,
                'naara_id' => $other?->naara_id, 'preview' => $last->deleted_for_all_at !== null ? 'Message deleted' : (trim((string) $last->body) !== '' ? mb_substr(\App\Support\N2N\ChatFormat::plain((string) $last->body), 0, 90) : ['image' => 'Photo', 'voice' => 'Voice message', 'video' => 'Video', 'audio' => 'Audio file', 'doc' => 'Document'][$last->type] ?? 'Message'), 'mine' => $last->sender_id === $user->id,
                'at' => $last->created_at?->timestamp ?? 0, 'unread' => $unread, 'line' => 'naara', 'request' => $isRequest, 'known' => $isKnown, 'pinned' => $parts[$c->id]->pinned_at !== null ? 1 : 0,
                'muted' => $parts[$c->id]->muted_until !== null && $parts[$c->id]->muted_until->isFuture(),
                'archived' => $parts[$c->id]->archived_at !== null, 'marked' => $parts[$c->id]->marked_unread_at !== null, 'locked' => $parts[$c->id]->locked_at !== null,
            ];
        }

        return $out;
    }

    /** Naara Line SMS threads, read-only here: replying still happens in the existing Messages screen. */
    private function line(User $user, string $filter, string $q): array
    {
        $threads = MessageThread::query()->where('user_id', $user->id)->orderByDesc('last_at')->limit(100)->get();
        $out = [];
        foreach ($threads as $t) {
            if ($filter === 'unread' && (int) $t->unread_count === 0) {
                continue;
            }
            if (trim($q) !== '' && ! str_contains(mb_strtolower($t->last_body.' '.$t->counterpart_number), mb_strtolower(trim($q)))) {
                continue;
            }
            $out[] = [
                'kind' => 'line', 'id' => $t->id, 'title' => $t->counterpart_number, 'username' => null, 'naara_id' => null, 'preview' => mb_substr((string) $t->last_body, 0, 90),
                'mine' => $t->last_direction === 'out', 'at' => $t->last_at?->timestamp ?? 0, 'unread' => (int) $t->unread_count, 'line' => 'line', 'request' => false, 'known' => false, 'pinned' => 0, 'muted' => false,
            ];
        }

        return $out;
    }

    public function unread(int $conversationId, int $userId, int $lastRead): int
    {
        return Message::query()->where('conversation_id', $conversationId)->where('id', '>', $lastRead)->where('sender_id', '!=', $userId)->forViewer($userId)->count();
    }

    public function totalUnread(User $user): int
    {
        $n = 0;
        foreach (Participant::query()->where('user_id', $user->id)->whereNull('archived_at')->whereNull('locked_at')->get() as $p) {
            $n += $this->unread($p->conversation_id, $user->id, (int) $p->last_read_message_id);
        }

        return $n;
    }

    private function like(string $s): string
    {
        return addcslashes($s, '%_\\');
    }
}

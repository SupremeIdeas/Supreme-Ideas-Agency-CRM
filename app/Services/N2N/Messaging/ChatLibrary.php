<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Attachment;
use App\Models\N2N\AttachmentHide;
use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Models\User;
use App\Services\N2N\Media\MediaService;

/**
 * Media gallery and storage manager for ONE chat (N2N Batch 12, MASTER-ONLY), built from the Batch 7 attachment rows. N2N carries photos,
 * videos and voice notes only (no general documents), and no general links exist, so the Links tab lists only Naara-issued links.
 * "Delete for me" hides an item from this member's own view; the object itself is removed only when EVERY participant has removed it, and
 * the copy never promises that this lowers a bill (object stores can bill a minimum retention period).
 */
class ChatLibrary
{
    public function __construct(private readonly MessageService $messages) {}

    /** Attachments of this chat that this member can see (not deleted, not hidden by them, message visible to them). */
    private function visible(User $user, int $conversationId)
    {
        $this->messages->mine($user, $conversationId);

        return Attachment::query()->where('status', Attachment::ATTACHED)
            ->whereIn('message_id', Message::query()->where('conversation_id', $conversationId)->whereNull('deleted_for_all_at')
                ->forViewer($user->id)->select('id'))
            ->whereNotIn('id', AttachmentHide::query()->where('user_id', $user->id)->select('attachment_id'));
    }

    /** @return list<array{id: int, kind: string, bytes: int, seconds: ?int, mine: bool, at: ?\Carbon\CarbonInterface}> */
    public function items(User $user, int $conversationId, string $tab = 'media', int $limit = 120): array
    {
        $kinds = match ($tab) {
            'voice' => ['voice', 'audio'],
            'docs' => ['doc'],
            default => ['image', 'video'],
        };
        $rows = $this->visible($user, $conversationId)->whereIn('kind', $kinds)->with('message:id,sender_id,created_at')->orderByDesc('id')->limit(max(1, min(300, $limit)))->get();

        return $rows->map(fn (Attachment $a) => [
            'id' => $a->id, 'kind' => $a->kind, 'name' => $a->meta['name'] ?? null, 'bytes' => (int) $a->bytes, 'seconds' => $a->duration_s, 'mine' => (int) $a->message?->sender_id === $user->id, 'at' => $a->message?->created_at,
        ])->all();
    }

    /** Naara-issued links in this chat (this host only), newest first. @return list<array{id: int, url: string, at: ?\Carbon\CarbonInterface}> */
    public function links(User $user, int $conversationId, int $limit = 60): array
    {
        $this->messages->mine($user, $conversationId);
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($host === '') {
            return [];
        }
        $out = [];
        $rows = Message::query()->where('conversation_id', $conversationId)->whereNull('deleted_for_all_at')->forViewer($user->id)
            ->whereRaw("body LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $host).'%'])->orderByDesc('id')->limit(200)->get();
        foreach ($rows as $m) {
            if (preg_match_all('~https?://[^\s<>"\')\]]+~i', (string) $m->body, $found)) {
                foreach ($found[0] as $u) {
                    if (strcasecmp((string) parse_url($u, PHP_URL_HOST), $host) === 0) {
                        $out[] = ['id' => $m->id, 'url' => $u, 'at' => $m->created_at];
                    }
                }
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return array_slice($out, 0, $limit);
    }

    /** @return array{count: int, bytes: int, by_kind: array<string, array{count: int, bytes: int}>} what THIS member can see of the chat's media */
    public function usage(User $user, int $conversationId): array
    {
        $by = [];
        $total = ['count' => 0, 'bytes' => 0];
        foreach ($this->visible($user, $conversationId)->get(['kind', 'bytes']) as $a) {
            $by[$a->kind]['count'] = ($by[$a->kind]['count'] ?? 0) + 1;
            $by[$a->kind]['bytes'] = ($by[$a->kind]['bytes'] ?? 0) + (int) $a->bytes;
            $total['count']++;
            $total['bytes'] += (int) $a->bytes;
        }

        return $total + ['by_kind' => $by];
    }

    /**
     * Hide the chosen items from this member's view. Returns how many were hidden. An item that EVERY participant of the chat has now
     * hidden is deleted from storage; otherwise the other person keeps seeing (and keeping) their copy.
     *
     * @param  list<int>  $attachmentIds
     */
    public function hideForMe(User $user, int $conversationId, array $attachmentIds): int
    {
        $ids = $this->visible($user, $conversationId)->whereIn('id', array_map('intval', $attachmentIds))->pluck('id');
        foreach ($ids as $id) {
            AttachmentHide::query()->firstOrCreate(['user_id' => $user->id, 'attachment_id' => $id], ['created_at' => now()]);
        }
        $members = Participant::query()->where('conversation_id', $conversationId)->pluck('user_id');
        foreach (Attachment::query()->whereIn('id', $ids)->get() as $a) {
            $hiddenBy = AttachmentHide::query()->where('attachment_id', $a->id)->whereIn('user_id', $members)->count();
            if ($hiddenBy >= $members->count()) {
                app(MediaService::class)->deleteObjects($a);
            }
        }

        return $ids->count();
    }

    /** @return list<int> attachment ids this member has hidden in this chat (so the thread view can skip them) */
    public function hiddenIds(User $user, int $conversationId): array
    {
        return AttachmentHide::query()->where('user_id', $user->id)->whereIn('attachment_id', Attachment::query()->whereIn('message_id', Message::query()->where('conversation_id', $conversationId)->select('id'))->select('id'))
            ->pluck('attachment_id')->map(fn ($i) => (int) $i)->all();
    }

    public static function human(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB';
    }
}

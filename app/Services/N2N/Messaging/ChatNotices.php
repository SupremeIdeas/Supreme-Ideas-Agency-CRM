<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\ChatNotice;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Admin-editable chat notices (N2N, MASTER-ONLY) with an honesty guard: a notice may not claim end-to-end encryption
 * unless the platform really has it (`n2n.e2ee_enabled`, false because messages are encrypted in transit and at rest, not end to end).
 */
class ChatNotices
{
    private const E2E_PATTERNS = [
        '/\bend[\s\-]*to[\s\-]*end\b/i', '/\be2e(e)?\b/i', '/\bzero[\s\-]*knowledge\b/i',
        '/\b(only|just)\s+(you|you and \w+)\s+(can|could)\s+(read|see)\b/i', '/\bnobody\s+(else\s+)?(can|could)\s+(read|see)\b/i',
        '/\bnot even (us|naara|we)\b/i', '/\bunreadable by (us|anyone)\b/i',
    ];

    public static function e2eeEnabled(): bool
    {
        return (bool) Setting::getValue('n2n.e2ee_enabled', false);
    }

    /** @return list<string> reasons the text is refused (empty = fine) */
    public function problems(string $title, string $body): array
    {
        $out = [];
        if (! self::e2eeEnabled()) {
            foreach (self::E2E_PATTERNS as $re) {
                if (preg_match($re, $title.' '.$body)) {
                    $out[] = 'This claims end-to-end privacy. Naara chat is encrypted in transit and at rest but is not end-to-end encrypted, so that wording is blocked. Say what is true (for example "encrypted in transit and at rest").';
                    break;
                }
            }
        }

        return $out;
    }

    /** Live notices this member has not dismissed; each is marked seen once. */
    public function forMember(int $userId)
    {
        $notices = ChatNotice::query()->live()->orderBy('id')->get();
        if ($notices->isEmpty()) {
            return $notices;
        }
        $state = DB::table('n2n_chat_notice_seen')->where('user_id', $userId)->whereIn('notice_id', $notices->pluck('id'))->get()->keyBy('notice_id');
        $show = $notices->filter(fn ($n) => ! ($n->dismissible && ($state[$n->id]->dismissed_at ?? null)))->values();
        foreach ($show as $n) {
            if (! isset($state[$n->id])) {
                DB::table('n2n_chat_notice_seen')->insertOrIgnore(['notice_id' => $n->id, 'user_id' => $userId, 'seen_at' => now()]);
            }
        }

        return $show;
    }

    public function dismiss(int $userId, int $noticeId): void
    {
        $n = ChatNotice::query()->live()->where('dismissible', true)->find($noticeId);
        if (! $n) {
            return;
        }
        DB::table('n2n_chat_notice_seen')->updateOrInsert(['notice_id' => $n->id, 'user_id' => $userId], ['dismissed_at' => now()]);
    }

    /** @return array{seen:int,dismissed:int} */
    public function stats(int $noticeId): array
    {
        $q = DB::table('n2n_chat_notice_seen')->where('notice_id', $noticeId);

        return ['seen' => (clone $q)->count(), 'dismissed' => (clone $q)->whereNotNull('dismissed_at')->count()];
    }
}

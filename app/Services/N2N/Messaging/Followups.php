<?php

namespace App\Services\N2N\Messaging;

use App\Jobs\SendWebPushJob;
use App\Models\N2N\Followup;
use App\Models\N2N\Participant;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Realtime\Realtime;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Follow-ups (Naara Planner Phase C, MASTER-ONLY): "remind me to call John". A deliberately tiny, strictly private list:
 *  - only the owner can see, change or delete a row; the person it is about is never told and cannot find out;
 *  - it can be about someone the member has a chat with (so it cannot be used to pin a private note on an arbitrary account);
 *  - one reminder per row, claimed with a compare-and-set before it is sent (exactly once); a done row is never reminded;
 *  - capped (100 open) and erased with the member.
 */
class Followups
{
    public const MAX_OPEN = 100;

    /** @throws MessagingException */
    public function create(User $user, string $title, ?CarbonInterface $due = null, ?int $conversationId = null): Followup
    {
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags($title)));
        if ($title === '' || mb_strlen($title) > 120) {
            throw new MessagingException('invalid', 'Write what to follow up on (up to 120 characters).');
        }
        if ($due !== null && ($due->lt(now()->addMinutes(1)) || $due->gt(now()->addYear()))) {
            throw new MessagingException('invalid', 'Pick a reminder time from one minute to one year from now.');
        }
        if (Followup::query()->where('user_id', $user->id)->whereNull('done_at')->count() >= self::MAX_OPEN) {
            throw new MessagingException('invalid', 'You have '.self::MAX_OPEN.' open follow-ups. Finish or delete one first.');
        }
        $about = null;
        if ($conversationId !== null) {
            if (! Participant::query()->where('conversation_id', $conversationId)->where('user_id', $user->id)->exists()) {
                throw new MessagingException('not_found', 'That chat could not be found.');
            }
            $about = (int) Participant::query()->where('conversation_id', $conversationId)->where('user_id', '!=', $user->id)->value('user_id') ?: null;
        }

        return Followup::query()->create(['user_id' => $user->id, 'about_user_id' => $about, 'conversation_id' => $conversationId, 'title' => $title, 'due_at' => $due]);
    }

    /** @return \Illuminate\Support\Collection<int, Followup> open first (due soonest, then undated), then recently done */
    public function list(User $user, int $limit = 100): \Illuminate\Support\Collection
    {
        $open = Followup::query()->with('about:id,name,username')->where('user_id', $user->id)->whereNull('done_at')->orderByRaw('due_at is null')->orderBy('due_at')->orderByDesc('starred')->limit($limit)->get();
        $done = Followup::query()->with('about:id,name,username')->where('user_id', $user->id)->whereNotNull('done_at')->where('done_at', '>', now()->subDays(7))->orderByDesc('done_at')->limit(20)->get();

        return $open->concat($done);
    }

    public function toggleDone(User $user, int $id): bool
    {
        $f = $this->mine($user, $id);
        $f->forceFill(['done_at' => $f->done_at === null ? now() : null])->save();

        return $f->done_at !== null;
    }

    public function toggleStar(User $user, int $id): bool
    {
        $f = $this->mine($user, $id);
        $f->forceFill(['starred' => ! $f->starred])->save();

        return $f->starred;
    }

    public function delete(User $user, int $id): void
    {
        $this->mine($user, $id)->delete();
    }

    /** Re-time a row; the reminder re-arms. @throws MessagingException */
    public function snooze(User $user, int $id, CarbonInterface $due): void
    {
        if ($due->lt(now()->addMinutes(1)) || $due->gt(now()->addYear())) {
            throw new MessagingException('invalid', 'Pick a reminder time from one minute to one year from now.');
        }
        $this->mine($user, $id)->forceFill(['due_at' => $due, 'reminded_at' => null])->save();
    }

    /** One reminder per due row, exactly once. @return int how many were sent */
    public function remindDue(int $limit = 500): int
    {
        $sent = 0;
        $rows = Followup::query()->whereNull('done_at')->whereNull('reminded_at')->whereNotNull('due_at')->where('due_at', '<=', now())->where('due_at', '>', now()->subDay())->orderBy('due_at')->limit($limit)->get();
        foreach ($rows as $f) {
            if (Followup::query()->whereKey($f->id)->whereNull('reminded_at')->whereNull('done_at')->update(['reminded_at' => now()]) !== 1) {
                continue;
            }
            Realtime::publish((int) $f->user_id, 'followup.due', ['followup_id' => $f->id, 'conversation_id' => $f->conversation_id, 'tone' => 'message.naara']);
            $preview = (bool) Setting::getValue('n2n.push_preview', false);
            SendWebPushJob::dispatch((int) $f->user_id, [
                'title' => 'Follow-up reminder',
                'body' => $preview ? mb_substr((string) $f->title, 0, 80) : 'You have a follow-up due on Naara.',
                'url' => url('/n2n/planner'),
            ]);
            $sent++;
        }

        return $sent;
    }

    public static function eraseFor(User $user): void
    {
        Followup::query()->where('user_id', $user->id)->delete();
        Followup::query()->where('about_user_id', $user->id)->update(['about_user_id' => null]);   // others' private notes lose the link to an erased person
    }

    /** @throws MessagingException */
    private function mine(User $user, int $id): Followup
    {
        $f = Followup::query()->where('user_id', $user->id)->find($id);
        if ($f === null) {
            throw new MessagingException('not_found', 'That follow-up is not available.');
        }

        return $f;
    }
}

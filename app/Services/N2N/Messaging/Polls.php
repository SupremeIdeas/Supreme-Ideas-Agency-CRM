<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Block;
use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Models\N2N\Poll;
use App\Models\N2N\PollOption;
use App\Models\N2N\PollVote;
use App\Models\User;
use App\Services\N2N\Realtime\Realtime;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Polls in a Naara chat (N2N, MASTER-ONLY). A poll is a message of type `poll` (the question is its body, so every preview and export already works); options
 * and votes are separate rows. Both members of the chat can vote, the creator can close it, results are visible to the two of them only, and every vote starts
 * from ConversationPrefs::visibleMessage (member of the chat, not deleted, not cleared). Question and options go through the same link filter as any message.
 */
class Polls
{
    public const MAX_OPTIONS = 6;

    public function __construct(private readonly ConversationPrefs $prefs) {}

    /**
     * @param  array{question?: mixed, label?: mixed, options?: mixed, multiple?: mixed}  $raw
     * @return array{question: string, options: list<string>, multiple: bool}
     *
     * @throws MessagingException
     */
    public static function clean(array $raw): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($raw['question'] ?? $raw['label'] ?? ''))));
        $options = [];
        foreach ((array) ($raw['options'] ?? []) as $o) {
            $o = trim(preg_replace('/\s+/u', ' ', strip_tags((string) (is_array($o) ? ($o['text'] ?? '') : $o))));
            if ($o !== '' && ! in_array(mb_strtolower($o), array_map('mb_strtolower', $options), true)) {
                $options[] = mb_substr($o, 0, 80);
            }
        }
        if ($q === '' || mb_strlen($q) > 160 || count($options) < 2) {
            throw new MessagingException('invalid', 'A poll needs a question (up to 160 characters) and at least two different options.');
        }
        $options = array_slice($options, 0, self::MAX_OPTIONS);
        foreach (array_merge([$q], $options) as $text) {
            if (($why = LinkFilter::problem($text)) !== null) {
                throw new MessagingException('link_blocked', $why);
            }
        }

        return ['question' => $q, 'options' => $options, 'multiple' => ! empty($raw['multiple'])];
    }

    /** Called inside the send transaction, once the message row exists. */
    public function attach(Message $message, array $clean): Poll
    {
        $poll = Poll::query()->create(['message_id' => $message->id, 'question' => $clean['question'], 'multiple' => $clean['multiple']]);
        foreach ($clean['options'] as $i => $text) {
            PollOption::query()->create(['poll_id' => $poll->id, 'position' => $i, 'text' => $text]);
        }

        return $poll;
    }

    /**
     * Toggle the member's vote for one option. Single-choice polls move the vote; multiple-choice polls add or remove that option. Returns the member's chosen option ids.
     *
     * @return list<int>
     *
     * @throws MessagingException
     */
    public function toggle(User $user, int $pollId, int $optionId): array
    {
        $poll = Poll::query()->find($pollId);
        $option = $poll ? PollOption::query()->where('poll_id', $poll->id)->find($optionId) : null;
        if ($poll === null || $option === null) {
            throw new MessagingException('not_found', 'That poll is not available.');
        }
        $m = $this->prefs->visibleMessage($user, (int) $poll->message_id);
        if ($poll->closed_at !== null) {
            throw new MessagingException('invalid', 'This poll is closed.');
        }
        if (RateLimiter::tooManyAttempts('n2n-vote:'.$user->id, 60)) {
            throw new MessagingException('rate_limited', 'Slow down a little.');
        }
        RateLimiter::hit('n2n-vote:'.$user->id, 60);
        $other = (int) Participant::query()->where('conversation_id', $m->conversation_id)->where('user_id', '!=', $user->id)->value('user_id');
        if ($other > 0 && Block::query()->where(fn ($q) => $q->where('blocker_id', $other)->where('blocked_id', $user->id))->orWhere(fn ($q) => $q->where('blocker_id', $user->id)->where('blocked_id', $other))->exists()) {
            return [];   // a blocked pair: silent no-op, same as reactions
        }
        $mine = PollVote::query()->where('poll_id', $poll->id)->where('user_id', $user->id);
        $has = (clone $mine)->where('option_id', $option->id)->exists();
        if ($has) {
            (clone $mine)->where('option_id', $option->id)->delete();
        } else {
            if (! $poll->multiple) {
                (clone $mine)->delete();
            }
            PollVote::query()->create(['poll_id' => $poll->id, 'option_id' => $option->id, 'user_id' => $user->id, 'created_at' => now()]);
        }
        if ($other > 0) {
            Realtime::publish($other, 'message.poll', ['conversation_id' => $m->conversation_id, 'message_id' => $m->id]);
        }

        return PollVote::query()->where('poll_id', $poll->id)->where('user_id', $user->id)->pluck('option_id')->map(fn ($i) => (int) $i)->all();
    }

    /** Only the person who asked can close a poll. */
    public function close(User $user, int $pollId): void
    {
        $poll = Poll::query()->find($pollId);
        $m = $poll ? $this->prefs->visibleMessage($user, (int) $poll->message_id) : null;
        if ($poll === null || $m === null || (int) $m->sender_id !== $user->id) {
            throw new MessagingException('not_participant', 'Only the person who asked can close this poll.');
        }
        $poll->forceFill(['closed_at' => $poll->closed_at ?? now()])->save();
        $other = (int) Participant::query()->where('conversation_id', $m->conversation_id)->where('user_id', '!=', $user->id)->value('user_id');
        if ($other > 0) {
            Realtime::publish($other, 'message.poll', ['conversation_id' => $m->conversation_id, 'message_id' => $m->id]);
        }
    }

    /**
     * @param  list<int>  $messageIds
     * @return array<int, array{id: int, question: string, multiple: bool, closed: bool, total: int, voters: int, options: list<array{id: int, text: string, votes: int, pct: int, mine: bool}>}>
     */
    public function forMessages(User $user, array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }
        $polls = Poll::query()->whereIn('message_id', $messageIds)->get();
        if ($polls->isEmpty()) {
            return [];
        }
        $options = PollOption::query()->whereIn('poll_id', $polls->pluck('id'))->orderBy('position')->get()->groupBy('poll_id');
        $votes = PollVote::query()->whereIn('poll_id', $polls->pluck('id'))->get()->groupBy('poll_id');
        $out = [];
        foreach ($polls as $p) {
            $v = $votes[$p->id] ?? collect();
            $total = $v->count();
            $out[$p->message_id] = [
                'id' => $p->id, 'question' => $p->question, 'multiple' => $p->multiple, 'closed' => $p->closed_at !== null, 'total' => $total, 'voters' => $v->pluck('user_id')->unique()->count(),
                'options' => ($options[$p->id] ?? collect())->map(function ($o) use ($v, $total, $user) {
                    $n = $v->where('option_id', $o->id)->count();

                    return ['id' => $o->id, 'text' => $o->text, 'votes' => $n, 'pct' => $total > 0 ? (int) round($n * 100 / $total) : 0, 'mine' => $v->where('option_id', $o->id)->where('user_id', $user->id)->isNotEmpty()];
                })->values()->all(),
            ];
        }

        return $out;
    }
}

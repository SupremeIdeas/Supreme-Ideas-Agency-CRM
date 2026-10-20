<?php

namespace App\Services\Brain;

use App\Models\Brain\BrainMessage;
use App\Models\Brain\BrainThread;
use App\Services\AI\AiUsage;
use App\Services\AI\ClaudeGateway;
use App\Models\User;
use App\Support\Brain\BrainAccess;
use App\Support\Brain\BrainSettings;
use Throwable;

/**
 * Answers one NaaraBrain question (MASTER-ONLY). Runs inside BrainReplyJob (every external call is a queued job). Sprint 1 shell scope: a plain, tool-less
 * conversation through the one ClaudeGateway, so every call is measured in ai_usage_log as feature "brain_chat". It cannot see or touch the person's account
 * yet; the system prompt says so honestly. Tools (read-only first) arrive with the Sprint 1 runtime, behind the isolation tests.
 */
class BrainResponder
{
    public function __construct(private ClaudeGateway $gateway) {}

    public function answer(BrainMessage $pending): void
    {
        $thread = BrainThread::query()->ownedBy($pending->user_id)->find($pending->thread_id);
        if ($thread === null) {
            $pending->update(['status' => 'error', 'body' => __('brain.reply_failed')]);

            return;
        }
        // The depth runs at what the member's plan allows today (a lapsed plan quietly falls back to a lighter model).
        $owner = User::query()->find($pending->user_id);
        $depth = $owner !== null ? BrainAccess::effectiveDepth($owner, $thread->depth) : BrainSettings::depth($thread->depth);
        $class = (string) (BrainSettings::depths()[$depth]['class'] ?? 'standard');
        $model = $this->gateway->model($class);

        $turns = $thread->messages()->where('status', 'done')->where('id', '<', $pending->id)->orderByDesc('id')->limit(BrainSettings::int('history_turns'))->get()->reverse()->values();
        $messages = [];
        foreach ($turns as $t) {
            // The API needs strictly alternating roles starting with the user: merge neighbours of the same role, drop a leading assistant turn.
            if ($messages !== [] && $messages[array_key_last($messages)]['role'] === $t->role) {
                $messages[array_key_last($messages)]['content'] .= "\n\n".$t->body;
            } elseif ($messages !== [] || $t->role === 'user') {
                $messages[] = ['role' => $t->role, 'content' => $t->body];
            }
        }
        if ($messages === [] || $messages[array_key_last($messages)]['role'] !== 'user') {
            $pending->update(['status' => 'error', 'body' => __('brain.reply_failed')]);

            return;
        }

        try {
            $body = AiUsage::as('brain_chat', (int) $pending->user_id, fn () => $this->gateway->messages([
                'model' => $model, 'max_tokens' => BrainSettings::int('max_tokens', 64), 'system' => $this->system($thread), 'messages' => $messages,
            ]));
            $text = trim(ClaudeGateway::text($body));
            $pending->update($text === '' ? ['status' => 'error', 'body' => __('brain.reply_failed')] : ['status' => 'done', 'body' => $text, 'model' => (string) ($body['model'] ?? $model)]);
        } catch (Throwable) {
            $pending->update(['status' => 'error', 'body' => __('brain.reply_failed')]);
        }
        $thread->forceFill(['last_message_at' => now()])->save();
    }

    private function system(BrainThread $thread): string
    {
        $length = match ($thread->length) {
            'concise' => 'Keep answers short: a few sentences unless the person asks for more.',
            'detailed' => 'Give thorough, well-structured answers with the reasoning shown.',
            default => 'Match the length of the answer to the question.',
        };

        return implode("\n", [
            'You are NaaraBrain, the assistant inside NaaraSim, a Pan-African travel-connectivity app that sells eSIM data plans, virtual phone numbers and SMS verification numbers.',
            'Be direct, warm and practical. Plain language, no filler.',
            $length,
            'You are an AI model. Say so if asked. Never claim to be human.',
            'You cannot see or change anything in the person\'s NaaraSim account yet: no balances, orders, numbers, messages or settings, and you cannot buy, send, call or delete anything. If asked, say that plainly and explain where in the app they can do it themselves.',
            'Never state prices, stock, coverage or delivery times for NaaraSim products as fact; point the person to the live screen in the app instead.',
            'Never claim messages are end-to-end encrypted or private from NaaraSim, and never promise outcomes you cannot guarantee.',
        ]);
    }
}

<?php

namespace App\Services\Omni\Flows;

use App\Jobs\Omni\ContinueOmniFlow;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniFlow;
use App\Models\Omni\OmniFlowRun;
use App\Models\Omni\OmniMessage;
use App\Models\Omni\OmniNote;
use App\Services\Omni\Outbox;
use App\Services\Omni\WindowClosedException;

/**
 * Respond flows (MASTER-ONLY, blueprint section 7): a trigger plus a short list of steps. Triggers: new_conversation, keyword, outside_hours. Steps: send_text,
 * delay, set_status, add_tag, note. A flow must validate before it can go live; test mode runs the same steps without sending or changing anything and returns
 * a trace. Loop protection: a flow runs at most once per conversation per 24 hours and a conversation starts at most 3 flow runs per hour. Flows only ever react
 * to a customer message, never to our own.
 */
class FlowEngine
{
    public const STEP_TYPES = ['send_text', 'delay', 'set_status', 'add_tag', 'note'];

    public const TRIGGERS = ['new_conversation', 'keyword', 'outside_hours'];

    public const MAX_STEPS = 12;

    public function __construct(private readonly Outbox $outbox)
    {
    }

    /** @return list<string> plain-language problems; an empty list means the flow may go live */
    public function validate(string $trigger, ?string $value, array $steps): array
    {
        $e = [];
        if (! in_array($trigger, self::TRIGGERS, true)) {
            $e[] = 'Pick when this flow should start.';
        }
        if ($trigger === 'keyword' && trim((string) $value) === '') {
            $e[] = 'Add at least one keyword, separated by commas.';
        }
        if ($trigger === 'outside_hours' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d-([01]\d|2[0-3]):[0-5]\d$/', trim((string) $value))) {
            $e[] = 'Write opening hours like 09:00-17:00.';
        }
        if ($steps === []) {
            $e[] = 'Add at least one step.';
        }
        if (count($steps) > self::MAX_STEPS) {
            $e[] = 'A flow can have up to '.self::MAX_STEPS.' steps.';
        }
        foreach (array_values($steps) as $i => $s) {
            $n = $i + 1;
            $type = $s['type'] ?? '';
            if (! in_array($type, self::STEP_TYPES, true)) {
                $e[] = "Step $n has an unknown type.";

                continue;
            }
            match ($type) {
                'send_text', 'note' => trim((string) ($s['text'] ?? '')) === '' || mb_strlen((string) $s['text']) > 1000 ? $e[] = "Step $n needs some text (up to 1,000 characters)." : null,
                'delay' => ((int) ($s['minutes'] ?? 0) < 1 || (int) $s['minutes'] > 1440) ? $e[] = "Step $n: wait between 1 and 1,440 minutes." : null,
                'set_status' => ! in_array($s['status'] ?? '', ['open', 'pending', 'resolved'], true) ? $e[] = "Step $n: pick open, waiting or done." : null,
                'add_tag' => preg_match('/^[\p{L}\p{N} _-]{1,30}$/u', (string) ($s['tag'] ?? '')) ? null : $e[] = "Step $n: a tag is 1 to 30 letters or numbers.",
            };
        }

        return $e;
    }

    /** Does this flow start for this customer message? */
    public function matches(OmniFlow $flow, OmniMessage $m, OmniConversation $c): bool
    {
        return match ($flow->trigger_type) {
            'new_conversation' => OmniMessage::query()->forWorkspace((int) $c->workspace_id)->where('conversation_id', $c->id)->where('direction', 'in')->count() === 1,
            'keyword' => $this->hasKeyword((string) $flow->trigger_value, (string) $m->body),
            'outside_hours' => $this->outsideHours((string) $flow->trigger_value),
            default => false,
        };
    }

    private function hasKeyword(string $list, string $text): bool
    {
        $text = mb_strtolower($text);
        foreach (array_filter(array_map('trim', explode(',', mb_strtolower($list)))) as $k) {
            if ($k !== '' && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($k, '/').'(?![\p{L}\p{N}])/u', $text)) {
                return true;
            }
        }

        return false;
    }

    private function outsideHours(string $range, ?\DateTimeInterface $at = null): bool
    {
        if (! preg_match('/^(\d\d):(\d\d)-(\d\d):(\d\d)$/', $range, $m)) {
            return false;
        }
        $now = ($at ? \Illuminate\Support\Carbon::instance($at) : now())->format('H:i');
        $from = $m[1].':'.$m[2];
        $to = $m[3].':'.$m[4];

        return $from <= $to ? ($now < $from || $now >= $to) : ($now >= $to && $now < $from);
    }

    /** Called for each customer message. Returns the run when a flow took it (so the AI agent leaves that message alone). */
    public function handleInbound(OmniMessage $m): ?OmniFlowRun
    {
        $ws = (int) $m->workspace_id;
        $c = OmniConversation::query()->forWorkspace($ws)->find($m->conversation_id);
        if (! $c || $m->direction !== 'in') {
            return null;
        }
        if (OmniFlowRun::query()->forWorkspace($ws)->where('conversation_id', $c->id)->where('is_test', false)->where('created_at', '>=', now()->subHour())->count() >= 3) {
            return null;   // loop protection: a runaway rule cannot keep a conversation busy
        }
        foreach (OmniFlow::query()->forWorkspace($ws)->where('status', 'live')->orderBy('id')->get() as $flow) {
            if (OmniFlowRun::query()->forWorkspace($ws)->where('flow_id', $flow->id)->where('conversation_id', $c->id)->where('is_test', false)->where('created_at', '>=', now()->subDay())->exists()) {
                continue;   // once per conversation per day
            }
            if ($this->matches($flow, $m, $c)) {
                $flow->increment('runs_count');
                $run = OmniFlowRun::create(['workspace_id' => $ws, 'flow_id' => $flow->id, 'conversation_id' => $c->id, 'is_test' => false, 'status' => 'running', 'trace' => []]);
                $this->execute($flow, $run, 0);

                return $run->fresh();
            }
        }

        return null;
    }

    /** Test mode: the same steps, nothing sent, nothing changed. Returns the trace. */
    public function test(OmniFlow $flow, string $sampleText = 'Hello'): array
    {
        $trace = [];
        foreach ((array) $flow->steps as $i => $s) {
            $trace[] = ['step' => $i + 1, 'type' => $s['type'] ?? '?', 'result' => match ($s['type'] ?? '') {
                'send_text' => 'Would send: '.$this->fill((string) ($s['text'] ?? ''), 'Customer'),
                'delay' => 'Would wait '.(int) ($s['minutes'] ?? 0).' minutes',
                'set_status' => 'Would set the conversation to '.($s['status'] ?? '?'),
                'add_tag' => 'Would tag the contact "'.($s['tag'] ?? '').'"',
                'note' => 'Would add a private note',
                default => 'Unknown step',
            }];
        }

        return $trace;
    }

    public function execute(OmniFlow $flow, OmniFlowRun $run, int $from): void
    {
        $ws = (int) $run->workspace_id;
        $c = OmniConversation::query()->forWorkspace($ws)->find($run->conversation_id);
        $trace = (array) $run->trace;
        $steps = array_values((array) $flow->steps);
        if (! $c) {
            $run->forceFill(['status' => 'failed'])->save();

            return;
        }
        for ($i = $from; $i < count($steps); $i++) {
            $s = $steps[$i];
            $res = 'done';
            try {
                switch ($s['type']) {
                    case 'send_text':
                        $contact = OmniContact::query()->forWorkspace($ws)->find($c->contact_id);
                        $this->outbox->queue($ws, $c, $this->fill((string) $s['text'], (string) ($contact?->name ?: 'there')), [], null, 'flow-'.$run->id.'-'.$i);
                        $res = 'sent';
                        break;
                    case 'delay':
                        $trace[] = ['step' => $i + 1, 'type' => 'delay', 'result' => 'waiting '.(int) $s['minutes'].' minutes', 'at' => now()->toIso8601String()];
                        $run->forceFill(['trace' => $trace])->save();
                        ContinueOmniFlow::dispatch($run->id, $i + 1)->delay(now()->addMinutes((int) $s['minutes']));

                        return;
                    case 'set_status':
                        $c->forceFill(['status' => $s['status']])->save();
                        break;
                    case 'add_tag':
                        $contact = OmniContact::query()->forWorkspace($ws)->find($c->contact_id);
                        if ($contact) {
                            $tags = array_values(array_unique(array_merge((array) $contact->tags, [(string) $s['tag']])));
                            $contact->forceFill(['tags' => array_slice($tags, 0, 30)])->save();
                        }
                        break;
                    case 'note':
                        OmniNote::create(['workspace_id' => $ws, 'conversation_id' => $c->id, 'author_id' => null, 'body' => mb_substr('Flow "'.$flow->name.'": '.$s['text'], 0, 2000)]);
                        break;
                }
            } catch (WindowClosedException) {
                $res = 'skipped: the 24-hour window is closed';
            } catch (\Throwable $e) {
                $trace[] = ['step' => $i + 1, 'type' => $s['type'], 'result' => 'failed: '.mb_substr($e->getMessage(), 0, 100), 'at' => now()->toIso8601String()];
                $run->forceFill(['status' => 'failed', 'trace' => $trace])->save();

                return;
            }
            $trace[] = ['step' => $i + 1, 'type' => $s['type'], 'result' => $res, 'at' => now()->toIso8601String()];
        }
        $run->forceFill(['status' => 'done', 'trace' => $trace])->save();
    }

    private function fill(string $text, string $name): string
    {
        return str_replace('{name}', explode(' ', trim($name))[0] ?: 'there', $text);
    }

    /** The starter pack a workspace can install in one click (all start as drafts so nothing goes live unreviewed). */
    public static function starters(): array
    {
        return [
            ['name' => 'Welcome new customers', 'trigger_type' => 'new_conversation', 'trigger_value' => null, 'steps' => [
                ['type' => 'send_text', 'text' => 'Hi {name}, thanks for reaching out. We have your message and will reply shortly.'], ['type' => 'add_tag', 'tag' => 'new']]],
            ['name' => 'After-hours reply', 'trigger_type' => 'outside_hours', 'trigger_value' => '09:00-17:00', 'steps' => [
                ['type' => 'send_text', 'text' => 'We are closed right now. We will answer first thing when we open.'], ['type' => 'set_status', 'status' => 'pending']]],
            ['name' => 'Pricing question', 'trigger_type' => 'keyword', 'trigger_value' => 'price, prices, cost, how much', 'steps' => [
                ['type' => 'send_text', 'text' => 'Happy to help with pricing. Tell us what you need and we will send an exact quote.'], ['type' => 'add_tag', 'tag' => 'pricing']]],
            ['name' => 'Follow up after a day', 'trigger_type' => 'new_conversation', 'trigger_value' => null, 'steps' => [
                ['type' => 'delay', 'minutes' => 1440], ['type' => 'note', 'text' => 'It has been a day since this customer first wrote. Check that they were helped.']]],
        ];
    }
}

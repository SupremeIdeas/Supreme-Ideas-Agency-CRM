<?php

namespace App\Services\Voice;

use App\Jobs\LogCallEventJob;
use App\Models\AiVoiceAgentConfig;
use App\Models\AiVoiceAgentSession;
use App\Models\BusinessHoursSchedule;
use App\Models\CallFlow;
use App\Models\CallFlowNode;
use App\Models\RingGroup;
use App\Services\SMS\VoiceProviderInterface;
use App\Support\Business\BusinessSettings;
use App\Services\Business\AiVoice\AgentBilling;
use App\Services\Business\AiVoice\AgentGreeting;
use App\Services\Business\AiVoice\AgentToken;
use App\Support\Business\RecordingPolicy;
use Illuminate\Http\Request;

/**
 * Business Suite Prompt 14 §3.2 — turns a CallFlow into TwiML, one node per
 * HTTP round trip. The whole walk is STATELESS: every continuation (menu
 * digits, "that leg didn't answer, try the next") is a signed Twilio
 * callback to a route that carries the node id plus a couple of query
 * counters (`h` hop count, `a` menu attempt, `step` sequential leg), so no
 * call-state table is needed and nothing is lost between requests.
 *
 * Verb support, all native Twilio: `<Gather>` (menu), `<Dial>` with
 * several `<Number>`/`<Client>` children (simultaneous ring group),
 * chained `<Dial action>` (sequential), the existing forwardTwiml() verbs
 * (forward — reused, not re-implemented), `<Play>`/`<Say>`+`<Record>`
 * (voicemail). A true hold-queue (`<Enqueue>` + agent-side pickup) is NOT
 * built — see the Prompt 14 progress note for why.
 */
class CallFlowResolver
{
    // Loop guard, menu retries and per-leg ring time are admin settings (Admin > Business Suite > Call behaviour): see App\Support\Business\BusinessSettings.

    private ?VoiceProviderInterface $voice;

    public function __construct(?VoiceProviderInterface $voice = null)
    {
        // Only Twilio implements the voice interface (Prompt 14 scope).
        $this->voice = $voice ?? app('number.twilio');
    }

    /** Entry point from the voice webhook: start at the flow's root node. */
    public function buildTwiml(CallFlow $flow, Request $request): string
    {
        $root = $flow->rootNode();
        if (! $root) {
            return $this->wrap($this->unavailable());
        }

        return $this->wrap($this->renderNode($flow, $root, $request, hops: 0, attempt: 0));
    }

    /** A redirect target: render one specific node. */
    public function nodeTwiml(CallFlow $flow, CallFlowNode $node, Request $request): string
    {
        return $this->wrap($this->renderNode($flow, $node, $request, $this->hops($request), (int) $request->query('a', 0)));
    }

    /** `<Gather>` posted a digit (or nothing) for a menu node. */
    public function digitsTwiml(CallFlow $flow, CallFlowNode $menu, Request $request): string
    {
        $digit = trim((string) $request->input('Digits', ''));
        $map = (array) $menu->cfg('digits', []);
        $hops = $this->hops($request) + 1;

        if ($digit !== '' && isset($map[$digit])) {
            $child = $this->sibling($flow, (int) $map[$digit]);
            if ($child) {
                return $this->wrap($this->renderNode($flow, $child, $request, $hops, 0));
            }
        }

        return $this->wrap($this->retryMenu($flow, $menu, $request, $hops, (int) $request->query('a', 0) + 1));
    }

    /** A queue `<Dial>` leg finished (answered, busy, no-answer, …). */
    public function dialResultTwiml(CallFlow $flow, CallFlowNode $queue, Request $request): string
    {
        $status = (string) $request->input('DialCallStatus', '');
        $step = max(0, (int) $request->query('step', 0));
        $hops = $this->hops($request) + 1;
        $answered = in_array($status, ['completed', 'answered'], true);

        LogCallEventJob::dispatch([
            'user_id' => $flow->walletGroup?->owner_user_id, 'rule_id' => null,
            'call_flow_id' => $flow->id, 'call_flow_node_id' => $queue->id,
            'call_sid' => $request->input('CallSid'), 'from' => $request->input('From'), 'to' => $request->input('To'),
            'status' => $answered ? 'answered' : 'no-answer',
        ]);

        if ($answered) {
            return $this->wrap('<Hangup/>');
        }

        $group = $this->ringGroup($flow, $queue);
        if ($group && $group->strategy === RingGroup::SEQUENTIAL) {
            return $this->wrap($this->sequentialLeg($flow, $queue, $group, $request, $step + 1, $hops, $this->recFromRequest($request)));
        }

        return $this->wrap($this->overflow($flow, $queue, $request, $hops));
    }

    // ── node rendering ──────────────────────────────────────────────────────

    private function renderNode(CallFlow $flow, CallFlowNode $node, Request $request, int $hops, int $attempt): string
    {
        if ($hops > BusinessSettings::call('max_hops')) {
            return $this->say('Sorry, we could not connect your call. Goodbye.').'<Hangup/>';
        }

        LogCallEventJob::dispatch([
            'user_id' => $flow->walletGroup?->owner_user_id, 'rule_id' => null,
            'call_flow_id' => $flow->id, 'call_flow_node_id' => $node->id,
            'call_sid' => $request->input('CallSid'), 'from' => $request->input('From'), 'to' => $request->input('To'),
            'status' => 'ringing',
        ]);

        return match ($node->type) {
            CallFlowNode::MENU => $this->menu($flow, $node, $request, $hops, $attempt),
            CallFlowNode::QUEUE => $this->withRecording($flow, $node, $request, $hops, fn (?array $rec) => $this->queue($flow, $node, $request, $hops, $rec)),
            CallFlowNode::FORWARD => $this->withRecording($flow, $node, $request, $hops, fn (?array $rec) => $this->forward($flow, $node, $request, $rec)),
            CallFlowNode::VOICEMAIL => $this->voicemail($node),
            CallFlowNode::AI_AGENT => $this->aiAgent($flow, $node, $request, $hops),
            default => $this->unavailable(),
        };
    }

    private function menu(CallFlow $flow, CallFlowNode $node, Request $request, int $hops, int $attempt): string
    {
        if ($attempt >= BusinessSettings::call('menu_attempts')) {
            return $this->say('We did not get that. Goodbye.').'<Hangup/>';
        }

        // §2: a menu carrying a schedule branches to `closed_node_id` after hours.
        $scheduleId = $node->cfg('business_hours_schedule_id');
        $closedId = $node->cfg('closed_node_id');
        if ($scheduleId && $closedId) {
            $schedule = BusinessHoursSchedule::where('wallet_group_id', $flow->wallet_group_id)->find($scheduleId);
            $closed = $this->sibling($flow, (int) $closedId);
            if ($schedule && $closed && ! $schedule->isOpenNow()) {
                return $this->renderNode($flow, $closed, $request, $hops + 1, 0);
            }
        }

        $timeout = max(2, min(30, (int) $node->cfg('timeout_seconds', 5)));
        $action = $this->url('webhooks.twilio.call-flow.digits', $flow, $node, ['h' => $hops, 'a' => $attempt]);
        $prompt = (string) $node->cfg('prompt_text', 'Thanks for calling.');

        // No input within the timeout falls out of <Gather> to the retry path.
        return '<Gather input="dtmf" numDigits="1" timeout="'.$timeout.'" method="POST" action="'.$this->e($action).'">'
            .$this->say($prompt)
            .'</Gather>'
            .'<Redirect method="POST">'.$this->e($this->retryUrl($flow, $node, $hops + 1, $attempt + 1)).'</Redirect>';
    }

    private function retryMenu(CallFlow $flow, CallFlowNode $menu, Request $request, int $hops, int $attempt): string
    {
        if ($attempt >= BusinessSettings::call('menu_attempts') || $hops > BusinessSettings::call('max_hops')) {
            return $this->say('We did not get that. Goodbye.').'<Hangup/>';
        }

        $retry = $this->sibling($flow, (int) $menu->cfg('invalid_retry_node_id', 0)) ?? $menu;

        return $this->say('Sorry, that was not a valid choice.')
            .'<Redirect method="POST">'.$this->e($this->url('webhooks.twilio.call-flow', $flow, $retry, ['h' => $hops, 'a' => $attempt])).'</Redirect>';
    }

    private function retryUrl(CallFlow $flow, CallFlowNode $menu, int $hops, int $attempt): string
    {
        $retryId = (int) $menu->cfg('invalid_retry_node_id', 0);
        $retry = $retryId ? ($this->sibling($flow, $retryId) ?? $menu) : $menu;

        // Past the attempt cap the redirect target says goodbye instead of replaying.
        return $this->url('webhooks.twilio.call-flow', $flow, $retry, ['h' => $hops, 'a' => $attempt]);
    }

    private function queue(CallFlow $flow, CallFlowNode $node, Request $request, int $hops, ?array $rec = null): string
    {
        $group = $this->ringGroup($flow, $node);
        if (! $group) {
            return $this->overflow($flow, $node, $request, $hops);
        }

        if ($group->strategy === RingGroup::SEQUENTIAL) {
            return $this->sequentialLeg($flow, $node, $group, $request, 0, $hops, $rec);
        }

        $targets = $group->dialTargets();
        if ($targets === []) {
            return $this->overflow($flow, $node, $request, $hops);
        }

        // Simultaneous: ONE <Dial>, every seat as a child — Twilio bridges
        // whichever answers first, no custom state (§4.3).
        $wait = max(5, min(120, (int) $node->cfg('max_wait_seconds', 120)));

        return $this->dial($flow, $node, $request, $targets, $wait, step: 0, hops: $hops, rec: $rec);
    }

    private function sequentialLeg(CallFlow $flow, CallFlowNode $node, RingGroup $group, Request $request, int $step, int $hops, ?array $rec = null): string
    {
        $targets = $group->dialTargets();
        $wait = max(5, min(120, (int) $node->cfg('max_wait_seconds', 120)));

        if (! isset($targets[$step]) || $step * BusinessSettings::call('leg_seconds') >= $wait) {
            return $this->overflow($flow, $node, $request, $hops);
        }

        return $this->dial($flow, $node, $request, [$targets[$step]], min(BusinessSettings::call('leg_seconds'), $wait - $step * BusinessSettings::call('leg_seconds')), $step, $hops, $rec);
    }

    /**
     * @param  array<int,array{number:?string,client:?string}>  $targets
     * @param  ?array<string,string>  $rec  recording evidence when this call is being recorded (see withRecording())
     */
    private function dial(CallFlow $flow, CallFlowNode $node, Request $request, array $targets, int $timeout, int $step, int $hops, ?array $rec = null, ?string $whisperUrl = null): string
    {
        $from = (string) $request->input('From', '');
        $caller = $from !== '' ? ' callerId="'.$this->e($from).'"' : '';
        $action = $this->url('webhooks.twilio.call-flow.dial', $flow, $node, ['step' => $step, 'h' => $hops] + ($rec ? $this->recQuery($rec) : []));
        $whisper = $whisperUrl ? ' url="'.$this->e($whisperUrl).'"' : ($rec ? ' url="'.$this->e(route('webhooks.twilio.call-flow.whisper')).'"' : '');

        $children = '';
        foreach ($targets as $t) {
            if ($t['number']) {
                $children .= '<Number'.$whisper.'>'.$this->e($t['number']).'</Number>';
            }
            if ($t['client']) {
                $children .= '<Client'.$whisper.'>'.$this->e($t['client']).'</Client>';
            }
        }

        return '<Dial'.$caller.($rec ? $this->recordingAttrs($flow, $node, $request, $rec) : '').' timeout="'.$timeout.'" method="POST" action="'.$this->e($action).'">'.$children.'</Dial>';
    }

    /** Nobody answered / nobody reachable: the overflow node, or a polite goodbye. */
    private function overflow(CallFlow $flow, CallFlowNode $node, Request $request, int $hops): string
    {
        $next = $this->sibling($flow, (int) $node->cfg('overflow_node_id', 0));
        if ($next) {
            return $this->renderNode($flow, $next, $request, $hops + 1, 0);
        }

        return $this->say('Sorry, no one is available to take your call right now. Goodbye.').'<Hangup/>';
    }

    /**
     * §3.2: reuse the single forward-TwiML builder the CallForwardingRule path
     * already uses, so the two can never drift. forwardTwiml() returns a whole
     * document; we only need its verbs.
     */
    private function forward(CallFlow $flow, CallFlowNode $node, Request $request, ?array $rec = null): string
    {
        $target = (string) $node->cfg('target_number', '');
        if ($target === '') {
            return $this->unavailable();
        }

        $doc = $this->voice->forwardTwiml(
            $target,
            (string) $request->input('From', '') ?: null,
            $node->cfg('fallback_number') ?: null,
            route('webhooks.twilio.recording'),
        );
        $verbs = (string) preg_replace('~^<\?xml[^>]*\?>\s*<Response>|</Response>\s*$~', '', $doc);

        // Recording: add the record attributes to every <Dial> and tell the person who answers (the number they dial is theirs).
        if ($rec) {
            $verbs = str_replace('<Dial', '<Dial'.$this->recordingAttrs($flow, $node, $request, $rec), $verbs);
            $verbs = str_replace('<Number>', '<Number url="'.$this->e(route('webhooks.twilio.call-flow.whisper')).'">', $verbs);
        }

        return $verbs;
    }

    // ── AI voice agent (Prompt 17) ──────────────────────────────────────────

    /**
     * Connect the caller to the AI voice agent through Twilio ConversationRelay. Anything that stops the agent from taking the call (module or workspace
     * switched off, no server address, owner cannot fund a minute) sends the caller to the node's overflow step, or ends politely: the caller never hits a dead end.
     */
    private function aiAgent(CallFlow $flow, CallFlowNode $node, Request $request, int $hops): string
    {
        $group = $flow->walletGroup;
        $config = $group ? AiVoiceAgentConfig::forGroup($group) : null;
        if ($group && $config && $config->enabled && \App\Support\Business\BusinessSettings::aiVoiceReady() && app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($group, 'ai_voice')) {
            $session = app(AgentBilling::class)->begin($group, [
                'virtual_number_id' => $flow->virtual_number_id, 'call_flow_id' => $flow->id, 'call_flow_node_id' => $node->id,
                'call_reference' => (string) $request->input('CallSid', ''), 'from_number' => (string) $request->input('From', ''),
            ]);
            if ($session) {
                $s = fn (string $k) => \App\Support\Business\BusinessSettings::aiText($k);
                $attrs = ' url="'.$this->e($s('server_url')).'" welcomeGreeting="'.$this->e(AgentGreeting::for($config, (string) $group->name)).'" language="'.$this->e($config->language ?: 'en-US').'"'
                    .' ttsProvider="'.$this->e($s('tts_provider') ?: 'Google').'"'.($s('voice') !== '' ? ' voice="'.$this->e($s('voice')).'"' : '')
                    .' transcriptionProvider="Deepgram" interruptible="true" dtmfDetection="true"';
                $action = $this->url('webhooks.twilio.ai-agent.continue', $flow, $node, ['s' => $session->id, 'h' => $hops]);

                return '<Connect action="'.$this->e($action).'"><ConversationRelay'.$attrs.'>'
                    .'<Parameter name="session" value="'.$session->id.'"/><Parameter name="sig" value="'.AgentToken::make($session->id).'"/>'
                    .'</ConversationRelay></Connect>';
            }
        }

        return $this->overflow($flow, $node, $request, $hops);
    }

    /** The conversation ended (Twilio calls the `<Connect action>` URL): bill it, file it, and hand the caller to a person when the agent asked for that. */
    public function aiAgentContinueTwiml(CallFlow $flow, CallFlowNode $node, Request $request): string
    {
        $session = AiVoiceAgentSession::query()->where('call_flow_id', $flow->id)->find((int) $request->query('s', 0));
        if (! $session) {
            return $this->wrap('<Hangup/>');
        }
        $session = app(\App\Services\Business\AiVoice\AgentSessionFinalizer::class)->finalize($session);
        $hops = $this->hops($request) + 1;

        if ($session->handoff_reason === null) {
            return $this->wrap('<Hangup/>');
        }

        $group = $this->ringGroup($flow, $node);
        $targets = $group?->dialTargets() ?? [];
        if (! $group || $targets === []) {
            return $this->wrap($this->overflow($flow, $node, $request, $hops));
        }
        if ($group->strategy === RingGroup::SEQUENTIAL) {
            return $this->wrap($this->sequentialLeg($flow, $node, $group, $request, 0, $hops));
        }

        $whisper = route('webhooks.twilio.call-flow.whisper', ['s' => $session->id, 't' => AgentToken::make($session->id)]);

        return $this->wrap($this->dial($flow, $node, $request, $targets, 60, 0, $hops, null, $whisper));
    }

    // ── recording (Prompt 15) ───────────────────────────────────────────────

    /**
     * Wraps a node that connects the caller to a person. Decides whether this call is recorded (RecordingPolicy), says what the law
     * and the consent steer require, and hands the node builder the evidence to attach (or null for an unrecorded call):
     *  - off:     nothing changes;
     *  - notice:  the caller hears the announcement, then the call connects and is recorded;
     *  - consent: the caller is asked to press 1. 1 records; anything else, or silence, connects the call UNRECORDED.
     *
     * @param  \Closure(?array<string,string>): string  $inner
     */
    private function withRecording(CallFlow $flow, CallFlowNode $node, Request $request, int $hops, \Closure $inner): string
    {
        // Continuing after the caller's answer to the consent prompt, or on a later leg of a sequential group.
        $answer = (string) $request->query('rc', '');
        if ($answer === 'ask') {
            $rec = trim((string) $request->input('Digits', '')) === '1' ? $this->recFromRequest($request, 'pressed_1') : null;

            return $inner($rec);
        }
        if ($answer === '0') {
            return $inner(null);
        }
        if ($existing = $this->recFromRequest($request)) {
            return $inner($existing);
        }

        $number = $flow->virtualNumber;
        if (! $number) {
            return $inner(null);
        }
        $from = (string) $request->input('From', '');
        $to = (string) $request->input('To', '') ?: (string) $number->phone_number;
        $decision = app(RecordingPolicy::class)->decide($number, $from, $to);

        if ($decision['mode'] === RecordingPolicy::OFF) {
            return $inner(null);
        }

        $rec = [
            'rg' => (string) $decision['regime'], 'fc' => (string) $decision['from_country'], 'tc' => (string) $decision['to_country'],
            'av' => (string) config('call_recording.announcement_version', 'v1'), 'ca' => 'notice', 't' => (string) time(),
        ];

        if ($decision['mode'] === RecordingPolicy::NOTICE) {
            return $this->say((string) config('call_recording.announcement')).$inner($rec);
        }

        // consent: ask. The Gather posts back to this node with rc=ask; silence falls through to the unrecorded path.
        $ask = $this->url('webhooks.twilio.call-flow', $flow, $node, ['h' => $hops, 'a' => 0, 'rc' => 'ask'] + $this->recQuery($rec));
        $skip = $this->url('webhooks.twilio.call-flow', $flow, $node, ['h' => $hops + 1, 'a' => 0, 'rc' => '0']);

        return '<Gather input="dtmf" numDigits="1" timeout="6" method="POST" action="'.$this->e($ask).'">'
            .$this->say((string) config('call_recording.consent_prompt'))
            .'</Gather>'
            .'<Redirect method="POST">'.$this->e($skip).'</Redirect>';
    }

    /** The recording evidence carried in the query string through every hop of one call (set by withRecording). */
    private function recFromRequest(Request $request, ?string $action = null): ?array
    {
        if ($request->query('rg') === null) {
            return null;
        }

        return [
            'rg' => (string) $request->query('rg'), 'fc' => (string) $request->query('fc', ''), 'tc' => (string) $request->query('tc', ''),
            'av' => (string) $request->query('av', ''), 'ca' => $action ?? (string) $request->query('ca', 'notice'), 't' => (string) $request->query('t', time()),
        ];
    }

    /** @param array<string,string> $rec */
    private function recQuery(array $rec): array
    {
        return $rec;
    }

    /** The `<Dial>` attributes that make Twilio record from answer and report back to our signed callback. @param array<string,string> $rec */
    private function recordingAttrs(CallFlow $flow, CallFlowNode $node, Request $request, array $rec): string
    {
        $callback = route('webhooks.twilio.call-recording.status', ['callFlow' => $flow->id, 'node' => $node->id] + $rec + [
            'from' => (string) $request->input('From', ''), 'to' => (string) ($request->input('To', '') ?: $flow->virtualNumber?->phone_number),
        ]);

        return ' record="record-from-answer-dual" recordingStatusCallback="'.$this->e($callback).'" recordingStatusCallbackEvent="completed"'
            .' recordingStatusCallbackMethod="POST" timeLimit="'.(BusinessSettings::recordingMaxMinutes() * 60).'"';
    }

    private function voicemail(CallFlowNode $node): string
    {
        $greeting = (string) $node->cfg('greeting_url', '');
        $intro = ($greeting !== '' && preg_match('~^https://~i', $greeting))
            ? '<Play>'.$this->e($greeting).'</Play>'
            : $this->say('Please leave a message after the tone.');

        return $intro.'<Record maxLength="120" playBeep="true" action="'.$this->e(route('webhooks.twilio.recording')).'" />';
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** A node id is only honoured when it belongs to THIS flow. */
    private function sibling(CallFlow $flow, int $id): ?CallFlowNode
    {
        return $id > 0 ? $flow->nodes()->find($id) : null;
    }

    private function ringGroup(CallFlow $flow, CallFlowNode $node): ?RingGroup
    {
        $id = (int) $node->cfg('ring_group_id', 0);
        // An AI agent node hands over to the ring group set in the workspace's agent settings.
        if ($id === 0 && $node->type === CallFlowNode::AI_AGENT) {
            $id = (int) AiVoiceAgentConfig::forGroup($flow->walletGroup)->ring_group_id;
        }

        return $id > 0 ? RingGroup::where('wallet_group_id', $flow->wallet_group_id)->find($id) : null;
    }

    private function hops(Request $request): int
    {
        return max(0, (int) $request->query('h', 0));
    }

    /** @param array<string,int|string> $query */
    private function url(string $route, CallFlow $flow, CallFlowNode $node, array $query = []): string
    {
        return route($route, ['callFlow' => $flow->id, 'node' => $node->id] + $query);
    }

    private function unavailable(): string
    {
        return $this->say('Sorry, this line is not available right now. Goodbye.').'<Hangup/>';
    }

    private function say(string $text): string
    {
        return '<Say>'.$this->e($text).'</Say>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function wrap(string $verbs): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response>'.$verbs.'</Response>';
    }
}

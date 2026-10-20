<?php

namespace App\Services\Business\AiVoice;

use App\Models\AiVoiceAgentConfig;
use App\Models\AiVoiceAgentSession;

/**
 * The fixed set of things the voice agent may DO (MASTER-ONLY, Business Suite Prompt 17). A small registry, not a parallel action system:
 *  - transfer_to_human: hands the live call to the workspace's ring group (the Prompt 14 queue mechanics, via the call-flow `action` URL);
 *  - book_callback:     records a callback request on the session and tells the team (no scheduling system exists to reuse, and none is invented);
 *  - not_understood:    counts toward the clarification limit that triggers a hand-off;
 *  - end_call:          politely finishes with an outcome.
 * The spec's `check_order_status` is deliberately absent: Naara's own support tools (SupportTools) are scoped to the signed-in Naara customer, and a workspace's
 * callers are not Naara customers. Prompt 18 (integrations) is where a workspace's own systems become tools; add them here as entries.
 */
class AgentToolkit
{
    public const TRANSFER = 'transfer_to_human';

    public const CALLBACK = 'book_callback';

    public const UNCLEAR = 'not_understood';

    public const END = 'end_call';

    public const OUTCOMES = ['qualified', 'booked', 'no_outcome'];

    /** @return list<array<string,mixed>> Anthropic tool definitions */
    public function definitions(): array
    {
        return [
            [
                'name' => self::TRANSFER,
                'description' => 'Hand the caller to a person on the team. Use it when the caller asks for a human, is upset, or you cannot help. Give a one sentence summary for the person who will answer.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'reason' => ['type' => 'string', 'enum' => ['caller_requested', 'cannot_help', 'upset_caller'], 'description' => 'Why you are transferring.'],
                    'summary' => ['type' => 'string', 'description' => 'One short sentence: who is calling and what they need.'],
                ], 'required' => ['reason', 'summary']],
            ],
            [
                'name' => self::CALLBACK,
                'description' => 'Record that the team should call the caller back. Use when nobody is available or the caller prefers a callback.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'preferred_time' => ['type' => 'string', 'description' => 'When the caller would like the call, in their own words.'],
                    'caller_name' => ['type' => 'string', 'description' => 'The caller\'s name if they gave it.'],
                    'note' => ['type' => 'string', 'description' => 'What the call is about, one short sentence.'],
                ], 'required' => ['preferred_time']],
            ],
            [
                'name' => self::UNCLEAR,
                'description' => 'Call this when the caller\'s words made no sense or you could not understand them, instead of guessing. Then ask them to repeat.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => self::END,
                'description' => 'Finish the call when the caller has what they need or says goodbye. Say a short goodbye first.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'outcome' => ['type' => 'string', 'enum' => self::OUTCOMES, 'description' => 'qualified = a real lead or request was captured; booked = a callback was recorded; no_outcome = nothing came of it.'],
                ], 'required' => ['outcome']],
            ],
        ];
    }

    /**
     * Run one tool the model asked for. Never throws: a bad call comes back as an error string the model can read.
     *
     * @param  array<string,mixed>  $input
     * @return array{result: string, end: ?string} `end` is 'handoff' or 'end' when this tool finishes the conversation
     */
    public function run(string $name, array $input, AiVoiceAgentSession $session, AiVoiceAgentConfig $config, bool $teamAvailable): array
    {
        return match ($name) {
            self::TRANSFER => $this->transfer($input, $session, $config, $teamAvailable),
            self::CALLBACK => $this->callback($input, $session),
            self::UNCLEAR => ['result' => 'Noted. Ask the caller to repeat or rephrase.', 'end' => null],
            self::END => $this->end($input, $session),
            default => ['result' => 'That tool does not exist.', 'end' => null],
        };
    }

    /** @param array<string,mixed> $input */
    private function transfer(array $input, AiVoiceAgentSession $session, AiVoiceAgentConfig $config, bool $teamAvailable): array
    {
        if (! $config->ring_group_id) {
            return ['result' => 'There is nobody to transfer to. Offer to take a callback request instead.', 'end' => null];
        }
        if (! $teamAvailable) {
            return ['result' => 'The team is closed right now. Tell the caller, and offer to take a callback request instead.', 'end' => null];
        }

        $reason = in_array($input['reason'] ?? '', ['caller_requested', 'cannot_help', 'upset_caller'], true) ? $input['reason'] : 'cannot_help';
        $session->forceFill(['handoff_reason' => $reason, 'summary' => mb_substr(trim((string) ($input['summary'] ?? '')), 0, 300) ?: null])->save();

        return ['result' => 'Transfer started. Tell the caller you are connecting them now, in one short sentence.', 'end' => 'handoff'];
    }

    /** @param array<string,mixed> $input */
    private function callback(array $input, AiVoiceAgentSession $session): array
    {
        $when = mb_substr(trim((string) ($input['preferred_time'] ?? '')), 0, 120);
        if ($when === '') {
            return ['result' => 'Ask the caller when they would like to be called back.', 'end' => null];
        }

        $session->forceFill(['callback' => [
            'preferred_time' => $when,
            'caller_name' => mb_substr(trim((string) ($input['caller_name'] ?? '')), 0, 80) ?: null,
            'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 200) ?: null,
            'number' => $session->from_number,
        ], 'outcome' => 'booked'])->save();

        return ['result' => 'Callback recorded for the number they are calling from. Confirm it to the caller.', 'end' => null];
    }

    /** @param array<string,mixed> $input */
    private function end(array $input, AiVoiceAgentSession $session): array
    {
        $outcome = in_array($input['outcome'] ?? '', self::OUTCOMES, true) ? $input['outcome'] : 'no_outcome';
        // A booked callback already recorded is never downgraded by a careless end_call.
        if ($session->outcome !== 'booked') {
            $session->forceFill(['outcome' => $outcome])->save();
        }

        return ['result' => 'The call will end after your goodbye.', 'end' => 'end'];
    }
}

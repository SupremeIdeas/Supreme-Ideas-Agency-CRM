<?php

namespace App\Services\Business\AiVoice;

use App\Models\AiVoiceAgentConfig;
use App\Models\AiVoiceAgentSession;

/**
 * Turns Twilio's `setup` message into a running AgentConversation (MASTER-ONLY, Business Suite Prompt 17), or refuses it. The session id and its signature come
 * from the `<Parameter>` tags our own TwiML carried, so a conversation can only be opened for a session we created, that is still in progress, that has not been
 * settled, and whose workspace still has the agent switched on.
 */
class ConversationFactory
{
    public function __construct(private AgentLlm $llm, private AgentToolkit $tools)
    {
    }

    /** @param array<string,mixed> $setup @return ?array{conversation: AgentConversation, deadline_seconds: int} */
    public function __invoke(array $setup): ?array
    {
        $params = (array) ($setup['customParameters'] ?? []);
        $id = (int) ($params['session'] ?? 0);
        if ($id < 1 || ! AgentToken::ok($id, (string) ($params['sig'] ?? ''))) {
            return null;
        }

        $session = AiVoiceAgentSession::query()->with('walletGroup')->find($id);
        if (! $session || $session->status !== AiVoiceAgentSession::IN_PROGRESS || $session->settled_at !== null || ! $session->walletGroup) {
            return null;
        }
        $config = AiVoiceAgentConfig::forGroup($session->walletGroup);
        if (! $config->enabled) {
            return null;
        }

        $open = $config->business_hours_schedule_id ? (bool) $config->schedule?->isOpenNow() : true;
        $conversation = new AgentConversation($session, $config, $this->llm, $this->tools, (string) $session->walletGroup->name, $open);

        // Wind the call up a little before the funded block ends so the caller hears a goodbye, not a click.
        return ['conversation' => $conversation, 'deadline_seconds' => max(10, $session->fundedSeconds() - 10)];
    }
}

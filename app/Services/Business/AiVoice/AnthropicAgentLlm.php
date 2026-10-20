<?php

namespace App\Services\Business\AiVoice;

use App\Services\AI\AnthropicClient;

/** The voice agent's model: the Anthropic API the platform already uses for its own AI features (one vendor, one key, one set of rate limits). */
class AnthropicAgentLlm implements AgentLlm
{
    public function __construct(private AnthropicClient $client)
    {
    }

    public function converse(string $system, array $messages, array $tools): array
    {
        return $this->client->converse($system, $messages, $tools);
    }
}

<?php

namespace App\Services\Support;

use App\Services\AI\ClaudeGateway;
use App\Services\Support\Contracts\ChatModel;

/**
 * Production chat model (Module 24): the Anthropic Messages API with tool-use, through the one ClaudeGateway (retries, prompt caching, measured usage).
 * Gated on services.anthropic.api_key; the model id is configuration so it can be tuned without a deploy.
 */
class ClaudeChatModel implements ChatModel
{
    public function __construct(private ClaudeGateway $gateway) {}

    public function available(): bool
    {
        return $this->gateway->enabled();
    }

    public function reply(string $system, array $messages, array $tools): array
    {
        $response = $this->gateway->messages([
            'max_tokens' => 1024,
            'system' => $system,
            'tools' => $tools,
            'messages' => $messages,
        ], timeout: 60);

        return [
            'stop_reason' => $response['stop_reason'] ?? 'end_turn',
            'content' => $response['content'] ?? [],
        ];
    }
}

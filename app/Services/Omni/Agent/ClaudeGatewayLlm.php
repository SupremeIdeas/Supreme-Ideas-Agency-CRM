<?php

namespace App\Services\Omni\Agent;

use App\Services\AI\AiUsage;
use App\Services\AI\ClaudeGateway;

/** Production LlmClient: the platform's one ClaudeGateway, so every Respond call is measured in ai_usage_log as feature "respond_agent". */
final class ClaudeGatewayLlm implements LlmClient
{
    public function __construct(private readonly ClaudeGateway $gateway)
    {
    }

    public function complete(string $system, array $messages, int $maxTokens = 400, ?int $ownerUserId = null): array
    {
        $body = AiUsage::as('respond_agent', $ownerUserId, fn () => $this->gateway->messages([
            'model' => $this->gateway->model((string) config('omni.agent_model_class', 'fast')), 'max_tokens' => $maxTokens, 'system' => $system, 'messages' => $messages,
        ]));

        return ['text' => trim(ClaudeGateway::text($body)), 'tokens_in' => (int) ($body['usage']['input_tokens'] ?? 0), 'tokens_out' => (int) ($body['usage']['output_tokens'] ?? 0)];
    }
}

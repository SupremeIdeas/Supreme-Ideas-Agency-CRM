<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Thin Anthropic Messages API client (blueprint Section 29 + the AI Pricing
 * Architect). It lights up ONLY when an Anthropic API key is active — the key is
 * admin-managed via ProviderKeys (services.anthropic.api_key) or .env, never
 * hard-coded (money-safety rule 10). Every AI feature checks enabled() first and
 * degrades gracefully when the key is absent, so the platform runs fine without
 * it and simply offers the manual margin controls instead.
 */
class AnthropicClient
{
    /** True when an Anthropic key is configured (admin panel or .env). */
    public function enabled(): bool
    {
        return $this->gateway()->enabled();
    }

    public function model(): string
    {
        return $this->gateway()->model();
    }

    /** Every call goes through the one gateway (retries, prompt caching, usage log). Resolved lazily so test doubles that skip the constructor keep working. */
    protected function gateway(): ClaudeGateway
    {
        return app(ClaudeGateway::class);
    }

    /**
     * Send a single-turn Messages request and return the concatenated text of
     * the assistant reply. Throws if the key is missing or the call fails — the
     * caller is responsible for catching and surfacing a friendly message.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function complete(string $system, array $messages, int $maxTokens = 4096, float $temperature = 0.2): string
    {
        $body = $this->gateway()->messages([
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'system' => $system,
            'messages' => $messages,
        ]);
        $text = ClaudeGateway::text($body);

        if (trim($text) === '') {
            throw new RuntimeException('Anthropic API returned an empty response.');
        }

        return $text;
    }

    /**
     * One tool-aware Messages turn, for conversations that act (the Business Suite voice agent). Returns the spoken text, any tool calls the model made
     * and the raw content blocks (to append to the history verbatim). Short timeout: a person is waiting on the phone.
     *
     * @param  list<array<string,mixed>>  $messages
     * @param  list<array<string,mixed>>  $tools
     * @return array{text: string, tool_uses: list<array{id: string, name: string, input: array<string,mixed>}>, content: list<array<string,mixed>>}
     */
    public function converse(string $system, array $messages, array $tools, int $maxTokens = 400, int $timeout = 20): array
    {
        $body = $this->gateway()->messages(array_filter([
            'max_tokens' => $maxTokens,
            'temperature' => 0.3,
            'system' => $system,
            'messages' => $messages,
            'tools' => $tools ?: null,
        ], fn ($v) => $v !== null), timeout: $timeout);

        $content = array_values((array) ($body['content'] ?? []));
        $text = trim(collect($content)->where('type', 'text')->pluck('text')->implode(' '));
        $uses = collect($content)->where('type', 'tool_use')->map(fn ($b) => ['id' => (string) $b['id'], 'name' => (string) $b['name'], 'input' => (array) ($b['input'] ?? [])])->values()->all();

        return ['text' => $text, 'tool_uses' => $uses, 'content' => $content];
    }

    /**
     * Convenience: ask for a JSON object and decode it. Tolerates the model
     * wrapping the JSON in prose or a ```json fence by extracting the outermost
     * object. Throws if nothing decodes.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<mixed>
     */
    public function completeJson(string $system, array $messages, int $maxTokens = 4096): array
    {
        $raw = $this->complete($system, $messages, $maxTokens);

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Extract the first {...} block if the model added surrounding text.
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('Anthropic API did not return valid JSON.');
    }
}

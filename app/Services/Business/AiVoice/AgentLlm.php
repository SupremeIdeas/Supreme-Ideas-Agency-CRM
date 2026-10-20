<?php

namespace App\Services\Business\AiVoice;

/** The language model behind the voice agent, behind a contract so the conversation logic is tested with no network (and a vendor swap touches one class). */
interface AgentLlm
{
    /**
     * One model turn.
     *
     * @param  list<array<string,mixed>>  $messages  Anthropic-style messages (string or content-block content)
     * @param  list<array<string,mixed>>  $tools     Anthropic-style tool definitions
     * @return array{text: string, tool_uses: list<array{id: string, name: string, input: array<string,mixed>}>, content: list<array<string,mixed>>}
     *
     * @throws \RuntimeException when the model could not answer
     */
    public function converse(string $system, array $messages, array $tools): array;
}

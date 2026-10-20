<?php

namespace App\Services\Omni\Agent;

/** The one door from the Respond agent to a language model (MASTER-ONLY). Tests bind a fake; production binds ClaudeGatewayLlm. */
interface LlmClient
{
    /**
     * @param list<array{role:string,content:string}> $messages strictly alternating, starting and ending with the customer
     * @param int|null $ownerUserId the workspace owner whose plan pays, so the cost is measured against them (Brain economics)
     *
     * @return array{text:string,tokens_in:int,tokens_out:int}
     *
     * @throws \RuntimeException when the model is unavailable
     */
    public function complete(string $system, array $messages, int $maxTokens = 400, ?int $ownerUserId = null): array;
}

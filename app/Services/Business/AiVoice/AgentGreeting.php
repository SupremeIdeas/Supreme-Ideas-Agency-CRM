<?php

namespace App\Services\Business\AiVoice;

use App\Models\AiVoiceAgentConfig;

/** What the caller hears first. The owner writes the welcome; the disclosure that this is an automated assistant is always added and cannot be removed. */
final class AgentGreeting
{
    public const DISCLOSURE = 'You are speaking with an automated assistant.';

    public static function for(AiVoiceAgentConfig $config, string $businessName): string
    {
        $welcome = trim((string) $config->greeting) !== '' ? trim((string) $config->greeting) : 'Hello, thanks for calling '.$businessName.'.';

        return mb_substr($welcome, 0, 300).' '.self::DISCLOSURE;
    }
}

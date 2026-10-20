<?php

namespace App\Services\Business\AiVoice;

/** Signs a session id so a conversation can only be opened for a session our own TwiML created. */
final class AgentToken
{
    public static function make(int $sessionId): string
    {
        return substr(hash_hmac('sha256', 'ai-agent:'.$sessionId, (string) config('app.key')), 0, 32);
    }

    public static function ok(int $sessionId, string $token): bool
    {
        return $token !== '' && hash_equals(self::make($sessionId), $token);
    }
}

<?php

namespace App\Services\Inbox;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Bot API client (MASTER-ONLY, Business Suite Prompt 16). One bot per workspace connection: the token is passed in, never read from
 * platform config. The host is fixed (api.telegram.org), so a stored value can never point a request somewhere else. Like the WhatsApp client it never
 * throws into the caller: a failure returns null/false and is logged without the token.
 */
class TelegramBotClient
{
    private function url(string $token, string $method): string
    {
        return 'https://api.telegram.org/bot'.$token.'/'.$method;
    }

    /** The bot's own identity, or null when the token is wrong. @return ?array{id: string, username: ?string} */
    public function me(string $token): ?array
    {
        try {
            $r = Http::timeout(10)->get($this->url($token, 'getMe'));
        } catch (\Throwable $e) {
            Log::warning('Telegram getMe failed', ['error' => $this->scrub($e->getMessage(), $token)]);

            return null;
        }
        if (! $r->successful() || ! $r->json('ok')) {
            return null;
        }

        return ['id' => (string) $r->json('result.id'), 'username' => $r->json('result.username')];
    }

    /** Point the bot's updates at our webhook. Telegram echoes `$secret` back in X-Telegram-Bot-Api-Secret-Token on every delivery. */
    public function setWebhook(string $token, string $url, string $secret): bool
    {
        try {
            $r = Http::timeout(10)->post($this->url($token, 'setWebhook'), [
                'url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['message'], 'drop_pending_updates' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram setWebhook failed', ['error' => $this->scrub($e->getMessage(), $token)]);

            return false;
        }

        return $r->successful() && (bool) $r->json('ok');
    }

    public function deleteWebhook(string $token): void
    {
        try {
            Http::timeout(10)->post($this->url($token, 'deleteWebhook'));
        } catch (\Throwable) {
            // best effort: removing a connection must not fail because Telegram is unreachable
        }
    }

    /** Send plain text. Returns "chatId:messageId" style provider ref (the message id), or null on failure. */
    public function sendMessage(string $token, string $chatId, string $text): ?string
    {
        $text = trim($text);
        if ($token === '' || $chatId === '' || $text === '') {
            return null;
        }

        try {
            $r = Http::timeout(15)->post($this->url($token, 'sendMessage'), ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4096)]);
        } catch (\Throwable $e) {
            Log::warning('Telegram send failed', ['error' => $this->scrub($e->getMessage(), $token)]);

            return null;
        }
        if (! $r->successful() || ! $r->json('ok')) {
            Log::warning('Telegram send rejected', ['status' => $r->status(), 'description' => $r->json('description')]);

            return null;
        }

        return (string) $r->json('result.message_id');
    }

    /**
     * sendMessage with the reason when it fails (omni adapter): ok, the message id, Telegram's own error_code and description, and retry_after when flood control applies.
     *
     * @return array{ok: bool, id: ?string, code: ?int, description: ?string, retry_after: ?int}
     */
    public function sendMessageDetailed(string $token, string $chatId, string $text): array
    {
        $fail = fn (?int $code, ?string $d, ?int $retry = null) => ['ok' => false, 'id' => null, 'code' => $code, 'description' => $d, 'retry_after' => $retry];
        $text = trim($text);
        if ($token === '' || $chatId === '' || $text === '') {
            return $fail(null, 'empty token, chat or text');
        }
        try {
            $r = Http::timeout(15)->post($this->url($token, 'sendMessage'), ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4096)]);
        } catch (\Throwable $e) {
            return $fail(null, $this->scrub($e->getMessage(), $token));
        }
        if ($r->successful() && $r->json('ok')) {
            return ['ok' => true, 'id' => (string) $r->json('result.message_id'), 'code' => null, 'description' => null, 'retry_after' => null];
        }

        return $fail((int) ($r->json('error_code') ?? $r->status()), (string) $r->json('description'), $r->json('parameters.retry_after') !== null ? (int) $r->json('parameters.retry_after') : null);
    }

    /** Any Bot API send method (sendPhoto, sendVideo, sendAnimation, sendDocument, sendAudio, sendVoice, sendLocation, sendContact, sendPoll, sendMessage with parse_mode...). Returns the message id or null. */
    public function call(string $token, string $method, array $params): ?string
    {
        if ($token === '' || ! preg_match('/^send[A-Za-z]+$/', $method) || ! isset($params['chat_id'])) {
            return null;
        }

        try {
            $r = Http::timeout(30)->post($this->url($token, $method), $params);
        } catch (\Throwable $e) {
            Log::warning('Telegram send failed', ['method' => $method, 'error' => $this->scrub($e->getMessage(), $token)]);

            return null;
        }
        if (! $r->successful() || ! $r->json('ok')) {
            Log::warning('Telegram send rejected', ['method' => $method, 'status' => $r->status(), 'description' => $r->json('description')]);

            return null;
        }

        return (string) $r->json('result.message_id');
    }

    /** An exception message can carry the request URL, which contains the token. */
    private function scrub(string $message, string $token): string
    {
        return str_replace($token, '[token]', $message);
    }
}

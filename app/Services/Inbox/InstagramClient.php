<?php

namespace App\Services\Inbox;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Messaging (Meta Graph API) send client (MASTER-ONLY, Business Suite Prompt 16). It shares the Meta app already used for WhatsApp, but
 * the account id and access token are the WORKSPACE's own, passed in. Evaluated against folding it into one Meta client with WhatsApp: the request
 * shapes differ (recipient object vs "to" field, different endpoint resource, 24h rule counted differently), so they stay two small classes.
 * Instagram only lets a business reply within 24 hours of the person's last message; Meta rejects anything later and that surfaces as a null here.
 */
class InstagramClient
{
    /** @return ?string Meta's message id, or null on failure (logged, never thrown) */
    public function sendMessage(string $accessToken, string $accountId, string $recipientId, string $text): ?string
    {
        $text = trim($text);
        if ($accessToken === '' || $accountId === '' || $recipientId === '' || $text === '') {
            return null;
        }

        $version = (string) config('services.whatsapp.graph_version', 'v25.0');

        try {
            $r = Http::withToken($accessToken)->timeout(15)->post("https://graph.facebook.com/{$version}/{$accountId}/messages", [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => mb_substr($text, 0, 1000)],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Instagram send failed', ['error' => str_replace($accessToken, '[token]', $e->getMessage())]);

            return null;
        }
        if (! $r->successful()) {
            Log::warning('Instagram send rejected', ['status' => $r->status(), 'body' => $r->json('error.message') ?? 'no detail']);

            return null;
        }

        return $r->json('message_id');
    }

    /** An image / video / audio attachment by URL (Instagram has no document type). @return ?string Meta's message id or null (logged, never thrown) */
    public function sendAttachment(string $accessToken, string $accountId, string $recipientId, string $type, string $url): ?string
    {
        if ($accessToken === '' || $accountId === '' || $recipientId === '' || ! in_array($type, ['image', 'video', 'audio'], true) || $url === '') {
            return null;
        }
        $version = (string) config('services.whatsapp.graph_version', 'v25.0');

        try {
            $r = Http::withToken($accessToken)->timeout(30)->post("https://graph.facebook.com/{$version}/{$accountId}/messages", [
                'recipient' => ['id' => $recipientId],
                'message' => ['attachment' => ['type' => $type, 'payload' => ['url' => $url]]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Instagram attachment failed', ['error' => str_replace($accessToken, '[token]', $e->getMessage())]);

            return null;
        }
        if (! $r->successful()) {
            Log::warning('Instagram attachment rejected', ['status' => $r->status(), 'body' => $r->json('error.message') ?? 'no detail']);

            return null;
        }

        return $r->json('message_id');
    }
}

<?php

namespace App\Services\WhatsApp;

use App\Support\ProviderStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level Meta WhatsApp Cloud API client (BUILD-4 §7). Sends pre-approved
 * TEMPLATE messages — the only kind allowed to reach a user outside the 24-hour
 * customer-service window, which is exactly what lifecycle Autopilot needs.
 *
 * Gated by real config (ProviderStatus): when the keys are absent the client is
 * a safe no-op that returns null, so the rest of the app runs unchanged until
 * the operator goes live. It NEVER throws into the caller — a messaging failure
 * must not break a money path.
 *
 * Endpoint: POST https://graph.facebook.com/{version}/{phone-number-id}/messages
 * Docs: developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
class WhatsAppCloudClient
{
    public function configured(): bool
    {
        return ProviderStatus::isActive('whatsapp');
    }

    /**
     * Send a template message. Returns the provider message id on success, or
     * null when unconfigured / on any failure (logged, never thrown).
     *
     * @param  string  $toE164  recipient in E.164 (digits, no +) e.g. 2348012345678
     * @param  list<array<string,mixed>>  $components  Cloud API template components
     */
    public function sendTemplate(string $toE164, string $template, ?string $lang = null, array $components = []): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        $to = preg_replace('/\D+/', '', $toE164);
        if ($to === '' || $template === '') {
            return null;
        }

        $version = (string) config('services.whatsapp.graph_version', 'v25.0');
        $phoneId = (string) config('services.whatsapp.phone_number_id');
        $token = (string) config('services.whatsapp.access_token');
        $lang ??= (string) config('services.whatsapp.default_lang', 'en');

        $template = [
            'name' => $template,
            'language' => ['code' => $lang],
        ] + ($components !== [] ? ['components' => $components] : []);

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => $template,
                ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send failed', ['error' => $e->getMessage(), 'template' => $template['name'] ?? null]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('WhatsApp send rejected', [
                'status' => $response->status(),
                'body' => $response->json('error.message') ?? $response->body(),
            ]);

            return null;
        }

        return $response->json('messages.0.id');
    }

    /**
     * Send a free-form text reply (Business Suite Prompt 16). Meta only allows this inside the 24-hour customer-service window that opens when the
     * person messages the business; outside it Meta rejects the call and the only option is a pre-approved template (sendTemplate(), untouched).
     * A workspace sends from its OWN WhatsApp Business number: pass its phone-number id and access token. Without them the platform's own
     * configuration is used. Returns Meta's message id, or null on any failure (logged, never thrown, same contract as sendTemplate()).
     */
    public function sendMessage(string $toE164, string $body, ?string $phoneNumberId = null, ?string $accessToken = null): ?string
    {
        $own = $phoneNumberId !== null && $phoneNumberId !== '' && $accessToken !== null && $accessToken !== '';
        if (! $own && ! $this->configured()) {
            return null;
        }

        $to = preg_replace('/\D+/', '', $toE164);
        $body = trim($body);
        if ($to === '' || $body === '') {
            return null;
        }

        $version = (string) config('services.whatsapp.graph_version', 'v25.0');
        $phoneId = $own ? $phoneNumberId : (string) config('services.whatsapp.phone_number_id');
        $token = $own ? $accessToken : (string) config('services.whatsapp.access_token');

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['preview_url' => false, 'body' => mb_substr($body, 0, 4096)],
                ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp reply failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('WhatsApp reply rejected', ['status' => $response->status(), 'body' => $response->json('error.message') ?? $response->body()]);

            return null;
        }

        return $response->json('messages.0.id');
    }

    /**
     * Send any other message type (image, video, audio, document, location, contacts) from a workspace's own number. `$payload` is the type-specific part of
     * the Cloud API body (e.g. ['type' => 'image', 'image' => ['link' => ..., 'caption' => ...]]). Same contract as sendMessage(): Meta's id or null, never thrown.
     */
    public function sendPayload(string $toE164, array $payload, string $phoneNumberId, string $accessToken): ?string
    {
        $to = preg_replace('/\D+/', '', $toE164);
        if ($to === '' || $phoneNumberId === '' || $accessToken === '' || ! isset($payload['type'])) {
            return null;
        }
        $version = (string) config('services.whatsapp.graph_version', 'v25.0');

        try {
            $response = Http::withToken($accessToken)->timeout(30)->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", ['messaging_product' => 'whatsapp', 'to' => $to] + $payload);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media send failed', ['error' => $e->getMessage()]);

            return null;
        }
        if (! $response->successful()) {
            Log::warning('WhatsApp media send rejected', ['status' => $response->status(), 'body' => $response->json('error.message') ?? $response->body()]);

            return null;
        }

        return $response->json('messages.0.id');
    }

    /**
     * Build a template's body `{{1}}, {{2}}, …` parameters as a components array.
     *
     * @param  list<string|int|float>  $bodyParams
     * @return list<array<string,mixed>>
     */
    public static function bodyComponents(array $bodyParams): array
    {
        if ($bodyParams === []) {
            return [];
        }

        return [[
            'type' => 'body',
            'parameters' => array_map(
                fn ($p) => ['type' => 'text', 'text' => (string) $p],
                $bodyParams,
            ),
        ]];
    }
}

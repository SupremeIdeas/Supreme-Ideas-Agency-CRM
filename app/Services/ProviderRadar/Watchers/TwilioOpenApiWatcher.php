<?php

namespace App\Services\ProviderRadar\Watchers;

use App\Services\ProviderRadar\Contracts\ProviderCapabilityWatcherInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Strategy 2 (OpenAPI spec diff) — Stripe's own real changelog covers Twilio
 * too poorly to act on programmatically, but Twilio publishes its actual
 * OpenAPI spec on GitHub (twilio/twilio-oai), which is real, versioned,
 * machine-readable evidence of new endpoints/fields/enum values.
 */
class TwilioOpenApiWatcher implements ProviderCapabilityWatcherInterface
{
    private const URL = 'https://raw.githubusercontent.com/twilio/twilio-oai/main/spec/json/twilio_api_v2010.json';

    public function provider(): string
    {
        return 'twilio';
    }

    public function sourceType(): string
    {
        return self::OPENAPI;
    }

    public function sourceDescription(): string
    {
        return self::URL;
    }

    public function fetchCurrentSignal(): string
    {
        $response = Http::timeout(15)->connectTimeout(5)->get(self::URL);

        if ($response->failed()) {
            throw new RuntimeException('Could not fetch the Twilio OpenAPI spec: HTTP '.$response->status());
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Twilio OpenAPI spec did not return valid JSON.');
        }

        // Recursively key-sort so a hash comparison never flags upstream key
        // reordering (which carries no real capability change) as a diff.
        self::sortKeysRecursively($json);

        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function integratedServiceClass(): ?string
    {
        return \App\Services\SMS\Numbers\TwilioService::class;
    }

    private static function sortKeysRecursively(array &$data): void
    {
        if (array_is_list($data)) {
            foreach ($data as &$value) {
                if (is_array($value)) {
                    self::sortKeysRecursively($value);
                }
            }

            return;
        }

        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::sortKeysRecursively($value);
            }
        }
    }
}

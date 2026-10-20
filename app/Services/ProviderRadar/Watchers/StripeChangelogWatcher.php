<?php

namespace App\Services\ProviderRadar\Watchers;

use App\Services\ProviderRadar\Contracts\ProviderCapabilityWatcherInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Strategy 1 (changelog) — Stripe publishes a real, human-readable changelog.
 * The fetched page IS the evidence a reviewer can open themselves; this
 * watcher only strips HTML tags down to plain text so the diff stays legible,
 * it never summarizes or interprets the content.
 */
class StripeChangelogWatcher implements ProviderCapabilityWatcherInterface
{
    private const URL = 'https://docs.stripe.com/changelog';

    public function provider(): string
    {
        return 'stripe';
    }

    public function sourceType(): string
    {
        return self::CHANGELOG;
    }

    public function sourceDescription(): string
    {
        return self::URL;
    }

    public function fetchCurrentSignal(): string
    {
        $response = Http::timeout(15)->connectTimeout(5)->get(self::URL);

        if ($response->failed()) {
            throw new RuntimeException('Could not fetch the Stripe changelog: HTTP '.$response->status());
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($response->body())) ?? '');
        if ($text === '') {
            throw new RuntimeException('Stripe changelog page returned no readable content.');
        }

        return $text;
    }

    public function integratedServiceClass(): ?string
    {
        return \App\Services\Payments\StripeGateway::class;
    }
}

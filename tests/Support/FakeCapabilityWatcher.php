<?php

namespace Tests\Support;

use App\Services\ProviderRadar\Contracts\ProviderCapabilityWatcherInterface;

/**
 * Test double for a Tier 5 #14 watcher — returns whatever signal the test
 * configures, so the scan pipeline (hash/diff/proposal) can be exercised
 * deterministically without any real network call.
 */
class FakeCapabilityWatcher implements ProviderCapabilityWatcherInterface
{
    public function __construct(
        private string $providerKey,
        private string $signal,
        private string $type = self::CHANGELOG,
    ) {
    }

    public function provider(): string
    {
        return $this->providerKey;
    }

    public function sourceType(): string
    {
        return $this->type;
    }

    public function sourceDescription(): string
    {
        return "fake://{$this->providerKey}";
    }

    public function fetchCurrentSignal(): string
    {
        return $this->signal;
    }

    public function integratedServiceClass(): ?string
    {
        return null;
    }
}

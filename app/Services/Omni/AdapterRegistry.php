<?php

namespace App\Services\Omni;

/** Channel name to adapter (MASTER-ONLY). Adapters register themselves in OmniServiceProvider; the FakeChannel only exists in tests. */
final class AdapterRegistry
{
    /** @var array<string, ChannelAdapter> */
    private array $adapters = [];

    public function register(ChannelAdapter $adapter): void
    {
        $this->adapters[$adapter->channel()] = $adapter;
    }

    public function for(string $channel): ?ChannelAdapter
    {
        return $this->adapters[$channel] ?? null;
    }

    /** @return list<string> */
    public function channels(): array
    {
        return array_keys($this->adapters);
    }
}

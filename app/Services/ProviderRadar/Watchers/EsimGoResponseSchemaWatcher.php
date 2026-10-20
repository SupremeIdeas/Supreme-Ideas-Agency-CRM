<?php

namespace App\Services\ProviderRadar\Watchers;

use App\Services\eSIM\EsimGoService;
use App\Services\ProviderRadar\Contracts\ProviderCapabilityWatcherInterface;
use App\Services\ProviderRadar\ResponseShape;
use RuntimeException;

/**
 * Strategy 3 (response schema-diff) — eSIM Go has no public changelog or
 * published OpenAPI spec to poll. This reuses the real, already-integrated
 * getCatalogue() call (the same one esim:sync uses) and compares only the
 * SHAPE of the response — key names and value types, never the actual
 * wholesale prices or plan data — so a genuinely new field appearing is real
 * evidence, without ever persisting cost data into the radar's own storage
 * (money-safety rule 2 still applies to what this watcher writes to disk).
 */
class EsimGoResponseSchemaWatcher implements ProviderCapabilityWatcherInterface
{
    public function __construct(private EsimGoService $service)
    {
    }

    public function provider(): string
    {
        return 'esimgo';
    }

    public function sourceType(): string
    {
        return self::RESPONSE_SCHEMA;
    }

    public function sourceDescription(): string
    {
        return 'EsimGoService::getCatalogue() response shape';
    }

    public function fetchCurrentSignal(): string
    {
        $catalogue = $this->service->getCatalogue();
        if ($catalogue === []) {
            throw new RuntimeException('eSIM Go catalogue returned no data to derive a shape from.');
        }

        return json_encode(ResponseShape::of($catalogue), JSON_PRETTY_PRINT);
    }

    public function integratedServiceClass(): ?string
    {
        return EsimGoService::class;
    }
}

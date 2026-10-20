<?php

namespace App\Services\ProviderRadar\Watchers;

use App\Services\eSIM\AiraloService;
use App\Services\ProviderRadar\Contracts\ProviderCapabilityWatcherInterface;
use App\Services\ProviderRadar\ResponseShape;
use RuntimeException;

/**
 * Strategy 3 (response schema-diff) — same rationale as EsimGoResponseSchemaWatcher:
 * Airalo has no public changelog or spec worth polling, so this reuses the
 * real getCatalogue() call and diffs only the response's shape.
 */
class AiraloResponseSchemaWatcher implements ProviderCapabilityWatcherInterface
{
    public function __construct(private AiraloService $service)
    {
    }

    public function provider(): string
    {
        return 'airalo';
    }

    public function sourceType(): string
    {
        return self::RESPONSE_SCHEMA;
    }

    public function sourceDescription(): string
    {
        return 'AiraloService::getCatalogue() response shape';
    }

    public function fetchCurrentSignal(): string
    {
        $catalogue = $this->service->getCatalogue();
        if ($catalogue === []) {
            throw new RuntimeException('Airalo catalogue returned no data to derive a shape from.');
        }

        return json_encode(ResponseShape::of($catalogue), JSON_PRETTY_PRINT);
    }

    public function integratedServiceClass(): ?string
    {
        return AiraloService::class;
    }
}

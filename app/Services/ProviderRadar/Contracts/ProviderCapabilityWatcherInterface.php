<?php

namespace App\Services\ProviderRadar\Contracts;

/**
 * Tier 5 #14 (Provider Capability Radar, master-only). One watcher per
 * integrated provider, following this codebase's existing
 * EsimProviderInterface/NumberProviderInterface pattern: the scheduler never
 * touches a concrete implementation, so a watcher is swappable and a
 * provider without a working detection strategy simply isn't registered
 * (never a fake/no-op implementation pretending to watch something).
 */
interface ProviderCapabilityWatcherInterface
{
    public const CHANGELOG = 'changelog';

    public const OPENAPI = 'openapi';

    public const RESPONSE_SCHEMA = 'response-schema';

    /** Stable provider key this watcher belongs to (e.g. 'stripe', 'twilio'). */
    public function provider(): string;

    /** Which detection strategy this is — drives how the diff is computed. */
    public function sourceType(): string;

    /** A stable identifier for what's being watched (a changelog URL, a spec URL, etc.), shown to the reviewer as the evidence source. */
    public function sourceDescription(): string;

    /** Fetch the current state of whatever signal this provider exposes. Raw, unprocessed (or, for a response-schema watcher, the shape — never real values). */
    public function fetchCurrentSignal(): string;

    /**
     * The FQCN of the service class NaaraSim actually uses to integrate with
     * this provider, if one exists — Phase C reflects on its public methods
     * so Claude reasons against what's genuinely integrated today, not a
     * guess. Null when there's no single service class to point at.
     */
    public function integratedServiceClass(): ?string;
}

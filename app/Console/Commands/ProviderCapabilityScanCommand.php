<?php

namespace App\Console\Commands;

use App\Services\ProviderRadar\ProviderCapabilityScanService;
use App\Services\ProviderRadar\Watchers\AiraloResponseSchemaWatcher;
use App\Services\ProviderRadar\Watchers\EsimGoResponseSchemaWatcher;
use App\Services\ProviderRadar\Watchers\StripeChangelogWatcher;
use App\Services\ProviderRadar\Watchers\TwilioOpenApiWatcher;
use Illuminate\Console\Command;

/**
 * provider:capability-scan (Tier 5 #14, master-only). Watches each
 * registered provider for signs of new API capability and turns a real
 * detected change into a reviewable proposal in the admin Updater's
 * Capability Radar tab. Never writes/merges/deploys code on its own.
 *
 * Registered watchers, by detection strategy (audited against
 * config/services.php's full provider list):
 * - changelog:        Stripe (docs.stripe.com/changelog — a real, public,
 *                      human-readable changelog).
 * - openapi:           Twilio (github.com/twilio/twilio-oai — a real,
 *                      versioned, machine-readable spec; Twilio has no
 *                      reliably pollable public changelog feed).
 * - response-schema:  eSIM Go, Airalo — neither publishes a changelog or
 *                      spec; both already have a safe, side-effect-free
 *                      getCatalogue() call this codebase uses for esim:sync,
 *                      reused here to watch for new response fields.
 * Every other configured provider (Paystack, Flutterwave, the SMS/number
 * lanes, etc.) has no watcher registered yet — Paystack's changelog blocks
 * bot access (403) and it has no safe read-only method to poll without
 * side effects; the rest need the same audit before a watcher is added.
 * Flagged as a follow-up rather than guessing at unverified URLs.
 */
class ProviderCapabilityScanCommand extends Command
{
    protected $signature = 'provider:capability-scan';

    protected $description = 'Scan registered providers for new API capability and generate reviewable proposals';

    public function handle(
        ProviderCapabilityScanService $scanner,
        StripeChangelogWatcher $stripe,
        TwilioOpenApiWatcher $twilio,
        EsimGoResponseSchemaWatcher $esimgo,
        AiraloResponseSchemaWatcher $airalo,
    ): int {
        $results = $scanner->scan([$stripe, $twilio, $esimgo, $airalo]);

        foreach ($results as $provider => $outcome) {
            $this->info("{$provider}: {$outcome}");
        }

        return self::SUCCESS;
    }
}

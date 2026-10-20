<?php

namespace App\Services\Bootstrap\Steps;

use App\Models\Bootstrap\BootstrapEntry;
use App\Services\Bootstrap\BootstrapStep;
use App\Support\Bootstrap\CredentialRegistry;
use App\Support\Bootstrap\Census;
use App\Support\FeatureFlags;

/**
 * Bootstrap 4B (verification only): the "credentials-only" contract. Bootstrap NEVER creates a credential, so this step creates nothing and checks that the contract holds:
 * every provider in config/services.php is either in the admin credential schema or says where it is configured, every webhook route is covered by a slot,
 * bootstrap wrote no credential row, and no feature that needs keys is half-running (switched on without its keys).
 */
class CredentialSlotsStep implements BootstrapStep
{
    public function name(): string
    {
        return 'credentials';
    }

    public function plan(string $profile): array
    {
        return [];
    }

    public function apply(string $profile): array
    {
        return ['created' => 0, 'preserved' => 0, 'skipped' => 0];
    }

    public function verify(string $profile): array
    {
        $fail = [];
        $slots = CredentialRegistry::slots();

        foreach (array_keys(Census::serviceEnv()) as $provider) {
            if (! isset($slots[$provider]) && ! isset(CredentialRegistry::ELSEWHERE[$provider])) {
                $fail[] = "config/services.php has a '{$provider}' block with no credential slot and no 'configured elsewhere' note";
            }
        }
        foreach (CredentialRegistry::uncoveredWebhookRoutes() as $route) {
            $fail[] = "webhook route {$route} is not shown in any credential slot (add it to CredentialRegistry::WEBHOOKS or INTERNAL_WEBHOOK_ROUTES)";
        }
        if (BootstrapEntry::query()->where('kind', 'credential')->exists()) {
            $fail[] = 'the bootstrap manifest contains a credential row; bootstrap must never create credentials';
        }
        foreach (FeatureFlags::FEATURES as $key => $f) {
            // "Live" is already defined as switched on AND keys present, so a feature can never half-run; this only reports a switched-on feature that is waiting for keys.
            if (FeatureFlags::adminEnabled($key) && ! FeatureFlags::configured($key) && $f['requires'] !== [] && $profile === 'production' && FeatureFlags::enabled($key)) {
                $fail[] = "feature {$key} reports live without its required keys";
            }
        }

        return $fail;
    }
}

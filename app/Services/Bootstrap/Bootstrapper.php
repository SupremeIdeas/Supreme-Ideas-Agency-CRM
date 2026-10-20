<?php

namespace App\Services\Bootstrap;

use App\Models\Bootstrap\BootstrapEntry;
use App\Services\Bootstrap\Steps\AnnouncementsStep;
use App\Services\Bootstrap\Steps\CredentialSlotsStep;
use App\Services\Bootstrap\Steps\JourneyGoalsStep;
use App\Services\Bootstrap\Steps\NotificationCatalogStep;
use App\Services\Bootstrap\Steps\PricingSafetyStep;
use App\Services\Bootstrap\Steps\PromotionsStep;
use App\Services\Bootstrap\Steps\SettingsDefaultsStep;
use Illuminate\Support\Facades\Schema;

/**
 * Runs the ordered bootstrap steps. Idempotent and non-destructive (rules 1-3 of the blueprint): a step only ever creates what is missing, records it in the manifest,
 * and notes when an admin has since edited it. Nothing here invents a credential.
 */
class Bootstrapper
{
    public const PROFILES = ['local', 'staging', 'production'];

    /** @return array<string, BootstrapStep> */
    public function steps(): array
    {
        $steps = [
            new SettingsDefaultsStep,
            new PricingSafetyStep,
            new PromotionsStep,
            new AnnouncementsStep,
            new JourneyGoalsStep,
            new NotificationCatalogStep,
            new CredentialSlotsStep,
        ];

        $out = [];
        foreach ($steps as $s) {
            $out[$s->name()] = $s;
        }

        return $out;
    }

    /**
     * @return array<string, array{plan?: list<array>, result?: array, failures: list<string>}>
     */
    public function run(string $profile = 'production', ?string $only = null, bool $dryRun = false): array
    {
        $report = [];
        foreach ($this->steps() as $name => $step) {
            if ($only !== null && $only !== $name) {
                continue;
            }
            if ($dryRun) {
                $report[$name] = ['plan' => $step->plan($profile), 'failures' => []];

                continue;
            }
            $result = $step->apply($profile);
            $this->markEdited($name);
            $report[$name] = ['result' => $result, 'failures' => $step->verify($profile)];
        }

        return $report;
    }

    /** @return array<string, list<string>> */
    public function verify(string $profile = 'production', ?string $only = null): array
    {
        $out = [];
        foreach ($this->steps() as $name => $step) {
            if ($only !== null && $only !== $name) {
                continue;
            }
            $out[$name] = $step->verify($profile);
        }

        return $out;
    }

    /** Records which manifest entries the admin has changed since bootstrap wrote them (so the diff view and Setup Health can say "yours"). */
    private function markEdited(string $step): void
    {
        if (! Schema::hasTable('bootstrap_manifest')) {
            return;
        }
        foreach (BootstrapEntry::where('step', $step)->whereNull('admin_modified_at')->get() as $e) {
            $current = $this->currentChecksum($e->kind, $e->item);
            if ($current !== null && $current !== $e->checksum) {
                $e->forceFill(['admin_modified_at' => now()])->save();
            }
        }
    }

    private function currentChecksum(string $kind, string $item): ?string
    {
        if ($kind === 'setting') {
            $row = \App\Models\Setting::query()->where('key', $item)->first();

            return $row ? BootstrapEntry::checksum($row->value['value'] ?? null) : null;
        }

        return null;
    }
}

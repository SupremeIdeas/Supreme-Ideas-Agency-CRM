<?php

namespace App\Support\Verify;

use App\Models\VerifyRun;
use Illuminate\Support\Facades\Schema;

/**
 * The single orchestrator behind `php artisan naara:verify`, the Setup Health "Run checks" button and the daily schedule (engineering blueprint 0.4):
 * every surface runs the same Check classes, so the answer cannot differ between them. Areas with no real check yet report an honest "not built".
 */
class Verifier
{
    public const AREAS = ['payouts', 'wallet', 'rewards', 'chat', 'calls', 'messages', 'preloader', 'media', 'menus', 'notifications', 'email', 'responsive', 'tours', 'brand', 'messaging', 'wl', 'trustpilot', 'omni', 'softphone'];

    /** @return list<class-string<Check>> */
    public static function checks(): array
    {
        return [
            ...(class_exists(Checks\N2NNaaraIdsSound::class) ? [Checks\N2NNaaraIdsSound::class] : []),
            ...(class_exists(Checks\N2NChatGesturesSound::class) ? [Checks\N2NChatGesturesSound::class] : []),
            ...(class_exists(Checks\BrandLinksHealthy::class) ? [Checks\BrandLinksHealthy::class] : []),
            ...(class_exists(Checks\BrandTrialsSound::class) ? [Checks\BrandTrialsSound::class] : []),
            ...(class_exists(Checks\WlFunnelSound::class) ? [Checks\WlFunnelSound::class] : []),
            ...(class_exists(Checks\ConversationRouterMatrixSound::class) ? [Checks\ConversationRouterMatrixSound::class] : []),
            ...(class_exists(Checks\BrandVisibilitySound::class) ? [Checks\BrandVisibilitySound::class] : []),
            ...(class_exists(Checks\OmniCoreSound::class) ? [Checks\OmniCoreSound::class] : []),
            ...(class_exists(Checks\OmniAutomationSound::class) ? [Checks\OmniAutomationSound::class] : []),
            ...(class_exists(Checks\OmniCredentialsStatus::class) ? [Checks\OmniCredentialsStatus::class] : []),
            ...(class_exists(Checks\SoftphoneReadiness::class) ? [Checks\SoftphoneReadiness::class] : []),
            Checks\TrustpilotSound::class,
            Checks\WalletLedgerSound::class,
            Checks\RewardsLedgerSound::class,
            Checks\MediaUploadLimits::class,
            Checks\MediaWebpSupport::class,
            Checks\PayoutsNoLiteralProviderLists::class,
            Checks\PayoutsGatewaysReady::class,
            Checks\PayoutsProbesFresh::class,
            Checks\ChatComposerPanelsSingleOpen::class,
            Checks\ChatNoticesHonest::class,
            Checks\ChatDiscoveryIsOptIn::class,
            Checks\CallsLayerAndTurn::class,
            Checks\MessagesCompliance::class,
            Checks\MenusAdminLinksOpen::class,
            Checks\PreloaderConfigSound::class,
            Checks\NotificationsCataloged::class,
            Checks\ToursIntegrity::class,
            Checks\NotificationEventsFire::class,
            Checks\EmailSecretsAreMasked::class,
            Checks\ResponsiveAuditExists::class,
            ...(class_exists(\App\Support\Business\SystemMoreAppsCheck::class) ? [\App\Support\Business\SystemMoreAppsCheck::class] : []),
            ...(class_exists(Checks\MasterMarketingVideosSound::class) ? [Checks\MasterMarketingVideosSound::class] : []),
            Checks\NotBuiltYet::class,
        ];
    }

    /** @return list<Check> */
    public static function instances(?string $area = null): array
    {
        $out = [];
        foreach (self::checks() as $class) {
            $check = app($class);
            if ($check instanceof Checks\NotBuiltYet) {
                foreach (Checks\NotBuiltYet::PENDING as $a => $phase) {
                    $out[] = new Checks\NotBuiltYet($a, $phase);
                }

                continue;
            }
            $out[] = $check;
        }

        return $area === null ? $out : array_values(array_filter($out, fn (Check $c) => $c->area() === $area));
    }

    /** @return list<array{area: string, check: string, status: string, detail: string, evidence: array}> */
    public static function run(?string $area = null, string $trigger = 'cli'): array
    {
        $rows = [];
        foreach (self::instances($area) as $check) {
            try {
                $r = $check->run();
            } catch (\Throwable $e) {
                $r = CheckResult::fail('The check itself crashed: '.get_class($e).': '.mb_substr($e->getMessage(), 0, 200));
            }
            $rows[] = ['area' => $check->area(), 'check' => $check->name(), 'status' => $r->status, 'detail' => $r->detail, 'evidence' => $r->evidence];
        }

        if (Schema::hasTable('verify_runs')) {
            $now = now();
            foreach ($rows as $row) {
                VerifyRun::create($row + ['trigger' => $trigger, 'ran_at' => $now]);
            }
            VerifyRun::query()->where('ran_at', '<', now()->subDays(30))->delete();
        }

        return $rows;
    }

    /** Latest stored result per check, for the admin panel. @return \Illuminate\Support\Collection<int, VerifyRun> */
    public static function latest()
    {
        if (! Schema::hasTable('verify_runs')) {
            return collect();
        }
        $ids = VerifyRun::query()->selectRaw('MAX(id) as id')->groupBy('area', 'check')->pluck('id');

        return VerifyRun::query()->whereIn('id', $ids)->orderBy('area')->orderBy('check')->get();
    }
}

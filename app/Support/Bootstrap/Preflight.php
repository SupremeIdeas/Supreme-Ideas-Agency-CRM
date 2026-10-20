<?php

namespace App\Support\Bootstrap;

use App\Models\Bootstrap\BootstrapEntry;
use App\Models\JobHeartbeat;
use App\Support\FeatureFlags;
use App\Support\SchedulerHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Config Doctor (Bootstrap 4B.6): side-effect-free checks that stop an invalid setup. Each check returns one status and the single fix.
 *   ok       nothing to do
 *   pending  waiting on the admin (cron, credentials, mail): expected on a fresh install, never an error
 *   warn     works, but not how production should look
 *   fail     must be fixed before "ready" (production refuses to be ready while any critical check fails)
 */
final class Preflight
{
    /** @return list<array{id: string, label: string, status: string, fix: string, critical: bool}> */
    public static function checks(): array
    {
        $prod = app()->environment('production');
        $rows = [];
        $add = function (string $id, string $label, string $status, string $fix = '', bool $critical = false) use (&$rows) {
            $rows[] = compact('id', 'label', 'status', 'fix', 'critical');
        };

        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');
        $local = (bool) preg_match('~//(localhost|127\.|0\.0\.0\.0|.*\.test)~', $url);
        $add('app_url', 'APP_URL is a real https address', $prod ? ($https && ! $local ? 'ok' : 'fail') : ($https && ! $local ? 'ok' : 'warn'), 'Set APP_URL to your live https domain; every webhook and OAuth URL is built from it.', true);
        $add('debug', 'APP_DEBUG is off in production', $prod && config('app.debug') ? 'fail' : 'ok', 'Set APP_DEBUG=false (it prints secrets on errors).', true);

        try {
            DB::connection()->getPdo();
            $add('database', 'Database connection works', 'ok', '', true);
        } catch (\Throwable) {
            $add('database', 'Database connection works', 'fail', 'Check DB_* in .env and that the database server is reachable.', true);
        }

        $add('queue_sync', 'Queue is not "sync" in production', $prod && SchedulerHealth::queueIsSync() ? 'fail' : 'ok', 'Set QUEUE_CONNECTION=database (cPanel) or redis (VPS); sync runs provider calls inside the web request.', true);
        $add('storage', 'storage/ is writable', is_writable(storage_path()) ? 'ok' : 'fail', 'chmod -R ug+rwX storage bootstrap/cache', true);
        $add('storage_link', 'public/storage link exists', file_exists(public_path('storage')) ? 'ok' : 'warn', 'php artisan storage:link (not needed if files are on Wasabi/S3).');

        $beat = null;
        try {
            $beat = Schema::hasTable('job_heartbeats') ? JobHeartbeat::query()->latest('created_at')->first() : null;
        } catch (\Throwable) {
        }
        $fresh = $beat && $beat->created_at && $beat->created_at->gt(now()->subMinutes(10));
        $add('cron', 'Cron is running (heartbeat in the last 10 minutes)', $fresh ? 'ok' : 'pending', 'Add one cron entry: * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1');

        $mail = (string) config('mail.default');
        $add('mail', 'Email transport configured', in_array($mail, ['log', 'array'], true) ? 'pending' : 'ok', 'Admin > Email settings: choose any provider, paste its credentials, press Send test.');

        $disk = (string) config('filesystems.default');
        $add('files', 'File storage is not the local disk', $disk === 'local' ? ($prod ? 'warn' : 'ok') : 'ok', 'Configure Wasabi/S3 (the project standard) or accept local files with the backup caveat.');

        $waiting = [];
        foreach (FeatureFlags::FEATURES as $key => $f) {
            if ($f['requires'] !== [] && FeatureFlags::adminEnabled($key) && ! FeatureFlags::configured($key)) {
                $waiting[] = $f['name'];
            }
        }
        $add('features', 'Features waiting for credentials show "Coming soon" (not half-running)', 'ok', $waiting ? 'Waiting for keys: '.implode(', ', $waiting) : '');

        $slots = CredentialRegistry::slots();
        $saved = count(array_filter($slots, fn ($s) => $s['status'] === 'saved'));
        $add('credentials', "Integrations connected: {$saved} of ".count($slots), $saved > 0 ? 'ok' : 'pending', 'Admin > Providers / Gateways: paste credentials. php artisan naara:credentials:status lists what is waiting.');

        try {
            $ran = Schema::hasTable('bootstrap_manifest') && BootstrapEntry::query()->exists();
        } catch (\Throwable) {
            $ran = false;
        }
        $add('bootstrap', 'Bootstrap defaults applied', $ran ? 'ok' : 'pending', 'php artisan naara:bootstrap');

        // Legal documents ship as drafts (starting points, not legal advice). Never a failure: it is the owner's call and counsel's job.
        try {
            $unreviewed = \App\Support\LegalReview::unreviewed();
        } catch (\Throwable) {
            $unreviewed = [];
        }
        $add('legal_review', 'Legal pages reviewed by counsel ('.(count(\App\Support\LegalContent::slugs()) - count($unreviewed)).' of '.count(\App\Support\LegalContent::slugs()).')', $unreviewed === [] ? 'ok' : 'pending', 'Have a lawyer review Admin > Legal pages (privacy, terms, refund, cookies, data deletion), then press "Mark as reviewed by counsel" on each.');

        $add('timezone', 'Application timezone set', config('app.timezone') ? 'ok' : 'warn', 'Set APP_TIMEZONE.');

        return $rows;
    }

    /** Production is ready only while no critical check fails. */
    public static function ready(?array $checks = null): bool
    {
        foreach ($checks ?? self::checks() as $c) {
            if ($c['critical'] && $c['status'] === 'fail') {
                return false;
            }
        }

        return true;
    }
}

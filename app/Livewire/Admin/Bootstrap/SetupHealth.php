<?php

namespace App\Livewire\Admin\Bootstrap;

use App\Models\Bootstrap\BootstrapEntry;
use App\Services\Bootstrap\Bootstrapper;
use App\Support\Auditor;
use App\Support\Bootstrap\CredentialRegistry;
use App\Support\Bootstrap\Census;
use App\Support\Bootstrap\Preflight;
use App\Support\Bootstrap\ProviderTester;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\ClientError;
use App\Support\SchedulerHealth;
use App\Support\Verify\Verifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > System > Setup Health (MASTER-ONLY, Bootstrap blueprint 4B.3 / section 14). One page that answers "what is done for me and what is waiting on me": the Config
 * Doctor checks, every credential slot with its generated webhook URLs, and what `naara:bootstrap` created (and which of those the admin has since edited).
 * Read-mostly: the actions are re-running the idempotent bootstrap (never overwrites an edit, never invents a credential) and a no-spend "Test connection" per provider.
 */
#[Layout('components.layouts.admin')]
class SetupHealth extends Component
{
    /** @var list<array{kind: string, item: string, note: string}> */
    public array $preview = [];

    public ?string $ranMessage = null;

    /** @var array<string, array{status: string, message: string}> provider => last test result (this page view only; nothing is stored) */
    public array $tests = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
    }

    public function previewBootstrap(): void
    {
        $this->ranMessage = null;
        $this->preview = [];
        foreach ((new Bootstrapper)->run($this->profile(), null, true) as $r) {
            array_push($this->preview, ...($r['plan'] ?? []));
        }
    }

    public function runBootstrap(): void
    {
        $created = 0;
        $failures = 0;
        foreach ((new Bootstrapper)->run($this->profile()) as $r) {
            $created += $r['result']['created'] ?? 0;
            $failures += count($r['failures']);
        }
        Auditor::log('bootstrap.run', null, null, ['created' => $created, 'failures' => $failures]);
        $this->preview = [];
        $this->ranMessage = $created === 0 && $failures === 0 ? 'Everything was already in place. Nothing changed.' : "Added {$created} default(s)".($failures ? "; {$failures} check(s) need attention (see the list)." : '. Nothing you edited was touched.');
    }

    /**
     * No-spend "Test connection" for one provider slot: a documented free read (account, balance or token), never a purchase, send or charge.
     * Super-admin only, throttled per provider so the button cannot be used to hammer a provider's API, and audited. The result carries no credential.
     */
    public function testProvider(string $provider): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
        if (! isset(CredentialRegistry::slots()[$provider])) {
            return;
        }
        $key = 'setup-health-test:'.$provider;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->tests[$provider] = ['status' => 'fail', 'message' => 'Tested a few times already. Wait a minute and try again.'];

            return;
        }
        RateLimiter::hit($key, 60);

        $this->tests[$provider] = ProviderTester::test($provider);
        Auditor::log('bootstrap.provider_test', null, null, ['provider' => $provider, 'result' => $this->tests[$provider]['status']]);
    }

    /** Runs every verify check (read-only) and stores the results; the same classes as `php artisan naara:verify`. */
    public function runChecks(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
        $key = 'setup-health-verify';
        if (RateLimiter::tooManyAttempts($key, 4)) {
            return;
        }
        RateLimiter::hit($key, 60);
        $rows = Verifier::run(null, 'admin');
        Auditor::log('verify.run', null, null, ['checks' => count($rows), 'failed' => count(array_filter($rows, fn ($r) => $r['status'] === 'fail'))]);
    }

    private function profile(): string
    {
        return app()->environment('production') ? 'production' : (app()->environment('staging') ? 'staging' : 'local');
    }

    public function render()
    {
        $checks = Preflight::checks();
        $slots = CredentialRegistry::slots();
        $byGroup = [];
        foreach ($slots as $s) {
            $byGroup[$s['group_label']][] = $s;
        }
        $manifest = Schema::hasTable('bootstrap_manifest')
            ? BootstrapEntry::query()->selectRaw('step, count(*) as total, sum(case when admin_modified_at is not null then 1 else 0 end) as edited')->groupBy('step')->get()
            : collect();

        // Job manifest: every scheduled command in routes/console.php, with its last recorded run and whether it is overdue. A command that is
        // scheduled but not monitored is flagged ("not watched") so a new cron job can never silently run without a heartbeat expectation.
        $watched = SchedulerHealth::report();
        $watchedNames = array_column($watched, 'name');
        $unwatched = array_values(array_filter(array_map(fn ($j) => $j['command'], Census::scheduler()), fn ($c) => ! in_array(SchedulerHealth::commandName($c) ?? $c, $watchedNames, true)));

        $clientErrors = Schema::hasTable('client_errors')
            ? ClientError::query()->where('created_at', '>=', now()->subDay())
                ->selectRaw('fingerprint, kind, route, component, message, count(*) as hits, max(created_at) as last_at')
                ->groupBy('fingerprint', 'kind', 'route', 'component', 'message')->orderByDesc('hits')->limit(10)->get()
            : collect();

        return view('livewire.admin.bootstrap.setup-health', [
            'verifyRows' => Verifier::latest(),
            'clientErrors' => $clientErrors,
            'jobs' => $watched,
            'unwatchedJobs' => array_values(array_unique($unwatched)),
            'checks' => $checks,
            'ready' => Preflight::ready($checks),
            'byGroup' => $byGroup,
            'saved' => count(array_filter($slots, fn ($s) => $s['status'] === 'saved')),
            'total' => count($slots),
            'manifest' => $manifest,
            'elsewhere' => CredentialRegistry::ELSEWHERE,
        ]);
    }
}

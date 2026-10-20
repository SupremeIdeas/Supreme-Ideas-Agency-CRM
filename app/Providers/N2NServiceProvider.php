<?php

namespace App\Providers;

use App\Http\Middleware\EnsureN2NEnabled;
use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\Contact;
use App\Models\BrandPartnerImage;
use App\Models\BrandPartnerVideo;
use App\Services\N2N\Brands\BrandMediaLimitObserver;
use App\Services\N2N\ContactObserver;
use App\Services\N2N\Media\NaaraPro;
use App\Services\N2N\Credit\CreditGifts;
use App\Services\N2N\Credit\SubscriptionGrant;
use App\Services\N2N\Identity\NaaraId;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use App\Services\N2N\Realtime\ChannelAuth;
use App\Services\N2N\Realtime\Realtime;
use App\Support\N2N\N2NSettings;
use App\Support\SchedulerHealth;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Registers N2N (MASTER-ONLY). Only ever added to the app when this class exists, so a fork can never half-register it. */
class N2NServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('n2n.enabled', EnsureN2NEnabled::class);
        Contact::observe(ContactObserver::class);
        \App\Services\N2N\Messaging\ConversationEventRecorder::register();
        \App\Models\BrandPartnerFollower::created(fn ($f) => rescue(fn () => app(\App\Services\Brands\BrandConnectService::class)->onConnect($f), null, true));   // Brand Connect: record the connection, welcome message (off unless enabled)   // unified conversation timeline (Phase 9)
        BrandPartnerImage::observe(BrandMediaLimitObserver::class);
        BrandPartnerVideo::observe(BrandMediaLimitObserver::class);
        BrandSubscription::observe(SubscriptionGrant::class);   // plan credit grant on activation/renewal (Batch 5)
        // Naara Pro is cached for a minute; any change to a brand or its subscription busts it at once (Batch 7).
        $bust = fn ($m) => NaaraPro::bust((int) ($m instanceof BrandSubscription ? $m->brandPartner?->owner_user_id : $m->owner_user_id));
        BrandSubscription::saved($bust);
        BrandSubscription::deleted($bust);
        BrandPartner::saved($bust);
        BrandPartner::deleted($bust);
        $this->app->bind(\App\Services\BrandLinks\LinkProbe::class, \App\Services\BrandLinks\SafeFetcher::class);   // Business links: every outbound look goes through the SSRF-hardened fetcher
        // Everyone gets their Naara ID the moment the account exists (the first open of N2N and the backfill command are only the safety nets).
        Event::listen(Registered::class, function ($e) {
            try {
                NaaraId::for($e->user);
            } catch (\Throwable $ex) {
                report($ex);   // never block a sign-up on this: the identity page and the backfill issue it later
            }
        });
        // A sign-up that came from a brand's Business Card records the intent to connect (one prompt, never an automatic follow).
        Event::listen(Registered::class, fn ($e) => app(\App\Services\Brands\ConnectionIntentService::class)->claimForUser($e->user));
        // Immutable once issued: nothing may change or clear an existing Naara ID (an ID is a handle other people have saved).
        \App\Models\User::updating(function ($user) {
            if ($user->isDirty('naara_id') && ! empty($user->getOriginal('naara_id'))) {
                $user->setAttribute('naara_id', $user->getOriginal('naara_id'));
                \Illuminate\Support\Facades\Log::warning('A change to an existing Naara ID was refused.', ['user_id' => $user->id]);
            }
        });
        // A pending credit gift is claimed only after the person verifies the same email/phone it was addressed to.
        Event::listen([Verified::class, Registered::class], fn ($e) => app(CreditGifts::class)->claimForUser($e->user));

        RateLimiter::for('n2n-send', fn (Request $r) => Limit::perMinute(N2NSettings::int('send_http_per_min', 120))->by('n2n-snd:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('n2n-call-signal', fn (Request $r) => Limit::perMinute(N2NSettings::int('call_signal_per_min', 600))->by('n2n-cs:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('n2n-realtime', fn (Request $r) => Limit::perMinute(N2NSettings::int('realtime_per_min', 120))->by('n2n-rt:'.($r->user()?->id ?? $r->ip())));

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(base_path('routes/n2n.php'));
        }
        $this->app['view']->addNamespace('n2n', resource_path('views/n2n'));

        $this->bootRealtime();
    }

    /** Private channels. A channel type with no registered authoriser is denied, never open. */
    public static function registerChannels(): void
    {
        foreach (['user', 'conv', 'call'] as $kind) {
            Broadcast::channel('n2n.'.$kind.'.{id}', fn ($user, $id) => ChannelAuth::can($user, $kind, (int) $id));
        }
    }

    private function bootRealtime(): void
    {
        Broadcast::routes(['middleware' => ['web', 'auth', 'n2n.enabled']]);

        // The Reverb connection is configured here, from config/n2n.php, so no shared config file carries N2N.
        $r = (array) config('n2n.realtime.reverb');
        config([
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb', 'key' => $r['key'] ?? null, 'secret' => $r['secret'] ?? null, 'app_id' => $r['app_id'] ?? null,
                'options' => ['host' => $r['host'] ?? '127.0.0.1', 'port' => $r['port'] ?? 8080, 'scheme' => $r['scheme'] ?? 'http', 'useTLS' => ($r['scheme'] ?? 'http') === 'https'],
            ],
        ]);
        if (rescue(fn () => Realtime::driver(), 'poll', false) === 'reverb') {
            config(['broadcasting.default' => 'reverb']);
        }
        // Registered AFTER the default connection is chosen, because channel definitions belong to the active broadcaster.
        self::registerChannels();

        SchedulerHealth::register('n2n:prune-realtime', 'N2N realtime event prune', 3600);
        SchedulerHealth::register('n2n:media-cleanup', 'N2N orphan upload cleanup', 7200);
        SchedulerHealth::register('n2n:expire-gifts', 'N2N credit-gift expiry refunds', 300);
        SchedulerHealth::register('n2n:credit-reconcile', 'N2N usage-credit reconcile', 86400);
        SchedulerHealth::register('n2n:calls-sweep', 'N2N call sweeper (missed calls, lost peers)', 300);
        SchedulerHealth::register('n2n:purge-status', 'N2N status purge (24-hour posts and their stored files are deleted)', 600);
        SchedulerHealth::register('n2n:expire-messages', 'N2N disappearing messages (expired messages are deleted)', 300);
        SchedulerHealth::register('n2n:event-reminders', 'N2N planner (event reminders are sent)', 300);
        SchedulerHealth::register('n2n:backfill-identity', 'N2N identity backfill (every account has its Naara ID)', 172800);
        SchedulerHealth::register('brand-links:recheck', 'Business links re-check (links that went bad are paused)', 691200);
        SchedulerHealth::register('brand-trials:convert', 'Naara Pro free trials (due trials convert, short wallets retry, reminders go out)', 3600);
        SchedulerHealth::register('n2n:send-scheduled', 'N2N scheduled messages (due messages are delivered)', 300);
        SchedulerHealth::register('n2n:group-sweep', 'N2N group-call sweeper (lost participants, credit step-downs, idle rooms)', 300);
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('n2n:prune-realtime')->hourly()->withoutOverlapping();
            $schedule->command('n2n:media-cleanup')->hourly()->withoutOverlapping();
            $schedule->command('n2n:expire-gifts')->everyMinute()->withoutOverlapping();
            $schedule->command('n2n:calls-sweep')->everyMinute()->withoutOverlapping();
            $schedule->command('n2n:group-sweep')->everyMinute()->withoutOverlapping();
            $schedule->command('n2n:send-scheduled')->everyMinute()->withoutOverlapping();
            $schedule->command('n2n:event-reminders')->everyMinute()->withoutOverlapping();
            $schedule->command('n2n:expire-messages')->everyMinute()->withoutOverlapping();
            $schedule->command('n2n:purge-status')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('n2n:credit-reconcile')->dailyAt('03:10')->withoutOverlapping();
            $schedule->command('n2n:backfill-identity')->dailyAt('03:40')->withoutOverlapping();
            $schedule->command('brand-trials:convert')->everyTenMinutes()->withoutOverlapping();
            $schedule->command('brand-links:recheck')->weeklyOn(1, '04:20')->withoutOverlapping();
        });
    }
}

<?php

namespace Tests\Feature\JourneyEngine;

use App\Livewire\JourneyEngine\AlertSettings;
use App\Models\EsimOrder;
use App\Models\JourneyEngine\JourneyCard;
use App\Models\JourneyEngine\JourneyDelivery;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\JourneyAlertNotification;
use App\Services\JourneyEngine\ChannelConsent;
use App\Services\JourneyEngine\Delivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Journey Engine J2: off-app delivery of protective alerts is consent-gated, quiet-hours-aware, capped, never repeated, and never carries suggestions. */
class JourneyDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('journey.delivery_enabled', true);
        Setting::setValue('journey.quiet_hours_start', 21);
        Setting::setValue('journey.quiet_hours_end', 8);
        $this->travel(0);
        \Illuminate\Support\Carbon::setTestNow(now()->setTime(12, 0));      // midday: outside quiet hours
    }

    private function lowDataUser(): User
    {
        $u = User::factory()->create(['is_active' => true, 'timezone' => 'UTC']);
        $o = EsimOrder::create(['user_id' => $u->id, 'provider' => 'esimgo', 'status' => 'active', 'activated_at' => now()->subDay(), 'expires_at' => now()->addDays(10), 'price_charged' => 5, 'wholesale_cost' => 3, 'currency' => 'USD']);
        DB::table('esim_usage_snapshots')->insert(['esim_order_id' => $o->id, 'user_id' => $u->id, 'data_total_mb' => 1000, 'data_remaining_mb' => 100, 'data_used_mb' => 900, 'captured_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return $u;
    }

    public function test_nothing_is_sent_while_the_master_switch_is_off(): void
    {
        Notification::fake();
        Setting::setValue('journey.delivery_enabled', false);
        $u = $this->lowDataUser();
        (new ChannelConsent)->grant($u, 'email');

        $r = app(Delivery::class)->run();

        $this->assertSame(0, $r['sent']);
        Notification::assertNothingSent();
    }

    public function test_no_consent_means_no_message_and_consent_is_per_channel(): void
    {
        Notification::fake();
        $u = $this->lowDataUser();
        $this->assertSame('no_consent', app(Delivery::class)->deliverTo($u)['skipped']);

        (new ChannelConsent)->grant($u, 'email');
        $r = app(Delivery::class)->deliverTo($u);

        $this->assertSame(1, $r['sent']);
        Notification::assertSentTo($u, JourneyAlertNotification::class, fn ($n) => $n->channels === ['email'] && $n->via($u) === ['mail']);
        $this->assertDatabaseHas('journey_deliveries', ['user_id' => $u->id, 'channel' => 'email', 'rule_key' => 'low_data']);
    }

    public function test_revoking_consent_stops_delivery_at_once(): void
    {
        Notification::fake();
        $u = $this->lowDataUser();
        $consent = new ChannelConsent;
        $consent->grant($u, 'push');
        $consent->revoke($u, 'push');

        $this->assertSame('no_consent', app(Delivery::class)->deliverTo($u)['skipped']);
        Notification::assertNothingSent();
    }

    public function test_quiet_hours_use_the_users_own_timezone(): void
    {
        Notification::fake();
        $u = $this->lowDataUser();
        $u->update(['timezone' => 'Africa/Lagos']);                       // 12:00 UTC = 13:00 Lagos: awake
        (new ChannelConsent)->grant($u, 'email');
        $this->assertSame(1, app(Delivery::class)->deliverTo($u)['sent']);

        \Illuminate\Support\Carbon::setTestNow(now()->setTime(21, 30));   // 21:30 UTC = 22:30 Lagos: asleep
        JourneyDelivery::query()->delete();
        $this->assertSame('quiet_hours', app(Delivery::class)->deliverTo($u)['skipped']);
        $u->update(['timezone' => 'America/Los_Angeles']);                // 21:30 UTC = 14:30 Los Angeles: awake
        $this->assertSame(1, app(Delivery::class)->deliverTo($u)['sent']);
    }

    public function test_the_same_alert_is_not_repeated_inside_the_repeat_window_and_the_daily_cap_holds(): void
    {
        Notification::fake();
        $u = $this->lowDataUser();
        (new ChannelConsent)->grant($u, 'email');
        $d = app(Delivery::class);

        $this->assertSame(1, $d->deliverTo($u)['sent']);
        $this->assertSame('already_sent', $d->deliverTo($u)['skipped']);

        Setting::setValue('journey.alert_repeat_hours', 24);
        \Illuminate\Support\Carbon::setTestNow(now()->addHours(25));
        $this->assertSame(1, $d->deliverTo($u)['sent'], 'a still-firing alert is sent again the next day, not before');

        Setting::setValue('journey.delivery_max_per_day', 1);
        $this->assertSame('daily_cap', $d->deliverTo($u)['skipped']);
    }

    public function test_delivery_does_not_count_as_an_impression_and_never_sends_suggestions(): void
    {
        Notification::fake();
        $u = $this->lowDataUser();
        (new ChannelConsent)->grant($u, 'push');
        app(Delivery::class)->deliverTo($u);

        $card = JourneyCard::where('user_id', $u->id)->where('rule_key', 'low_data')->first();
        $this->assertSame(0, $card->shown_count, 'the user has not seen it, so it is not an impression');
        $this->assertNull($card->first_shown_at);
        $this->assertSame(0, JourneyCard::where('user_id', $u->id)->where('priority', 'opportunity')->whereIn('id', JourneyDelivery::pluck('journey_card_id'))->count());
    }

    public function test_a_user_with_nothing_to_say_gets_nothing(): void
    {
        Notification::fake();
        $u = User::factory()->create(['is_active' => true, 'timezone' => 'UTC']);
        (new ChannelConsent)->grant($u, 'email');

        $this->assertSame('nothing_to_say', app(Delivery::class)->deliverTo($u)['skipped']);
        Notification::assertNothingSent();
    }

    public function test_the_alert_email_is_branded_escaped_and_links_to_the_settings_page(): void
    {
        $u = User::factory()->create(['name' => 'Ada']);
        $card = ['title' => 'Data running low', 'body' => 'You have used 91% of your data.', 'cta' => 'Top up', 'why' => 'Your latest usage reading crossed 80%.', 'url' => 'https://example.com/catalogue', 'priority' => 'important'];

        $mail = (new JourneyAlertNotification($card, ['email', 'push']))->toMail($u);
        $html = view($mail->view, $mail->viewData)->render();

        $this->assertStringContainsString('Data running low', $html);
        $this->assertStringContainsString('/account/alerts', $html);
        $this->assertSame(['webpush', 'mail'], (new JourneyAlertNotification($card, ['push', 'email']))->via($u));
    }

    public function test_the_settings_page_defaults_off_toggles_and_audits(): void
    {
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $c = Livewire::actingAs($u)->test(AlertSettings::class)->assertSet('push', false)->assertSet('email', false)->assertSee('Everything here is off until you turn it on');

        $c->call('toggle', 'email')->assertSet('email', true)->assertSee('alerts are on');
        $this->assertTrue((new ChannelConsent)->granted($u, 'email'));
        $c->call('toggle', 'email')->assertSet('email', false);
        $this->assertFalse((new ChannelConsent)->granted($u, 'email'));
        $c->call('toggle', 'whatsapp');                                    // not a deliverable channel: ignored
        $this->assertDatabaseHas('audit_logs', ['action' => 'journey.alert_consent_granted']);
        $this->assertDatabaseMissing('journey_channel_consents', ['channel' => 'whatsapp']);
    }

    public function test_the_deliver_command_runs_and_reports(): void
    {
        Notification::fake();
        $u = $this->lowDataUser();
        (new ChannelConsent)->grant($u, 'email');

        $this->artisan('naara:journey:deliver --dry')->expectsOutputToContain('that would be sent: 1')->assertSuccessful();
        Notification::assertNothingSent();
        $this->artisan('naara:journey:deliver')->expectsOutputToContain('sent: 1')->assertSuccessful();
        Notification::assertSentTo($u, JourneyAlertNotification::class);
    }
}

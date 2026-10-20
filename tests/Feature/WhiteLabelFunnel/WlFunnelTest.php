<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Livewire\WlFunnelPurchase;
use App\Models\User;
use App\Models\WlFunnelEvent;
use App\Models\WlFunnelInstance;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelCode;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Support\FeatureFlags;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** White Label funnel Phases 1 and 3: the instance with its ID, the state machine, register attachment, and the wizard's autosave. */
class WlFunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WhiteLabelLicensePlanSeeder::class);
        FeatureFlags::setEnabled('wl_funnel', true);
    }

    private function funnel(): WlFunnel
    {
        return app(WlFunnel::class);
    }

    private function visitor(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function test_the_code_has_the_right_prefix_and_a_working_check_symbol(): void
    {
        $this->assertStringStartsWith('BS-', WlFunnelCode::generate('basic'));
        $this->assertStringStartsWith('BS-', WlFunnelCode::generate('medium'));
        $this->assertStringStartsWith('EX-', WlFunnelCode::generate('extended'));
        $this->assertStringStartsWith('EX-', WlFunnelCode::generate('extended_v2'));
        for ($i = 0; $i < 200; $i++) {
            $c = WlFunnelCode::generate('extended');
            $this->assertTrue(WlFunnelCode::valid($c), $c);
            $this->assertSame($c, WlFunnelCode::normalise(strtolower(str_replace('-', ' ', $c))));
            $bad = substr($c, 0, -1).($c[-1] === '7' ? '8' : '7');
            $this->assertFalse(WlFunnelCode::valid($bad), "$c vs $bad");
        }
        $this->assertFalse(WlFunnelCode::valid('XX-12345678Z'));
    }

    public function test_the_state_machine_table_is_closed_and_terminals_stay_terminal(): void
    {
        foreach (WlFunnelStates::TRANSITIONS as $from => $tos) {
            foreach ($tos as $to) {
                $this->assertArrayHasKey($to, WlFunnelStates::TRANSITIONS, "$from -> $to targets an unknown state");
                $this->assertNotSame($from, $to);
            }
        }
        $this->assertSame([], WlFunnelStates::TRANSITIONS[WlFunnelStates::EXPIRED]);
        $this->assertSame([], WlFunnelStates::TRANSITIONS[WlFunnelStates::REFUNDED]);
        $this->assertTrue(WlFunnelStates::can('landing_clicked', 'registering'));
        $this->assertFalse(WlFunnelStates::can('landing_clicked', 'paid'));
        $this->assertFalse(WlFunnelStates::can('live', 'wizard_in_progress'));
    }

    public function test_an_illegal_transition_is_refused_and_every_legal_one_is_logged(): void
    {
        $inst = $this->funnel()->start('extended', $this->visitor());
        $this->assertSame('registering', $inst->status);
        $this->expectException(\DomainException::class);
        try {
            $this->funnel()->transition($inst, WlFunnelStates::PAID);
        } finally {
            $this->assertSame(2, WlFunnelEvent::where('instance_id', $inst->id)->count());   // landing + registering, nothing for the refused jump
        }
    }

    public function test_start_reuses_the_open_instance_and_only_switches_plan_before_progress(): void
    {
        $v = $this->visitor();
        $a = $this->funnel()->start('basic', $v);
        $b = $this->funnel()->start('extended', $v);
        $this->assertSame($a->id, $b->id);
        $this->assertSame('extended', $b->plan_key_at_landing);
        $this->assertSame($a->public_code, $b->public_code);                // the ID never changes
        $this->assertSame(1, WlFunnelInstance::count());
        $this->expectException(\InvalidArgumentException::class);
        $this->funnel()->start('nope', $v);
    }

    public function test_registration_attaches_the_instance_from_the_cookie_and_other_signups_get_none(): void
    {
        $v = $this->visitor();
        $inst = $this->funnel()->start('extended', $v);
        $r = $this->withCookies([WlFunnel::COOKIE => $v])->post('/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x']);
        $this->assertNotNull(User::where('email', 'ada@example.com')->first(), json_encode(session('errors')?->all()).' '.$r->headers->get('Location'));
        $u = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame($u->id, $inst->fresh()->user_id);
        $this->assertSame('registered', $inst->fresh()->status);

        \Illuminate\Support\Facades\Auth::logout();
        $this->flushSession();
        $this->post('/register', ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x'])->assertRedirect();
        $this->assertNull($this->funnel()->forUser(User::where('email', 'bob@example.com')->firstOrFail()));
        $this->assertSame(1, WlFunnelInstance::count());
    }

    public function test_the_start_route_creates_an_instance_sets_the_cookie_and_sends_visitors_to_register(): void
    {
        $res = $this->get('/white-label/start?plan=extended&utm_source=ad&utm_campaign=oct')->assertRedirect(route('register'));
        $res->assertCookie(WlFunnel::COOKIE);
        $inst = WlFunnelInstance::firstOrFail();
        $this->assertStringStartsWith('EX-', $inst->public_code);
        $this->assertSame(['utm_source' => 'ad', 'utm_campaign' => 'oct'], $inst->utm);
        $this->get('/register')->assertOk()->assertSee($inst->public_code)->assertSee('data-wl-funnel-banner', false);

        $u = User::factory()->create(["email_verified_at" => now(), "is_active" => true]);
        $this->actingAs($u)->get('/white-label/start?plan=basic')->assertRedirect(route('wl-funnel.purchase'));
    }

    public function test_with_the_flag_off_nothing_is_created_and_the_buttons_keep_their_old_target(): void
    {
        FeatureFlags::setEnabled('wl_funnel', false);
        $this->get('/white-label/start?plan=extended')->assertRedirect(route('white-label.landing'));
        $this->assertSame(0, WlFunnelInstance::count());
        $u = User::factory()->create(["email_verified_at" => now(), "is_active" => true]);
        $this->actingAs($u)->get(route('wl-funnel.purchase'))->assertNotFound();
        $this->get('/white-label')->assertOk()->assertDontSee('white-label/start');
    }

    public function test_the_landing_plan_buttons_start_the_funnel_when_it_is_on(): void
    {
        $this->get('/white-label')->assertOk()->assertSee('white-label/start?plan=extended', false);
    }

    public function test_the_wizard_validates_and_autosaves_each_step_and_resumes(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true, 'country_code' => 'NG']);
        $inst = $this->funnel()->start('extended', $this->visitor(), $u);
        $c = Livewire::actingAs($u)->test(WlFunnelPurchase::class)->assertSee($inst->public_code)->assertSet('step', 1);

        $c->set('brand_name', '<b>')->call('next')->assertHasErrors('brand_name')->assertSet('step', 1);
        $c->set('brand_name', 'Ada Connect')->call('next')->assertHasNoErrors()->assertSet('step', 2);
        $c->set('palette_key', 'custom')->set('primary', 'nothex')->call('next')->assertHasErrors()->assertSet('step', 2);
        $c->set('primary', '#0A6E6E')->set('accent', '#D4A017')->call('next')->assertSet('step', 3);
        $c->set('hosting_choice', 'own_vps')->set('disclaimer', false)->call('next')->assertHasErrors('disclaimer');
        $c->set('hosting_choice', 'supreme_ideas_server')->call('next')->assertSet('step', 4);
        $c->set('whatsapp', '12345')->call('next')->assertHasErrors('whatsapp');
        $c->set('whatsapp', '+2348012345678')->set('whatsapp_consent', false)->call('next')->assertHasErrors('whatsapp_consent');
        $c->set('whatsapp_consent', true)->call('next')->assertSet('step', 5);
        $c->set('billing_address', '1 Market Rd, Onitsha')->set('billing_country', 'ng')->call('next')->assertHasNoErrors()->assertSet('step', 6);
        $c->set('plan_key', 'extended')->call('next')->assertSet('step', 7)->assertSee('$5,000')->assertSee('Payment is not open yet');

        $d = $inst->fresh();
        $this->assertSame('Ada Connect', $d->draft['brand_name']);
        $this->assertSame('+2348012345678', $d->draft['whatsapp']);
        $this->assertSame('NG', $d->draft['billing_country']);
        $this->assertSame('wizard_in_progress', $d->status);
        $this->assertSame([], $this->funnel()->missing($d));

        $c->call('saveDraft')->assertSee('Saved.');
        $this->assertSame('draft_saved', $d->fresh()->status);

        // Resume after a "reload": a new component starts on the saved step with every answer back.
        Livewire::actingAs($u)->test(WlFunnelPurchase::class)->assertSet('step', 7)->assertSet('brand_name', 'Ada Connect')->assertSet('billing_country', 'NG');
    }

    public function test_a_brand_name_taken_by_someone_else_is_refused(): void
    {
        $a = User::factory()->create(["email_verified_at" => now(), "is_active" => true]);
        $b = User::factory()->create(["email_verified_at" => now(), "is_active" => true]);
        $ia = $this->funnel()->start('basic', $this->visitor(), $a);
        $ib = $this->funnel()->start('basic', $this->visitor(), $b);
        $this->funnel()->saveStep($ia, 'brand', ['brand_name' => 'Zed Mobile']);
        $this->expectException(ValidationException::class);
        $this->funnel()->saveStep($ib, 'brand', ['brand_name' => 'zed  mobile']);
    }

    public function test_review_prices_come_from_the_plan_rows_and_basic_shows_the_balance_to_extended(): void
    {
        $u = User::factory()->create(["email_verified_at" => now(), "is_active" => true]);
        $inst = $this->funnel()->start('basic', $this->visitor(), $u);
        $this->funnel()->saveStep($inst, 'plan', ['plan_key' => 'basic']);
        $r = $this->funnel()->review($inst->fresh());
        $basic = \App\Models\WhiteLabelLicensePlan::where('key', 'basic')->first();
        $ext = \App\Models\WhiteLabelLicensePlan::where('key', 'extended')->first();
        $this->assertSame((float) $basic->price_usd, $r['price_usd']);
        $this->assertSame(round((float) $ext->price_usd - (float) $basic->price_usd, 2), $r['balance_to_extended']);
        $basic->update(['price_usd' => 1111]);
        $this->assertSame(1111.0, $this->funnel()->review($inst->fresh())['price_usd']);   // never a hard-coded price
    }

    public function test_the_wizard_needs_no_merchant_status_and_grants_none(): void
    {
        $u = User::factory()->create(["email_verified_at" => now(), "is_active" => true]);
        $this->funnel()->start('extended', $this->visitor(), $u);
        $this->actingAs($u)->get(route('wl-funnel.purchase'))->assertOk();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('merchants') && \App\Models\Merchant::where('user_id', $u->id)->exists());
    }

    public function test_old_drafts_expire_but_issued_licences_never_do_and_verify_passes(): void
    {
        $old = $this->funnel()->start('basic', $this->visitor());
        $old->forceFill(['expires_at' => now()->subDay()])->save();
        $paid = $this->funnel()->start('extended', $this->visitor());
        $paid->forceFill(['status' => WlFunnelStates::LICENSE_ISSUED, 'expires_at' => now()->subDay()])->save();
        $this->artisan('wl-funnel:expire')->expectsOutputToContain('Expired 1')->assertSuccessful();
        $this->assertSame('expired', $old->fresh()->status);
        $this->assertSame('license_issued', $paid->fresh()->status);
        $this->assertSame('pass', (new \App\Support\Verify\Checks\WlFunnelSound)->run()->status);
    }
}

<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Models\ManualPaymentAccount;
use App\Models\ManualPaymentRate;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WlFunnelInstance;
use App\Models\WlFunnelManualPayment as P;
use App\Models\WlInvoice;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelManualPayments;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Services\WhiteLabel\Funnel\WlInvoiceIssuer;
use App\Services\WhiteLabel\Funnel\WlInvoiceNumbering;
use App\Services\WhiteLabel\Funnel\WlInvoiceSettings as S;
use App\Support\FeatureFlags;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Funnel documents, setup details, wallet payment and the admin funnel tab (Phases 4b, 6.2 to 6.5, 6B). */
class WlFunnelDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WhiteLabelLicensePlanSeeder::class);
        FeatureFlags::setEnabled('wl_funnel', true);
        FeatureFlags::setEnabled('wl_manual_payments', true);
        \App\Models\Setting::setValue('wl.rates.use_grey_live', false);
        Storage::fake(\App\Support\MediaStorage::privateDisk());
        ManualPaymentRate::create(['from_currency' => 'GBP', 'to_currency' => 'USD', 'rate' => 1.3036, 'source' => ManualPaymentRate::SOURCE_MANUAL, 'as_of' => now()]);
        ManualPaymentAccount::create(['currency' => 'USD', 'kind' => 'bank', 'label' => 'USD', 'fields' => [['label' => 'Account', 'value' => '123']], 'enabled' => true, 'sort' => 1]);
        ManualPaymentAccount::create(['currency' => 'GBP', 'kind' => 'bank', 'label' => 'GBP', 'fields' => [['label' => 'IBAN', 'value' => 'GB00']], 'enabled' => true, 'sort' => 1]);
        S::put('seller_name', 'Acme Seller Ltd');
    }

    private function buyer(string $plan = 'basic', string $hosting = 'supreme_ideas_server'): WlFunnelInstance
    {
        $u = User::factory()->create();
        $inst = app(WlFunnel::class)->start($plan, bin2hex(random_bytes(8)), $u);
        $inst->forceFill(['user_id' => $u->id, 'status' => WlFunnelStates::DRAFT_SAVED, 'draft' => ['brand_name' => 'Zenith Cloud', 'palette_key' => 'x', 'hosting_choice' => $hosting, 'contact_preference' => 'notification', 'billing_name' => 'Zed Co', 'billing_email' => 'zed@example.com', 'billing_address' => '1 Road', 'plan_key' => $plan, 'whatsapp' => '+2348012345678']])->save();

        return $inst->fresh();
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('p'.random_int(1, 99999999).'.png', (string) ob_get_clean());
    }

    private function admin(): User
    {
        return User::factory()->create(['password' => bcrypt('S3cret-pass')])->assignRole('admin');
    }

    private function approved(string $plan = 'basic', string $cur = 'USD', string $hosting = 'supreme_ideas_server'): array
    {
        $inst = $this->buyer($plan, $hosting);
        $svc = app(WlFunnelManualPayments::class);
        $pay = $svc->begin($inst, $cur);
        $svc->submitProof($pay, $inst->user, [$this->png()], 'Zed', null, null, null);
        $svc->approve($pay->fresh(), $this->admin(), (float) $pay->expected_amount);

        return [$inst->fresh(), $pay->fresh()];
    }

    public function test_approving_a_basic_payment_issues_invoice_receipt_and_a_balance_invoice(): void
    {
        [$inst, $pay] = $this->approved('basic');
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->first();
        $inv = WlInvoice::where('source_key', 'mp:'.$pay->id.':invoice')->firstOrFail();
        $this->assertSame('paid', $inv->status);
        $this->assertSame((float) $plan->price_usd, (float) $inv->total);
        $this->assertSame(0.0, (float) $inv->balance_due);
        $this->assertSame('Acme Seller Ltd', $inv->seller_snapshot['name']);
        $this->assertSame('Zed Co', $inv->buyer_snapshot['name']);
        $rct = WlInvoice::where('source_key', 'mp:'.$pay->id.':receipt')->firstOrFail();
        $this->assertSame('receipt', $rct->doc_type);
        $bal = WlInvoice::where('source_key', 'mp:'.$pay->id.':balance')->firstOrFail();
        $this->assertSame('issued', $bal->status);
        $this->assertSame((float) WhiteLabelLicensePlan::balanceToExtended($plan), (float) $bal->balance_due);
        $this->assertNotNull($bal->due_at);
        $this->assertSame(3, WlInvoice::count());
    }

    public function test_funnel_stats_count_buyers_revenue_and_what_is_still_owed(): void
    {
        $this->approved('basic');
        $this->buyer('basic');
        $s = \App\Services\WhiteLabel\Funnel\WlFunnelStats::summary();
        $this->assertSame(2, $s['started']);
        $this->assertSame(1, $s['paid']);
        $this->assertSame(50.0, $s['conversion']);
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->first();
        $this->assertSame((float) $plan->price_usd, $s['revenue']);
        $this->assertSame((float) WhiteLabelLicensePlan::balanceToExtended($plan), $s['outstanding']);
        \Livewire\Livewire::actingAs($this->admin())->test(\App\Livewire\Admin\WlFunnelOverview::class)->assertSee('Still owed')->assertSee('50% of starts');
    }

    public function test_admin_can_cancel_a_balance_invoice_with_a_credit_note_from_the_funnel_tab(): void
    {
        [$inst, $pay] = $this->approved('basic');
        $bal = WlInvoice::where('source_key', 'mp:'.$pay->id.':balance')->firstOrFail();
        $c = \Livewire\Livewire::actingAs($this->admin())->test(\App\Livewire\Admin\WlFunnelOverview::class)->call('view', $inst->id)
            ->assertSee('Cancel with credit note')
            ->set('voiding', $bal->id)->call('voidInvoice')->assertSet('error', 'Say why this document is being cancelled.')
            ->set('voidReason', 'Customer upgraded elsewhere')->call('voidInvoice')->assertSet('voiding', null);
        $this->assertSame('void', $bal->fresh()->status);
        $this->assertSame(1, WlInvoice::where('doc_type', 'credit_note')->count());
        $c->assertSee('CN-');
    }

    public function test_an_extended_plan_has_no_balance_invoice_and_issuing_is_idempotent(): void
    {
        [, $pay] = $this->approved('extended');
        $this->assertNull(WlInvoice::where('source_key', 'mp:'.$pay->id.':balance')->first());
        app(WlInvoiceIssuer::class)->forApprovedPayment($pay);
        app(WlInvoiceIssuer::class)->forApprovedPayment($pay);
        $this->assertSame(2, WlInvoice::count(), 'invoice and receipt, once');
    }

    public function test_numbers_are_gapless_per_series_and_follow_the_admin_format(): void
    {
        S::put('format_INV', 'ACME-{YYYY}-{0000}');
        $a = WlInvoiceNumbering::next('INV');
        $b = WlInvoiceNumbering::next('INV');
        $this->assertSame('ACME-'.now()->format('Y').'-0001', $a);
        $this->assertSame('ACME-'.now()->format('Y').'-0002', $b);
        $this->assertSame('RCT-'.now()->format('Y').'-000001', WlInvoiceNumbering::next('RCT'), 'a separate series has its own counter');
    }

    public function test_a_gbp_payment_records_the_locked_rate_and_a_small_shortfall_is_an_adjustment_line(): void
    {
        $inst = $this->buyer('basic');
        $svc = app(WlFunnelManualPayments::class);
        $pay = $svc->begin($inst, 'GBP');
        $svc->submitProof($pay, $inst->user, [$this->png()], 'Zed', null, null, null);
        $received = round((float) $pay->expected_amount * 0.997, 2);   // inside the 99.5% tolerance
        $svc->approve($pay->fresh(), $this->admin(), $received);
        $inv = WlInvoice::where('source_key', 'mp:'.$pay->id.':invoice')->firstOrFail();
        $this->assertSame('adjustment', $inv->lines[1]['kind']);
        $this->assertSame(round($received * 1.3036, 2), (float) $inv->total);
        $this->assertSame('GBP', $inv->payments[0]['paid_currency']);
        $this->assertSame(1.3036, $inv->payments[0]['rate_used']);
        $this->assertEqualsWithDelta(array_sum(array_column($inv->lines, 'amount')), (float) $inv->subtotal, 0.001);
    }

    public function test_issued_documents_do_not_change_when_settings_or_prices_change_later(): void
    {
        [, $pay] = $this->approved('basic');
        $inv = WlInvoice::where('source_key', 'mp:'.$pay->id.':invoice')->firstOrFail();
        S::put('seller_name', 'Someone Else Inc');
        WhiteLabelLicensePlan::where('key', 'basic')->update(['price_usd' => 9999]);
        $fresh = WlInvoice::find($inv->id);
        $this->assertSame('Acme Seller Ltd', $fresh->seller_snapshot['name']);
        $this->assertSame((float) $inv->total, (float) $fresh->total);
        $this->assertStringContainsString('Acme Seller Ltd', $this->pdfText($fresh));
    }

    private function pdfText(WlInvoice $inv): string
    {
        return view('pdf.wl-invoice', ['inv' => $inv, 'brand' => 'X'])->render();
    }

    public function test_the_pdf_renders_and_escapes_hostile_text(): void
    {
        [$inst, $pay] = $this->approved('basic');
        $inv = WlInvoice::where('source_key', 'mp:'.$pay->id.':invoice')->firstOrFail();
        $pdf = app(WlInvoiceIssuer::class)->pdf($inv);
        $this->assertStringStartsWith('%PDF', $pdf);
        $inv->forceFill(['buyer_snapshot' => array_merge($inv->buyer_snapshot, ['name' => '<script>alert(1)</script>'])]);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $this->pdfText($inv));
    }

    public function test_the_public_page_and_pdf_need_the_unguessable_token_and_the_owner_download_needs_the_owner(): void
    {
        [$inst, $pay] = $this->approved('basic');
        $inv = WlInvoice::where('source_key', 'mp:'.$pay->id.':invoice')->firstOrFail();
        $token = app(WlInvoiceIssuer::class)->newPublicToken($inv);
        $this->get(route('wl-invoice.show', $token))->assertOk()->assertSee($inv->number)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('wl-invoice.pdf', $token))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/inv/'.str_repeat('a', 48))->assertNotFound();
        $this->assertNotSame($token, (string) $inv->fresh()->getRawOriginal('public_token_hash'), 'only a hash is stored');
        $this->actingAs($inst->user)->get(route('wl-invoice.own', $inv->id))->assertOk();
        $r = $this->actingAs(User::factory()->create())->get(route('wl-invoice.own', $inv->id));
        $this->assertContains($r->status(), [302, 404]);   // never the document
        $this->assertNotSame('application/pdf', $r->headers->get('Content-Type'));
    }

    public function test_the_retry_command_issues_documents_for_an_approved_payment_that_missed_them(): void
    {
        [, $pay] = $this->approved('extended');
        WlInvoice::query()->delete();
        $this->artisan('wl-invoices:retry')->assertSuccessful();
        $this->assertSame(2, WlInvoice::count());
    }

    public function test_setup_details_for_an_own_server_are_stored_encrypted_and_revealed_only_with_password_and_reason(): void
    {
        [$inst] = $this->approved('basic', 'USD', 'own_vps');
        $this->actingAs($inst->user)->get(route('wl-funnel.setup'))->assertOk()->assertSee('temporary, limited account');
        $this->actingAs($inst->user)->post(route('wl-funnel.setup.store'), ['whatsapp_number' => '+2348012345678', 'hosting_host' => 'srv.example.com', 'hosting_username' => 'setupuser', 'hosting_password' => 'Sup3r-secret'])->assertRedirect(route('wl-funnel.setup'));
        $license = WhiteLabelInstance::whereNotNull('license_key')->firstOrFail();
        $this->assertStringNotContainsString('Sup3r-secret', (string) \DB::table('white_label_project_intakes')->value('hosting_password'));
        $this->assertSame(WlFunnelStates::SETUP, $inst->fresh()->status);

        $admin = $this->admin();
        $c = \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\WlFunnelOverview::class)->call('view', $inst->id)->assertDontSee('Sup3r-secret');
        $c->set('password', 'wrong')->set('reason', 'x')->call('reveal', 'server')->assertSee('password is not correct')->assertDontSee('Sup3r-secret');
        $c->set('password', 'S3cret-pass')->set('reason', '')->call('reveal', 'server')->assertSee('Say why')->assertDontSee('Sup3r-secret');
        $c->set('password', 'S3cret-pass')->set('reason', 'Starting the install')->call('reveal', 'server')->assertSee('Sup3r-secret');
        $c->set('password', 'S3cret-pass')->set('reason', 'Check the key')->call('reveal', 'license_key')->assertSee($license->license_key);
        $this->assertSame(2, \App\Models\AuditLog::query()->where('action', 'wl.funnel_secret_revealed')->count());

        $c->call('markComplete')->assertHasNoErrors();
        $this->assertSame(WlFunnelStates::LIVE, $inst->fresh()->status);
        \Illuminate\Support\Facades\Date::setTestNow(now()->addDays(8));
        $this->artisan('wl-secrets:purge')->assertSuccessful();
        $this->assertNull(\DB::table('white_label_project_intakes')->value('hosting_password'));
        \Illuminate\Support\Facades\Date::setTestNow();
    }

    public function test_a_supreme_ideas_hosting_buyer_is_never_asked_for_a_login(): void
    {
        [$inst] = $this->approved('basic');
        $this->actingAs($inst->user)->get(route('wl-funnel.setup'))->assertOk()->assertDontSee('Password or SSH key')->assertSee('never ask for a server login');
        $this->actingAs($inst->user)->post(route('wl-funnel.setup.store'), ['whatsapp_number' => 'not-a-number'])->assertSessionHasErrors('whatsapp_number');
    }

    public function test_the_setup_page_is_unavailable_before_payment_is_approved(): void
    {
        $inst = $this->buyer();
        $this->actingAs($inst->user)->get(route('wl-funnel.setup'))->assertNotFound();
    }

    public function test_paying_from_the_wallet_issues_once_and_never_charges_twice(): void
    {
        $inst = $this->buyer('basic');
        $price = (float) WhiteLabelLicensePlan::where('key', 'basic')->value('price_usd');
        app(\App\Services\Wallet\WalletService::class)->credit($inst->user, $price + 10, 'USD', ['reference' => 'seed-'.$inst->id, 'description' => 'seed']);
        $svc = app(WlFunnelManualPayments::class);
        $one = $svc->payWithWallet($inst, $inst->user);
        $two = $svc->payWithWallet($inst->fresh(), $inst->user);
        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, WhiteLabelInstance::count());
        $this->assertEqualsWithDelta(10.0, app(\App\Services\Wallet\WalletService::class)->spendableUsd($inst->user->fresh()), 0.001);
        $this->assertGreaterThanOrEqual(2, WlInvoice::count());
        $this->assertSame(WlFunnelStates::LICENSE_ISSUED, $inst->fresh()->status);
    }

    public function test_the_wallet_refuses_when_the_balance_is_short_and_nothing_is_issued(): void
    {
        $inst = $this->buyer('basic');
        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        try {
            app(WlFunnelManualPayments::class)->payWithWallet($inst, $inst->user);
        } finally {
            $this->assertSame(0, WhiteLabelInstance::count());
        }
    }

    public function test_the_admin_invoice_settings_page_saves_and_previews_a_sample(): void
    {
        $admin = $this->admin();
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\WlInvoiceSettingsPage::class)->set('sellerName', 'New Legal Name')->set('formats.INV', 'X-{YYYY}-{0000}')->call('save')->assertHasNoErrors();
        $this->assertSame('New Legal Name', S::sellerName());
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\WlInvoiceSettingsPage::class)->set('formats.INV', 'no counter')->call('save')->assertHasErrors('formats.INV');
        $this->actingAs($admin)->get(route('admin.white-label.invoice-preview'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get(route('admin.white-label.funnel'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('admin.white-label.funnel'))->assertNotFound();
    }

    public function test_the_pay_page_shows_the_payee_note_from_the_account_and_hardcodes_nothing(): void
    {
        $inst = $this->buyer();
        ManualPaymentAccount::where('currency', 'USD')->update(['payee_note' => 'Pay Acme Seller Ltd directly']);
        $c = \Livewire\Livewire::actingAs($inst->user)->test(\App\Livewire\WlFunnelPay::class)->call('choose', 'USD');
        $c->assertSee('Pay Acme Seller Ltd directly')->assertDontSee('CEO');
        ManualPaymentAccount::where('currency', 'USD')->update(['payee_note' => null]);
        \Livewire\Livewire::actingAs($inst->user)->test(\App\Livewire\WlFunnelPay::class)->call('choose', 'USD')->assertDontSee('data-wl-payee-note', false)->assertDontSee('CEO');
    }

    public function test_verify_flags_an_approved_payment_without_an_invoice_and_passes_when_issued(): void
    {
        [, $pay] = $this->approved('extended');
        $this->assertSame('pass', (new \App\Support\Verify\Checks\WlFunnelSound)->run()->status);
        WlInvoice::query()->delete();
        $this->assertSame('fail', (new \App\Support\Verify\Checks\WlFunnelSound)->run()->status);
    }

    /** The licence a funnel purchase issues must be exactly what a deployed fork activates with: key -> activate -> token -> distribution API, with the tier the plan promised. */
    public function test_a_funnel_licence_activates_like_any_other_licence_and_reaches_the_update_api(): void
    {
        \App\Models\Setting::setValue('white_label_api.enabled', true);
        [$inst, $pay] = $this->approved('basic');
        $license = WhiteLabelInstance::findOrFail($pay->white_label_instance_id);
        $this->assertSame(WhiteLabelInstance::ACQUISITION_LANDING_FUNNEL, $license->acquisition_method);
        $this->assertSame(WhiteLabelInstance::ACTIVE, $license->status);
        $this->assertSame(WhiteLabelInstance::TIER_NORMAL, $license->tier);
        $this->assertSame(WhiteLabelInstance::LEVEL_BASIC, $license->entitlement_level);
        $this->assertNotEmpty($license->license_key);

        $res = $this->postJson('/api/v1/white-label/activate', ['license_key' => $license->license_key, 'current_version' => '2026.09.01-1', 'domain' => 'https://zenith.example.com']);
        $res->assertOk()->assertJsonStructure(['token', 'instance' => ['brand_name', 'slug', 'tier', 'status']]);
        $this->assertSame('Zenith Cloud', $res->json('instance.brand_name'));
        $this->assertSame('normal', $res->json('instance.tier'));

        $this->withToken($res->json('token'))->getJson('/api/v1/white-label/updates/check?current_version=2026.09.01-1&product=naarasim-whitelabel')->assertOk();
        $this->assertSame(1, $license->fresh()->tokens()->count());

        // A wrong key is the same generic refusal as for any licence; a regenerated key kills the old token.
        $this->postJson('/api/v1/white-label/activate', ['license_key' => 'NAARA-ZZZZ-ZZZZ-ZZZZ'])->assertStatus(403)->assertJson(['message' => 'Invalid or inactive license key.']);
        $this->app['auth']->forgetGuards();   // the token request left the API guard holding the instance
        app(\App\Services\Updater\WhiteLabelLicenseService::class)->issueLicense($license->fresh(), (string) $license->tier);
        $this->assertSame(0, $license->fresh()->tokens()->count(), 'the old token is dead');
    }

    public function test_an_extended_funnel_purchase_activates_fully_unlocked(): void
    {
        \App\Models\Setting::setValue('white_label_api.enabled', true);
        [, $pay] = $this->approved('extended');
        $license = WhiteLabelInstance::findOrFail($pay->white_label_instance_id);
        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $license->tier);
        $this->assertSame(WhiteLabelInstance::LEVEL_FULL, $license->entitlement_level);
        $this->postJson('/api/v1/white-label/activate', ['license_key' => $license->license_key])->assertOk()->assertJsonPath('instance.tier', 'extended');
    }

    public function test_a_rejected_or_unapproved_payment_never_yields_a_licence_that_can_activate(): void
    {
        \App\Models\Setting::setValue('white_label_api.enabled', true);
        $inst = $this->buyer('basic');
        $svc = app(WlFunnelManualPayments::class);
        $pay = $svc->begin($inst, 'USD');
        $svc->submitProof($pay, $inst->user, [$this->png()], 'Zed', null, null, null);
        $this->assertSame(0, WhiteLabelInstance::count());
        $svc->reject($pay->fresh(), $this->admin(), 'Unreadable');
        $this->assertSame(0, WhiteLabelInstance::count());
        $this->postJson('/api/v1/white-label/activate', ['license_key' => 'NAARA-AAAA-BBBB-CCCC'])->assertStatus(403);
    }
}

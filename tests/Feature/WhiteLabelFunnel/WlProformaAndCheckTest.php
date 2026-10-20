<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Livewire\Admin\WlFunnelCheck;
use App\Models\ManualPaymentAccount;
use App\Models\ManualPaymentRate;
use App\Models\User;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WlFunnelInstance;
use App\Models\WlInvoice;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelManualPayments;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Services\WhiteLabel\Funnel\WlInvoiceSettings as S;
use App\Support\FeatureFlags;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Funnel proforma on the payment instruction card and the admin Funnel check and quote simulator (Phases 6B and 10, MASTER-ONLY). */
class WlProformaAndCheckTest extends TestCase
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

    private function buyer(): WlFunnelInstance
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $inst = app(WlFunnel::class)->start('basic', bin2hex(random_bytes(8)), $u);
        $inst->forceFill(['user_id' => $u->id, 'status' => WlFunnelStates::DRAFT_SAVED, 'draft' => ['brand_name' => 'Zenith Cloud', 'palette_key' => 'x', 'hosting_choice' => 'supreme_ideas_server', 'contact_preference' => 'notification', 'billing_name' => 'Zed Co', 'billing_email' => 'zed@example.com', 'billing_address' => '1 Road', 'plan_key' => 'basic', 'whatsapp' => '+2348012345678']])->save();

        return $inst->fresh();
    }

    public function test_the_pay_page_offers_a_proforma_that_is_issued_once_and_is_not_a_tax_invoice(): void
    {
        $inst = $this->buyer();
        $pay = app(WlFunnelManualPayments::class)->begin($inst, 'GBP');
        $this->actingAs($inst->user)->get('/account/white-label-pay')->assertOk()->assertSee('Download proforma invoice');

        $r = $this->actingAs($inst->user)->get('/account/white-label-pay/proforma')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $pf = WlInvoice::query()->where('doc_type', 'proforma')->get();
        $this->assertCount(1, $pf);
        $d = $pf->first();
        $this->assertSame('mp:'.$pay->id.':proforma', $d->source_key);
        $this->assertSame('PRO', $d->series);
        $this->assertStringContainsString('PRO-', $d->number);
        $this->assertEqualsWithDelta(0.0, (float) $d->amount_paid, 0.001);
        $this->assertStringContainsString($pay->reference_code, (string) $d->terms);
        $this->assertStringContainsString('not a tax invoice', (string) $d->terms);
        $this->assertSame('Zed Co', $d->buyer_snapshot['name']);
        $this->assertSame('GBP', $d->meta['currency']);

        $this->actingAs($inst->user)->get('/account/white-label-pay/proforma')->assertOk();   // a second download is the same document
        $this->assertSame(1, WlInvoice::query()->where('doc_type', 'proforma')->count());
        $this->assertSame(0, WlInvoice::query()->where('doc_type', 'invoice')->count(), 'a proforma never takes an invoice number');
    }

    public function test_the_proforma_needs_a_started_payment_and_a_signed_in_buyer(): void
    {
        $inst = $this->buyer();
        $this->actingAs($inst->user)->get('/account/white-label-pay/proforma')->assertNotFound();   // no payment started yet
        auth()->logout();
        $this->get('/account/white-label-pay/proforma')->assertRedirect();
        $other = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->actingAs($other)->get('/account/white-label-pay/proforma')->assertStatus(404)->assertDontSee('PRO-');
    }

    public function test_the_funnel_check_reports_readiness_and_simulates_a_quote_without_saving_anything(): void
    {
        $a = User::factory()->create(['email_verified_at' => now(), 'is_active' => true])->assignRole('admin');
        $this->actingAs(User::factory()->create(['email_verified_at' => now(), 'is_active' => true]))->get('/adminmaster/white-label/funnel-check')->assertNotFound();
        $this->actingAs($a)->get('/adminmaster/white-label/funnel-check')->assertOk()->assertSee('Funnel check')->assertSee('At least one way to pay');

        $plan = WhiteLabelLicensePlan::query()->orderBy('sort_order')->first();
        $before = [WlInvoice::count(), \App\Models\WlFunnelManualPayment::count(), ManualPaymentRate::count()];
        Livewire::actingAs($a)->test(WlFunnelCheck::class)->set('plan', $plan->key)->set('currency', 'GBP')->call('simulate')->assertSee('Buyer sends')->assertSee('1.3036');
        Livewire::actingAs($a)->test(WlFunnelCheck::class)->set('plan', $plan->key)->set('currency', 'EUR')->call('simulate')->assertSee('rate', false);   // no EUR rate: fails closed
        $this->assertSame($before, [WlInvoice::count(), \App\Models\WlFunnelManualPayment::count(), ManualPaymentRate::count()], 'the simulator creates nothing');
    }
}

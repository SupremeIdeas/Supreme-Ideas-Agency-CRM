<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Models\ManualPaymentAccount;
use App\Models\ManualPaymentRate;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePayment;
use App\Models\WlFunnelInstance;
use App\Models\WlFunnelManualPayment as P;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelManualPayments;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Services\WhiteLabel\Payments\ManualPaymentException;
use App\Support\FeatureFlags;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** White Label funnel manual payments (Phases 4 to 6.1): quote, proof, review, and an idempotent licence on approval. */
class WlFunnelManualPaymentTest extends TestCase
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
        ManualPaymentRate::create(['from_currency' => 'USDT', 'to_currency' => 'USD', 'rate' => 0.998, 'source' => ManualPaymentRate::SOURCE_MANUAL, 'as_of' => now()]);
        $this->account('USD', 'bank', null, true);
        $this->account('GBP', 'bank', null, true);
        $this->account('USDT', 'crypto', 'TRC20', true, 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS', now());
        $this->account('USDT', 'crypto', 'BEP20', true, '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed', null);   // enabled but never test-confirmed
    }

    private function account(string $cur, string $kind, ?string $net, bool $enabled, string $value = '1234567890', $tested = null): void
    {
        ManualPaymentAccount::create(['currency' => $cur, 'kind' => $kind, 'network' => $net, 'label' => "$cur $kind", 'fields' => [['label' => 'Account', 'value' => $value]], 'enabled' => $enabled, 'sort' => 1, 'test_confirmed_at' => $tested]);
    }

    private function buyer(string $plan = 'basic'): WlFunnelInstance
    {
        $u = User::factory()->create();
        $f = app(WlFunnel::class);
        $inst = $f->start($plan, bin2hex(random_bytes(8)), $u);
        $inst->forceFill(['user_id' => $u->id, 'status' => WlFunnelStates::DRAFT_SAVED, 'draft' => ['brand_name' => 'Zenith Cloud', 'palette_key' => 'x', 'hosting_choice' => 'supreme_ideas_server', 'contact_preference' => 'notification', 'billing_name' => 'Zed', 'billing_email' => 'zed@example.com', 'plan_key' => $plan]])->save();

        return $inst->fresh();
    }

    private function svc(): WlFunnelManualPayments
    {
        return app(WlFunnelManualPayments::class);
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('proof'.random_int(1, 999999).'.png', (string) ob_get_clean());
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('admin');
    }

    public function test_the_quote_uses_the_plan_row_price_and_the_conservative_formula_and_reuses_a_locked_one(): void
    {
        $inst = $this->buyer('basic');
        $price = (float) \App\Models\WhiteLabelLicensePlan::where('key', 'basic')->value('price_usd');
        $pay = $this->svc()->begin($inst, 'GBP');
        $this->assertSame(ceil(round($price / 1.3036 * 100, 6)) / 100, (float) $pay->expected_amount);
        $this->assertStringStartsWith('WL-'.$inst->public_code.'-', $pay->reference_code);
        $this->assertSame(WlFunnelStates::AWAITING_PAYMENT, $inst->fresh()->status);
        $this->assertSame($pay->id, $this->svc()->begin($inst->fresh(), 'GBP')->id, 'a locked identical quote is reused');
        $usd = $this->svc()->begin($inst->fresh(), 'USD');
        $this->assertSame($price, (float) $usd->expected_amount);
        $this->assertSame(P::CANCELLED, $pay->fresh()->status, 'a different currency replaces the old quote');
    }

    public function test_crypto_needs_a_test_confirmed_account_and_the_chosen_network(): void
    {
        $inst = $this->buyer();
        $this->assertSame(['TRC20'], $this->svc()->networksFor('USDT'));
        $this->assertSame('TRC20', $this->svc()->begin($inst, 'USDT', 'TRC20')->network);
        $this->expectException(ManualPaymentException::class);
        $this->svc()->begin($this->buyer(), 'USDT', 'BEP20');   // never test-confirmed: unavailable
    }

    public function test_no_trustworthy_rate_means_no_quote(): void
    {
        ManualPaymentRate::query()->delete();
        $this->expectException(ManualPaymentException::class);
        $this->svc()->begin($this->buyer(), 'GBP');
    }

    public function test_the_flag_and_unfinished_steps_block_a_payment(): void
    {
        $inst = $this->buyer();
        $inst->forceFill(['draft' => ['brand_name' => 'x']])->save();
        try {
            $this->svc()->begin($inst->fresh(), 'USD');
            $this->fail('unfinished purchase paid');
        } catch (ManualPaymentException) {
        }
        FeatureFlags::setEnabled('wl_manual_payments', false);
        $this->expectException(ManualPaymentException::class);
        $this->svc()->begin($this->buyer(), 'USD');
    }

    public function test_proof_validation_and_storage(): void
    {
        $inst = $this->buyer();
        $pay = $this->svc()->begin($inst, 'USD');
        $disk = Storage::disk(\App\Support\MediaStorage::privateDisk());
        $evil = UploadedFile::fake()->createWithContent('x.png', '<?php echo 1; ?> padding padding padding padding');
        try {
            $this->svc()->submitProof($pay, $inst->user, [$evil], 'Zed', null, null, null);
            $this->fail('accepted a script');
        } catch (ManualPaymentException) {
        }
        $this->assertSame([], $disk->allFiles(), 'a refused batch leaves nothing stored');
        try {
            $this->svc()->submitProof($pay, User::factory()->create(), [$this->png()], 'Zed', null, null, null);
            $this->fail('someone else sent proof');
        } catch (ManualPaymentException) {
        }
        $p = $this->svc()->submitProof($pay, $inst->user, [$this->png()], 'Zed', 'Bank', null, null);
        $this->assertSame(P::REVIEW, $p->status);
        $this->assertCount(1, $disk->allFiles());
        $this->assertSame(WlFunnelStates::PAYMENT_REVIEW, $inst->fresh()->status);
    }

    public function test_a_crypto_proof_needs_a_valid_unique_hash(): void
    {
        $a = $this->buyer();
        $pa = $this->svc()->begin($a, 'USDT', 'TRC20');
        try {
            $this->svc()->submitProof($pa, $a->user, [$this->png()], 'Zed', null, null, 'not-a-hash');
            $this->fail('bad hash accepted');
        } catch (ManualPaymentException) {
        }
        $hash = str_repeat('ab', 32);
        $this->svc()->submitProof($pa, $a->user, [$this->png()], 'Zed', null, null, $hash);
        $b = $this->buyer();
        $pb = $this->svc()->begin($b, 'USDT', 'TRC20');
        $this->expectException(ManualPaymentException::class);
        $this->svc()->submitProof($pb, $b->user, [$this->png()], 'Zed', null, null, $hash);
    }

    public function test_approval_issues_exactly_one_licence_and_is_idempotent(): void
    {
        $inst = $this->buyer('basic');
        $pay = $this->svc()->begin($inst, 'USD');
        $this->svc()->submitProof($pay, $inst->user, [$this->png()], 'Zed', null, null, null);
        $admin = $this->admin();
        $one = $this->svc()->approve($pay->fresh(), $admin, (float) $pay->expected_amount);
        $two = $this->svc()->approve($pay->fresh(), $admin, (float) $pay->expected_amount);
        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, WhiteLabelInstance::count());
        $this->assertSame(1, WhiteLabelLicensePayment::count());
        $this->assertSame(WhiteLabelInstance::ACQUISITION_LANDING_FUNNEL, $one->acquisition_method);
        $this->assertNotEmpty($one->fresh()->license_key);
        $this->assertSame($inst->user_id, $one->owner_user_id);
        $this->assertSame(WlFunnelStates::LICENSE_ISSUED, $inst->fresh()->status);
        $this->assertSame(P::APPROVED, $pay->fresh()->status);
    }

    public function test_underpaid_reject_and_resubmit_never_issue_a_licence(): void
    {
        $inst = $this->buyer();
        $pay = $this->svc()->begin($inst, 'USD');
        $this->svc()->submitProof($pay, $inst->user, [$this->png()], 'Zed', null, null, null);
        $admin = $this->admin();
        try {
            $this->svc()->approve($pay->fresh(), $admin, (float) $pay->expected_amount * 0.9);
            $this->fail('approved a short amount');
        } catch (ManualPaymentException) {
        }
        $this->svc()->underpaid($pay->fresh(), $admin, (float) $pay->expected_amount * 0.9);
        $this->assertSame(P::UNDERPAID, $pay->fresh()->status);
        $this->svc()->submitProof($pay->fresh(), $inst->user, [$this->png()], 'Zed', null, null, null);   // sends the difference
        $this->svc()->reject($pay->fresh(), $admin, 'The screenshot is unreadable.');
        $this->assertSame(P::AWAITING, $pay->fresh()->status);
        $this->assertSame('The screenshot is unreadable.', $pay->fresh()->reject_reason);
        $this->assertSame(0, WhiteLabelInstance::count());
        $this->svc()->submitProof($pay->fresh(), $inst->user, [$this->png()], 'Zed', null, null, null);
        $this->svc()->approve($pay->fresh(), $admin, (float) $pay->expected_amount);
        $this->assertSame(1, WhiteLabelInstance::count());
    }

    public function test_only_an_admin_can_review(): void
    {
        $inst = $this->buyer();
        $pay = $this->svc()->begin($inst, 'USD');
        $this->svc()->submitProof($pay, $inst->user, [$this->png()], 'Zed', null, null, null);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc()->approve($pay->fresh(), User::factory()->create(), (float) $pay->expected_amount);
    }

    public function test_a_duplicate_proof_image_is_flagged_for_the_reviewer(): void
    {
        $a = $this->buyer();
        $pa = $this->svc()->begin($a, 'USD');
        $file = $this->png();
        $this->svc()->submitProof($pa, $a->user, [$file], 'Zed', null, null, null);
        $b = $this->buyer();
        $pb = $this->svc()->begin($b, 'USD');
        $same = UploadedFile::fake()->createWithContent('again.png', (string) file_get_contents($file->getRealPath()));
        $p = $this->svc()->submitProof($pb, $b->user, [$same], 'Zed', null, null, null);
        $this->assertContains('duplicate_image', $p->flags);
    }

    public function test_the_pay_page_walks_a_buyer_from_currency_to_proof(): void
    {
        $inst = $this->buyer();
        $c = \Livewire\Livewire::actingAs($inst->user)->test(\App\Livewire\WlFunnelPay::class)
            ->call('choose', 'GBP')->assertSee('GBP')->assertSee('Refresh quote')->assertSee($inst->public_code);
        $pay = $this->svc()->current($inst->fresh());
        $c->assertSee(number_format((float) $pay->expected_amount, 2))->assertSee($pay->reference_code)->assertSee('1234567890');
        $c->set('files', [$this->png()])->set('payer_name', 'Zed Payer')->call('submit')->assertHasNoErrors()->assertSee('Proof received');
        $this->assertSame(P::REVIEW, $pay->fresh()->status);
    }

    public function test_the_crypto_page_shows_the_network_warning_and_a_qr_and_hides_untested_networks(): void
    {
        $inst = $this->buyer();
        \Livewire\Livewire::actingAs($inst->user)->test(\App\Livewire\WlFunnelPay::class)->call('choose', 'USDT')
            ->assertSee('Send only USDT on TRC20')->assertSee('TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS')->assertSeeHtml('<svg')->assertDontSee('BEP20');
    }

    public function test_the_pay_page_hides_the_manual_card_when_off_and_is_a_404_when_the_funnel_is_off(): void
    {
        $inst = $this->buyer();
        $this->actingAs($inst->user)->get(route('wl-funnel.pay'))->assertOk();
        FeatureFlags::setEnabled('wl_manual_payments', false);
        $this->actingAs($inst->user)->get(route('wl-funnel.pay'))->assertOk()->assertDontSee('bank transfer or crypto')->assertSee('Pay from your wallet');
        FeatureFlags::setEnabled('wl_funnel', false);
        $this->actingAs($inst->user)->get(route('wl-funnel.pay'))->assertNotFound();
    }

    public function test_the_admin_review_page_approves_once_and_streams_proof_only_to_admins(): void
    {
        $inst = $this->buyer();
        $pay = $this->svc()->begin($inst, 'USD');
        $this->svc()->submitProof($pay, $inst->user, [$this->png()], 'Zed', null, null, null);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.white-label.payment-review'))->assertOk()->assertSee($pay->reference_code);
        $this->actingAs($admin)->get(route('admin.wl-funnel-proof', [$pay->id, 0]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($inst->user)->get(route('admin.wl-funnel-proof', [$pay->id, 0]))->assertNotFound();
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\WlFunnelPaymentReview::class)
            ->call('view', $pay->id)->assertSee('Amount within tolerance')
            ->set('received', (string) $pay->expected_amount)->call('approve')->assertHasNoErrors();
        $this->assertSame(1, WhiteLabelInstance::count());
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\WlFunnelPaymentReview::class)->assertSee('Recently approved');
        $this->assertSame(1, WhiteLabelInstance::count());
    }
}

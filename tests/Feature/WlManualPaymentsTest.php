<?php

namespace Tests\Feature;

use App\Models\ManualPaymentAccount;
use App\Models\ManualPaymentAccountChange;
use App\Models\ManualPaymentRate;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhiteLabel\Payments\AddressIntegrity;
use App\Services\WhiteLabel\Payments\Keccak256;
use App\Services\WhiteLabel\Payments\ManualPaymentAccounts;
use App\Services\WhiteLabel\Payments\ManualPaymentException;
use App\Services\WhiteLabel\Payments\WlRateQuoter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Editable White Label receiving accounts, address integrity, and Grey live rate quotes. Grey is faked, shaped like the live API. */
class WlManualPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private const TRON = 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['services.grey.api_secret' => 'gbsk_test', 'services.grey.base_url' => 'https://businessapi.grey.co']);
    }

    private function admin(): User
    {
        return User::factory()->create(['password' => bcrypt('S3cret-pass')])->assignRole('admin');
    }

    private function bankData(array $over = []): array
    {
        return array_merge(['currency' => 'USD', 'kind' => 'bank', 'label' => 'USD bank transfer', 'fields' => [['label' => 'Account number', 'value' => '1234567890'], ['label' => 'Bank name', 'value' => 'Grey']]], $over);
    }

    // ------------------------------------------------------------------ integrity

    public function test_keccak_and_eip55_match_the_published_vectors(): void
    {
        $this->assertSame('c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470', Keccak256::hex(''));
        $this->assertSame('4e03657aea45a94fc7d47ba826c8d667c0d1e6e33a64a036ec44f58fa12d6c45', Keccak256::hex('abc'));
        foreach (['0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed', '0xfB6916095ca1df60bB79Ce92cE3Ea74c37c5d359', '0xdbF03B407c01E7cD3CBea99509d93f8DDDC8C6FB', '0xD1220A0cf47c7B9Be7A2E6BA89F429762e7b9aDb'] as $a) {
            $this->assertTrue(AddressIntegrity::evm($a), $a);
        }
        $this->assertFalse(AddressIntegrity::evm('0x5AAeb6053F3E94C9b9A09f33669435E7Ef1BeAed'), 'one flipped case');
        $this->assertFalse(AddressIntegrity::evm('0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAe'), 'short');
    }

    public function test_tron_and_solana_checksums(): void
    {
        $this->assertTrue(AddressIntegrity::tron(self::TRON));
        $this->assertFalse(AddressIntegrity::tron('TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfT'), 'last char changed');
        $this->assertFalse(AddressIntegrity::tron('0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522'));
        $this->assertTrue(AddressIntegrity::solana('DYw8jCTfwHNRJhhmFcbXvVDTqWMEVFBX6ZKUmG5CNSKK'));
        $this->assertFalse(AddressIntegrity::solana('DYw8jCTfwHNRJhhmFcbXvVDTqWMEVFBX6ZKUmG5CNSKK0'), 'invalid base58 char');
        $this->assertFalse(AddressIntegrity::check('Dogecoin', 'D8x')['ok']);
    }

    // ------------------------------------------------------------------ editable accounts

    public function test_an_admin_can_create_edit_and_replace_a_bank_account_and_details_are_encrypted(): void
    {
        $admin = $this->admin();
        $svc = app(ManualPaymentAccounts::class);
        $a = $svc->save(null, $this->bankData(), $admin, 'S3cret-pass', 'Add the Grey USD account');

        $this->assertSame('1234567890', $a->fresh()->fields[0]['value']);
        $this->assertStringNotContainsString('1234567890', (string) DB::table('manual_payment_accounts')->where('id', $a->id)->value('fields'), 'encrypted at rest');
        $this->assertFalse($a->enabled, 'a new account starts hidden');

        // Replace the details (the owner swaps in the Grey business account).
        $b = $svc->save($a, $this->bankData(['fields' => [['label' => 'Account number', 'value' => '9999000011'], ['label' => 'Bank name', 'value' => 'Grey Business']]]), $admin, 'S3cret-pass', 'Replaced with the Grey business account');
        $this->assertSame('9999000011', $b->fresh()->fields[0]['value']);
        $this->assertSame(['created', 'fields_changed'], ManualPaymentAccountChange::orderBy('id')->pluck('action')->all());
        $change = ManualPaymentAccountChange::where('action', 'fields_changed')->first();
        $this->assertNotSame($change->before_hash, $change->after_hash);
        $this->assertStringNotContainsString('9999000011', json_encode($change->toArray()), 'the log holds fingerprints, never details');
    }

    public function test_every_change_needs_the_admins_password_a_reason_and_the_admin_role(): void
    {
        $admin = $this->admin();
        $svc = app(ManualPaymentAccounts::class);
        foreach ([
            fn () => $svc->save(null, $this->bankData(), $admin, 'wrong', 'a valid reason'),
            fn () => $svc->save(null, $this->bankData(), $admin, '', 'a valid reason'),
            fn () => $svc->save(null, $this->bankData(), $admin, 'S3cret-pass', 'no'),
            fn () => $svc->save(null, $this->bankData(), User::factory()->create(['password' => bcrypt('S3cret-pass')]), 'S3cret-pass', 'a valid reason'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('expected refusal');
            } catch (ManualPaymentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, ManualPaymentAccount::count());
    }

    public function test_changing_a_crypto_address_switches_it_off_until_a_test_is_confirmed(): void
    {
        $admin = $this->admin();
        $svc = app(ManualPaymentAccounts::class);
        $crypto = fn ($addr) => ['currency' => 'USDT', 'kind' => 'crypto', 'network' => 'TRC20', 'label' => 'USDT on Tron', 'fields' => [['label' => 'Address', 'value' => $addr]]];

        $a = $svc->save(null, $crypto(self::TRON), $admin, 'S3cret-pass', 'Add the USDT address');
        try {
            $svc->setEnabled($a, true, $admin, 'S3cret-pass', 'Go live');
            $this->fail('cannot enable crypto without a test transaction');
        } catch (ManualPaymentException $e) {
            $this->assertStringContainsString('test transaction', $e->getMessage());
        }
        $svc->confirmTest($a, $admin, 'S3cret-pass', 'Sent 5 USDT, arrived');
        $this->assertTrue($svc->setEnabled($a->fresh(), true, $admin, 'S3cret-pass', 'Go live')->enabled);

        // A change of address (anti address-swap): off again, test mark cleared.
        $b = $svc->save($a->fresh(), $crypto('TLsV52sRDL79HXGGm9yzwKibb6BeruhUzy'), $admin, 'S3cret-pass', 'Rotate the address');
        $this->assertFalse($b->fresh()->enabled);
        $this->assertNull($b->fresh()->test_confirmed_at);

        // Saving the SAME address again changes nothing and does not switch it off.
        $svc->confirmTest($b->fresh(), $admin, 'S3cret-pass', 'Sent a test');
        $svc->setEnabled($b->fresh(), true, $admin, 'S3cret-pass', 'Go live');
        $same = $svc->save($b->fresh(), $crypto('TLsV52sRDL79HXGGm9yzwKibb6BeruhUzy'), $admin, 'S3cret-pass', 'Rename only');
        $this->assertTrue($same->fresh()->enabled);
    }

    public function test_a_bad_crypto_address_or_network_is_refused_and_a_bank_account_can_go_live_without_a_test(): void
    {
        $admin = $this->admin();
        $svc = app(ManualPaymentAccounts::class);
        foreach ([['TRC20', '0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522'], ['BEP20', self::TRON], ['Dogecoin', 'x'], [null, self::TRON]] as [$net, $addr]) {
            try {
                $svc->save(null, ['currency' => 'USDT', 'kind' => 'crypto', 'network' => $net, 'label' => 'x', 'fields' => [['label' => 'Address', 'value' => $addr]]], $admin, 'S3cret-pass', 'a valid reason');
                $this->fail("{$net} should be refused");
            } catch (ManualPaymentException) {
                $this->addToAssertionCount(1);
            }
        }
        $bank = $svc->save(null, $this->bankData(), $admin, 'S3cret-pass', 'Add the USD account');
        $this->assertTrue($svc->setEnabled($bank, true, $admin, 'S3cret-pass', 'Show to buyers')->enabled);
        $this->assertNull($bank->fresh()->test_confirmed_at, 'shown as not test-confirmed');
    }

    public function test_templates_ship_labels_only_never_account_details(): void
    {
        $this->assertContains('IBAN', ManualPaymentAccounts::template('GBP', 'bank'));
        $this->assertContains('ACH routing number', ManualPaymentAccounts::template('USD', 'bank'));
        $this->assertSame(['Address'], ManualPaymentAccounts::template('USDT', 'crypto'));
    }

    public function test_the_admin_page_edits_an_account_and_is_closed_to_everyone_else(): void
    {
        $admin = $this->admin();
        $path = '/'.config('admin.path', 'adminmaster');
        $this->actingAs($admin)->get($path.'/white-label/payments')->assertOk()->assertSee('Receiving accounts');
        $this->actingAs(User::factory()->create())->get($path.'/white-label/payments')->assertNotFound();

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\WhiteLabelPayments::class)
            ->call('newAccount')->set('label', 'USD bank transfer')->set('currency', 'USD')
            ->set('rows', [['label' => 'Account number', 'value' => '55501234'], ['label' => 'Bank name', 'value' => 'Grey']])
            ->set('password', 'S3cret-pass')->set('reason', 'First account from the admin page')
            ->call('save')->assertSet('error', null)->assertSee('USD bank transfer');
        $this->assertSame('55501234', ManualPaymentAccount::first()->fields[0]['value']);

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\WhiteLabelPayments::class)
            ->call('newAccount')->set('label', 'x')->set('rows', [['label' => 'a', 'value' => 'b']])->set('password', 'nope')->set('reason', 'a valid reason')
            ->call('save')->assertSet('error', 'Your password is not correct.');
    }

    // ------------------------------------------------------------------ rates

    /** Fake Grey's quote the way the live API behaves: a linear net rate per currency. */
    private function fakeGrey(array $netRates): void
    {
        Http::swap(new Factory);
        Http::fake(['*/v1/currency/rate' => function ($request) use ($netRates) {
            $d = $request->data();
            $rate = $netRates[$d['source_currency']] ?? null;
            if ($rate === null || ($d['transaction_type'] ?? '') !== 'swap') {
                return Http::response(['status' => 'failed', 'error' => 'deposit channel is empty'], 500);
            }

            return Http::response(['status' => 'success', 'data' => ['source_amount' => $d['source_amount'], 'source_currency' => $d['source_currency'], 'destination_currency' => 'USD', 'destination_amount' => round($d['source_amount'] * $rate, 2)]]);
        }]);
    }

    public function test_usd_needs_no_rate(): void
    {
        $q = app(WlRateQuoter::class)->amountToSend('USD', 1500);
        $this->assertSame([1500.0, 1.0, 'none'], [$q['amount'], $q['rate'], $q['source']]);
    }

    public function test_grey_live_quote_sizes_the_amount_so_it_converts_to_at_least_the_price(): void
    {
        // Live-shaped net rates: GBP 1.29997 (1.3065 less Grey's 0.5% fee), EUR 1.10495, USDT 0.998.
        $this->fakeGrey(['GBP' => 1.29997, 'EUR' => 1.10495, 'USDT' => 0.998, 'USDC' => 0.998]);
        $quoter = app(WlRateQuoter::class);

        foreach (['GBP' => 1.29997, 'EUR' => 1.10495, 'USDT' => 0.998] as $cur => $net) {
            $q = $quoter->amountToSend($cur, 1500);
            $this->assertSame('grey_live', $q['source'], $cur);
            $this->assertGreaterThanOrEqual(1500.0, round($q['amount'] * $net, 2), "{$cur} must convert to at least the price");
            $this->assertLessThan(1500.0 * 1.003, $q['amount'] * $net, "{$cur} is not wildly over-asked");
            $this->assertSame(round($q['amount'], 2), $q['amount'], 'whole cents');
        }
        $this->assertSame(1503.01, $quoter->amountToSend('USDT', 1500)['amount'], 'the blueprint fixture: USD 1,500 is about 1,503.01 USDT at 0.998');
        $this->assertGreaterThanOrEqual(3, ManualPaymentRate::where('source', 'grey_live')->distinct()->count('from_currency'));
    }

    public function test_lock_window_is_shorter_for_stablecoins(): void
    {
        $this->fakeGrey(['GBP' => 1.3, 'USDT' => 0.998]);
        $q = app(WlRateQuoter::class);
        $fiat = $q->amountToSend('GBP', 100);
        $stable = $q->amountToSend('USDT', 100);
        $this->assertTrue($fiat['locked_until']->gt($stable['locked_until']));
    }

    private function assertNoQuote(WlRateQuoter $q, string $currency, float $price): void
    {
        try {
            $q->amountToSend($currency, $price);
            $this->fail('expected no quote');
        } catch (ManualPaymentException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_when_grey_is_down_a_fresh_manual_rate_is_used_and_a_stale_or_missing_one_fails_closed(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('x', 503)]);
        $quoter = app(WlRateQuoter::class);

        $this->assertNoQuote($quoter, 'GBP', 1500); // no rate at all

        ManualPaymentRate::create(['from_currency' => 'GBP', 'to_currency' => 'USD', 'rate' => 1.3036, 'source' => 'manual', 'as_of' => now()->subHours(30)]);
        $this->assertNoQuote($quoter, 'GBP', 1500); // a 30h old rate is past the 24h limit

        ManualPaymentRate::create(['from_currency' => 'GBP', 'to_currency' => 'USD', 'rate' => 1.3036, 'source' => 'manual', 'as_of' => now()->subHour()]);
        $r = $quoter->amountToSend('GBP', 1500);
        $this->assertSame(['manual', 1150.66], [$r['source'], $r['amount']], 'the blueprint fixture: USD 1,500 is about GBP 1,150.66 at 1.3036');

        // With the live switch off Grey is not even asked.
        Setting::setValue(WlRateQuoter::S_USE_LIVE, false, 'white_label');
        $this->fakeGrey(['GBP' => 1.3]);
        $this->assertSame('manual', $quoter->amountToSend('GBP', 1500)['source']);
        Http::assertNothingSent();
    }

    public function test_a_quote_that_cannot_converge_is_refused_rather_than_short(): void
    {
        // A pathological source whose answer ignores the amount: asking for more never reaches the price.
        Http::swap(new Factory);
        Http::fake(['*/v1/currency/rate' => Http::response(['data' => ['destination_amount' => 100.0]])]);
        $this->assertNoQuote(app(WlRateQuoter::class), 'GBP', 1500);
    }

    // ------------------------------------------------------------------ seeding from a private file

    private function seedFile(array $extra = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'seed').'.json';
        file_put_contents($path, json_encode(array_merge(['payee_label' => 'the owner', 'accounts' => [
            ['currency' => 'USD', 'kind' => 'bank', 'label' => 'USD bank', 'enabled' => true, 'fields' => ['account_holder' => 'Test Holder', 'account_number' => '000111222', 'ach_routing' => '123456789']],
            ['currency' => 'USDT', 'kind' => 'crypto', 'network' => 'TRC20', 'label' => 'USDT TRC20', 'enabled' => true, 'fields' => ['address' => self::TRON]],
            ['currency' => 'USDT', 'kind' => 'crypto', 'network' => 'BEP20', 'label' => 'Bad address', 'enabled' => true, 'fields' => ['address' => '0xnotreal']],
        ]], $extra)));

        return $path;
    }

    public function test_the_seed_command_loads_encrypted_editable_accounts_and_keeps_crypto_off(): void
    {
        $this->artisan('wl:seed-manual-accounts', ['--file' => $this->seedFile()])->assertFailed();   // the bad address row is refused, the rest load
        $usd = ManualPaymentAccount::where('currency', 'USD')->firstOrFail();
        $this->assertTrue($usd->enabled);
        $this->assertSame('Account number', $usd->fields[1]['label']);
        $this->assertSame('You are paying the owner directly. Send so the full amount arrives.', $usd->payee_note);
        $this->assertStringNotContainsString('000111222', (string) DB::table('manual_payment_accounts')->where('id', $usd->id)->value('fields'), 'encrypted at rest');
        $tron = ManualPaymentAccount::where('network', 'TRC20')->firstOrFail();
        $this->assertFalse($tron->enabled, 'crypto always starts OFF');
        $this->assertSame(2, ManualPaymentAccount::count(), 'the bad address was not loaded');
        $this->assertSame(2, ManualPaymentAccountChange::count());
    }

    public function test_re_running_the_seed_never_overwrites_an_admin_edit_unless_asked(): void
    {
        $f = $this->seedFile();
        $this->artisan('wl:seed-manual-accounts', ['--file' => $f]);
        $usd = ManualPaymentAccount::where('currency', 'USD')->firstOrFail();
        $usd->update(['payee_note' => 'Our new business account']);
        $this->artisan('wl:seed-manual-accounts', ['--file' => $f]);
        $this->assertSame('Our new business account', $usd->fresh()->payee_note);
        $this->artisan('wl:seed-manual-accounts', ['--file' => $f, '--update' => true]);
        $this->assertStringContainsString('the owner', (string) $usd->fresh()->payee_note);
        $this->artisan('wl:seed-manual-accounts', ['--file' => '/nonexistent.json'])->assertFailed();
    }

    public function test_the_admin_can_edit_the_account_and_its_payee_note_with_nothing_hard_coded(): void
    {
        $admin = $this->admin();
        $acc = ManualPaymentAccount::create(['currency' => 'USD', 'kind' => 'bank', 'label' => 'USD', 'fields' => [['label' => 'Account', 'value' => '1']], 'enabled' => true]);
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\WhiteLabelPayments::class)->call('edit', $acc->id)
            ->set('payeeNote', 'Pay Supreme Ideas Agency Ltd')->set('password', 'S3cret-pass')->set('reason', 'Business account')->call('save');
        $this->assertSame('Pay Supreme Ideas Agency Ltd', $acc->fresh()->payee_note);
    }
}

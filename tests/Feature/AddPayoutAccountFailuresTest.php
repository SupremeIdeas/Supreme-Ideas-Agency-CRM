<?php

namespace Tests\Feature;

use App\Livewire\Withdraw;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Services\Payouts\Hardening\PayoutFreeze;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/** Engineering v3 Phase 2.5: adding a bank account never ends on an error page. Every failure becomes a plain message and a saved nothing. */
class AddPayoutAccountFailuresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
        config(['services.paystack.secret_key' => 'sk_test_x', 'payouts.env' => 'sandbox']);
    }

    private function fake(array $stubs): void
    {
        Http::swap(new Factory);
        Http::fake($stubs);
    }

    private function add(User $u, string $country = 'NG', string $bank = '058', string $number = '0123456789'): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($u)->test(Withdraw::class)
            ->set('country', $country)->set('bankCode', $bank)->set('accountNumber', $number)->call('addAccount');
    }

    private function bankList(): array
    {
        return ['*/bank?*' => Http::response(['status' => true, 'data' => [['code' => '058', 'name' => 'GTBank']]]), '*/bank/resolve*' => Http::response(['status' => true, 'data' => ['account_name' => 'ADA OBI']])];
    }

    public function test_the_happy_path_saves_a_verified_account(): void
    {
        $this->fake($this->bankList());
        $u = User::factory()->create();
        $this->add($u)->assertSet('accountError', null);
        $a = PayoutAccount::where('user_id', $u->id)->first();
        $this->assertSame(['ADA OBI', 'paystack', true], [$a->account_name, $a->provider, (bool) $a->is_verified]);
    }

    public function test_every_failure_is_a_plain_message_and_nothing_is_saved(): void
    {
        $u = User::factory()->create();

        // The provider cannot confirm the account (resolve fails).
        $this->fake(['*/bank?*' => Http::response(['status' => true, 'data' => [['code' => '058', 'name' => 'GTBank']]]), '*/bank/resolve*' => Http::response(['status' => false, 'message' => 'Could not resolve'], 422)]);
        $this->add($u)->assertNotSet('accountError', null);

        // The provider is down / times out.
        $this->fake(['*' => fn () => throw new ConnectionException('timeout')]);
        $this->add($u)->assertNotSet('accountError', null);

        // The provider answers 500.
        $this->fake(['*' => Http::response('boom', 500)]);
        $this->add($u)->assertNotSet('accountError', null);

        // A country no rail covers.
        $this->fake($this->bankList());
        $c = $this->add($u, 'JP');
        $this->assertNotNull($c->get('accountError'));

        // A frozen payee.
        app(PayoutFreeze::class)->freeze($u, 'not_me');
        $this->add($u)->assertNotSet('accountError', null);

        $this->assertSame(0, PayoutAccount::where('user_id', $u->id)->count(), 'a failure must never leave a half-saved account');
    }

    public function test_step_up_required_and_the_hourly_limit_are_messages_not_errors(): void
    {
        $this->fake($this->bankList());
        $u = User::factory()->create();

        \App\Models\Setting::setValue(\App\Support\PayoutSettings::STEP_UP_REQUIRED, true);
        $this->add($u)->assertNotSet('accountError', null);
        $this->assertSame(0, PayoutAccount::where('user_id', $u->id)->count());
        \App\Models\Setting::setValue(\App\Support\PayoutSettings::STEP_UP_REQUIRED, false);
        RateLimiter::clear('payout-add-account:'.$u->id);

        \App\Models\Setting::setValue(\App\Support\PayoutSettings::ADD_ACCOUNT_PER_HOUR ?? 'payouts.add_account_per_hour', 1);
        $this->add($u)->assertSet('accountError', null);
        $this->add($u, 'NG', '058', '0123456780')->assertNotSet('accountError', null);
    }

    public function test_adding_the_same_destination_twice_is_a_friendly_message(): void
    {
        $this->fake($this->bankList());
        $u = User::factory()->create();
        $this->add($u)->assertSet('accountError', null);
        $this->add($u)->assertSet('accountError', 'You have already saved that account.');
        $this->assertSame(1, PayoutAccount::where('user_id', $u->id)->count(), 'no duplicate row');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\Verify\Checks\RewardsLedgerSound;
use App\Support\Verify\Checks\WalletLedgerSound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletRewardsVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_normal_ledger_passes_and_every_kind_of_corruption_is_caught(): void
    {
        $u = User::factory()->create();
        $w = app(WalletService::class);
        $w->credit($u, 50, 'USD', ['reference' => 'a']);
        $w->debit($u, 20, 'USD', ['reference' => 'b']);
        $w->refund($u, 5, 'USD', ['reference' => 'c']);
        $this->assertSame('pass', (new WalletLedgerSound)->run()->status);

        // 1) a balance changed behind the ledger's back
        DB::table('user_wallets')->where('user_id', $u->id)->update(['usd_balance' => 999]);
        $r = (new WalletLedgerSound)->run();
        $this->assertSame('fail', $r->status);
        $this->assertStringContainsString('differs from their last ledger row', $r->detail);
        DB::table('user_wallets')->where('user_id', $u->id)->update(['usd_balance' => 35]);
        $this->assertSame('pass', (new WalletLedgerSound)->run()->status);

        // 2) a negative balance
        DB::table('user_wallets')->where('user_id', $u->id)->update(['usd_balance' => -1]);
        $this->assertStringContainsString('negative', (new WalletLedgerSound)->run()->detail);
        DB::table('user_wallets')->where('user_id', $u->id)->update(['usd_balance' => 35]);

        // 3) a row whose movement does not match its amount
        DB::table('wallet_transactions')->where('reference', 'a')->update(['amount' => 49]);
        $this->assertStringContainsString('does not match their amount', (new WalletLedgerSound)->run()->detail);
        DB::table('wallet_transactions')->where('reference', 'a')->update(['amount' => 50]);

        // 4) reserved more than held
        DB::table('user_wallets')->where('user_id', $u->id)->update(['reserved_usd' => 500]);
        $this->assertStringContainsString('reserved', (new WalletLedgerSound)->run()->detail);
    }

    public function test_the_rewards_ledger_check_catches_drift_and_unpaid_rewarded_referrals(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->assertSame('pass', (new RewardsLedgerSound)->run()->status);

        DB::table('referral_earnings')->insert(['user_id' => $a->id, 'type' => 'accrual', 'amount' => 3, 'balance_after' => 3, 'currency' => 'USD', 'reference' => 'referral_reward:77', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('pass', (new RewardsLedgerSound)->run()->status);
        DB::table('referral_earnings')->where('user_id', $a->id)->update(['balance_after' => 9]);
        $this->assertStringContainsString('differs from the sum', (new RewardsLedgerSound)->run()->detail);
        DB::table('referral_earnings')->where('user_id', $a->id)->update(['balance_after' => 3]);

        DB::table('referrals')->insert(['referrer_id' => $a->id, 'referred_id' => $b->id, 'type' => 'customer', 'reward_pct' => 10, 'rewarded' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertStringContainsString('marked rewarded with no matching earning', (new RewardsLedgerSound)->run()->detail);
    }
}

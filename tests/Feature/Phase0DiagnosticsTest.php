<?php

namespace Tests\Feature;

use App\Http\Controllers\ClientErrorController;
use App\Models\ClientError;
use App\Models\Setting;
use App\Models\VerifyRun;
use App\Support\FixFlags;
use App\Support\Verify\Verifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Engineering blueprint Phase 0: browser error beacon, `naara:verify`, fix flags. */
class Phase0DiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_beacon_stores_a_scrubbed_report_without_ip_or_query_string(): void
    {
        $res = $this->postJson('/_diag/client-error', [
            'kind' => 'error',
            'message' => 'Unexpected token at https://x.test/a?token=SECRETVALUE&b=1 user jane@example.com Bearer abc.def.ghi',
            'stack' => "TypeError: boom\n at https://x.test/wallet?code=ZZZ:1:1",
            'route' => '/wallet?tab=quick&secret=1',
            'component' => 'wallet',
            'vw' => 360,
        ]);
        $res->assertOk()->assertJson(['stored' => true]);

        $row = ClientError::first();
        $this->assertSame('/wallet', $row->route);
        $this->assertStringNotContainsString('SECRETVALUE', $row->message);
        $this->assertStringNotContainsString('jane@example.com', $row->message);
        $this->assertStringNotContainsString('abc.def.ghi', $row->message);
        $this->assertStringNotContainsString('ZZZ', (string) $row->stack);
        $this->assertArrayNotHasKey('ip', $row->getAttributes());
    }

    public function test_the_same_failure_in_a_loop_is_capped_and_bad_input_is_rejected(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/_diag/client-error', ['kind' => 'rejection', 'message' => 'loop', 'stack' => 'at a'])->assertOk();
        }
        $this->assertSame(5, ClientError::count());

        $this->postJson('/_diag/client-error', ['kind' => 'nope', 'message' => 'x'])->assertStatus(422);
        $this->postJson('/_diag/client-error', ['kind' => 'error'])->assertStatus(422);
    }

    public function test_scrub_removes_long_tokens(): void
    {
        $this->assertStringNotContainsString('0123456789abcdef0123456789abcdef', ClientErrorController::scrub('key 0123456789abcdef0123456789abcdef end', 100));
    }

    public function test_verify_runs_every_area_stores_results_and_never_fakes_a_pass_for_unbuilt_areas(): void
    {
        $this->artisan('naara:verify', ['--json' => true])->run();
        $rows = VerifyRun::all();
        $this->assertGreaterThanOrEqual(count(Verifier::AREAS), $rows->pluck('area')->unique()->count());
        $this->assertEqualsCanonicalizing(Verifier::AREAS, array_values(array_unique(array_merge($rows->pluck('area')->all(), []))));

        // Every area now has a real check: nothing may report the "no automated check yet" placeholder.
        $this->assertSame([], $rows->filter(fn ($r) => str_contains((string) $r->detail, 'No automated check yet'))->pluck('area')->all());
        $this->assertSame('pass', $rows->firstWhere('area', 'wallet')->status);
    }

    public function test_verify_rejects_an_unknown_area_and_can_run_one_area(): void
    {
        $this->artisan('naara:verify', ['area' => 'nonsense'])->assertExitCode(2);
        $this->artisan('naara:verify', ['area' => 'media'])->assertSuccessful();
        $this->assertTrue(VerifyRun::where('area', 'media')->exists());
        $this->assertFalse(VerifyRun::where('area', 'chat')->exists());
    }

    public function test_the_payout_registry_check_turns_green_now_that_health_reads_the_gateway_registry(): void
    {
        // It was a WARN while AutomationStatus hard-coded its provider list (blueprint 2.0b). Phase 2 derived the list from the registry.
        $rows = collect(Verifier::run('payouts'));
        $registry = $rows->first(fn ($r) => str_contains($r['check'], 'registry'));
        $this->assertSame('pass', $registry['status']);

        // And the check still FINDS a hand-written list if one is reintroduced (it scans the health layer for the literal pattern).
        $src = file_get_contents(app_path('Services/Payouts/Hardening/AutomationStatus.php'));
        $this->assertDoesNotMatchRegularExpression("/\\[\\s*'paystack'\\s*,\\s*'flutterwave'/", $src);
    }

    public function test_fix_flags_default_off_and_unknown_flags_are_refused(): void
    {
        $this->assertFalse(FixFlags::on('wallet_v2'));
        Setting::setValue('fix.wallet_v2', true, 'fix');
        $this->assertTrue(FixFlags::on('wallet_v2'));
        $this->expectException(\InvalidArgumentException::class);
        FixFlags::on('made_up');
    }
}

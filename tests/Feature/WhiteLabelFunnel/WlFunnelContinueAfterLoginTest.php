<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Models\User;
use App\Models\WlFunnelInstance;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Support\FeatureFlags;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Live bug (2026-10-18): a visitor pressed a plan on the White Label landing page, signed in, and ended up on the dashboard — the purchase
 * never continued. The sign-in now carries the person back into /account/white-label-purchase, for both login and registration.
 */
class WlFunnelContinueAfterLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WhiteLabelLicensePlanSeeder::class);
        FeatureFlags::setEnabled('wl_funnel', true);
    }

    public function test_an_existing_account_that_logs_in_after_pressing_a_plan_lands_in_the_purchase(): void
    {
        $user = User::factory()->create(['is_active' => true, 'password' => bcrypt('secret-pass-123')]);

        $start = $this->get('/white-label/start?plan=extended');
        $start->assertRedirect(route('register'));
        $visitor = collect($start->headers->getCookies())->first(fn ($c) => $c->getName() === WlFunnel::COOKIE)?->getValue();
        $this->assertNotEmpty($visitor, 'the funnel cookie must be issued');

        // Same browser: the cookie comes back, the person chooses "log in" instead of registering.
        $this->withUnencryptedCookie(WlFunnel::COOKIE, $visitor)
            ->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123'])
            ->assertRedirect(route('wl-funnel.purchase'));

        $inst = WlFunnelInstance::where('user_id', $user->id)->first();
        $this->assertNotNull($inst, 'the instance started on the landing page is bound to the account');
    }

    public function test_a_new_account_registered_after_pressing_a_plan_lands_in_the_purchase(): void
    {
        $start = $this->get('/white-label/start?plan=extended');
        $visitor = collect($start->headers->getCookies())->first(fn ($c) => $c->getName() === WlFunnel::COOKIE)?->getValue();

        $this->withUnencryptedCookie(WlFunnel::COOKIE, $visitor)
            ->post('/register', [
                'name' => 'Zed Buyer', 'email' => 'zed@example.com', 'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123', 'terms' => 'on',
            ])
            ->assertRedirect(route('wl-funnel.purchase'));
    }

    public function test_a_normal_login_with_no_funnel_still_goes_home(): void
    {
        $user = User::factory()->create(['is_active' => true, 'password' => bcrypt('secret-pass-123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-123'])
            ->assertRedirect(config('fortify.home', '/dashboard'));
    }
}

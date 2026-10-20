<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Models\User;
use App\Models\WlFunnelInstance;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Support\FeatureFlags;
use App\Support\MasterMarketing\WlResumeWidget;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The side widget keeps an unfinished White Label purchase one tap away, and disappears once it is paid. */
class WlResumeWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WhiteLabelLicensePlanSeeder::class);
        FeatureFlags::setEnabled('wl_funnel', true);
    }

    private function openInstance(User $u, string $status = WlFunnelStates::WIZARD): WlFunnelInstance
    {
        $inst = app(WlFunnel::class)->start('extended', bin2hex(random_bytes(16)), $u);
        $inst->forceFill(['user_id' => $u->id, 'status' => $status])->save();

        return $inst;
    }

    public function test_it_shows_on_ordinary_pages_while_a_purchase_is_open_and_links_to_the_purchase(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $inst = $this->openInstance($u);

        $this->actingAs($u)->get('/dashboard')
            ->assertOk()->assertSee('data-wl-resume-widget', false)->assertSee($inst->public_code)->assertSee(route('wl-funnel.purchase'), false);
    }

    public function test_it_is_hidden_on_the_purchase_pages_and_when_the_flag_is_off_and_with_no_open_purchase(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $this->openInstance($u);

        $this->assertNull(WlResumeWidget::for($u, 'wl-funnel.purchase'));
        $this->assertNull(WlResumeWidget::for($u, 'wl-funnel.pay'));
        $this->assertNotNull(WlResumeWidget::for($u, 'dashboard'));

        FeatureFlags::setEnabled('wl_funnel', false);
        $this->assertNull(WlResumeWidget::for($u, 'dashboard'));
        FeatureFlags::setEnabled('wl_funnel', true);

        $other = User::factory()->create(['is_active' => true]);
        $this->assertNull(WlResumeWidget::for($other, 'dashboard'));
        $this->assertNull(WlResumeWidget::for(null, 'dashboard'));
    }

    public function test_it_disappears_for_good_once_the_purchase_is_paid(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $inst = $this->openInstance($u, WlFunnelStates::AWAITING_PAYMENT);
        $this->assertNotNull(WlResumeWidget::for($u, 'dashboard'));

        $inst->forceFill(['status' => WlFunnelStates::PAID])->save();
        $this->assertNull(WlResumeWidget::for($u, 'dashboard'));
        $this->actingAs($u)->get('/dashboard')->assertOk()->assertDontSee('data-wl-resume-widget', false);
    }

    public function test_a_payment_under_review_says_so(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $this->openInstance($u, WlFunnelStates::PAYMENT_REVIEW);

        $w = WlResumeWidget::for($u, 'dashboard');
        $this->assertSame('Your payment is being checked', $w['label']);
        $this->assertSame('See status', $w['cta']);
    }
}

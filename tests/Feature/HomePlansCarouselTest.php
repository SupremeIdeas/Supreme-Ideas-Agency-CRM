<?php

namespace Tests\Feature;

use App\Livewire\Marketing\PlansByCountry;
use App\Models\EsimPlan;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** Homepage plans carousel: plans by the visitor's country, honest estimate fallback, and never any cost. */
class HomePlansCarouselTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Setting::setValue('pricing.public_mode', 'live');
    }

    private function plan(string $name, array $countries, string $type, float $retail, bool $featured = false): EsimPlan
    {
        static $n = 0;

        return EsimPlan::forceCreate([
            'provider' => 'esimgo', 'provider_plan_id' => 'p'.(++$n), 'name' => $name, 'type' => 'data', 'countries' => $countries,
            'coverage_type' => $type, 'is_active' => true, 'data_mb' => 5120, 'validity_days' => 30,
            'manual_retail_usd' => $retail, 'cost_price_usd' => $retail / 2, 'is_featured' => $featured,
        ]);
    }

    public function test_local_plans_for_the_visitors_country_lead(): void
    {
        $this->plan('Japan Local', ['JP'], 'local', 9.5);
        $this->plan('Europe Regional', ['FR', 'DE'], 'regional', 14);
        $this->plan('Japan+Asia', ['JP', 'KR'], 'regional', 12);

        $html = $this->withHeader('CF-IPCountry', 'JP')->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Plans for', $html);
        $this->assertLessThan(strpos($html, 'Japan+Asia'), strpos($html, 'Japan Local'));
        $this->assertStringNotContainsString('Europe Regional', $html);
    }

    public function test_a_visitor_can_pick_another_country_and_it_is_remembered(): void
    {
        $this->plan('Japan Local', ['JP'], 'local', 9.5);
        $this->plan('Kenya Local', ['KE'], 'local', 7);

        Livewire::test(PlansByCountry::class, ['copy' => []])
            ->set('country', 'KE')->assertSee('Kenya Local')->assertDontSee('Japan Local');
        $this->assertSame('KE', session('nx_plans_country'));
    }

    public function test_an_unknown_country_falls_back_to_popular_plans(): void
    {
        $this->plan('Japan Local', ['JP'], 'local', 9.5);
        request()->headers->set('CF-IPCountry', 'BR');

        Livewire::test(PlansByCountry::class, ['copy' => []])->assertSet('country', null)->assertSee('Japan Local');
    }

    public function test_cost_is_never_rendered(): void
    {
        $this->plan('Japan Local', ['JP'], 'local', 9.5);

        Livewire::test(PlansByCountry::class, ['copy' => []])->assertDontSee('4.75')->assertDontSee('cost');
    }

    public function test_the_estimate_tiers_show_until_live_plans_exist(): void
    {
        Setting::setValue('pricing.public_mode', 'estimate');

        Livewire::test(PlansByCountry::class, ['copy' => ['cta' => 'See All Plans']])->assertSee('Traveller')->assertSee('See All Plans');
    }

    public function test_the_homepage_renders_the_carousel_and_the_new_logo(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('nx-snap', $html);
        $this->assertStringContainsString('/brand/naara-logo-', $html);
        $this->assertStringContainsString('images/home/hero-mockup.webp', $html);
    }
}

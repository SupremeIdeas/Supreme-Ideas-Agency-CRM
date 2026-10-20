<?php

namespace App\Livewire\Marketing;

use App\Models\EsimPlan;
use App\Support\CountryNames;
use App\Support\Geo\GeoResolver;
use App\Support\PricingDisplay;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Homepage "plans for where you're going" carousel. The visitor's country comes from the geo resolver (Cloudflare header) unless they
 * picked one (remembered for the session); local plans for that country lead, then regional/global plans that cover it. Cost is never
 * read: only `display_price`. Before live plans exist it shows the honest estimate tiers, exactly like /pricing.
 */
class PlansByCountry extends Component
{
    /** The CMS copy for the pricing section (home.pricing). */
    public array $copy = [];

    public ?string $country = null;

    public function mount(array $copy = []): void
    {
        $this->copy = $copy;
        $picked = session('nx_plans_country');
        $guess = $picked ?: app(GeoResolver::class)->country(request());
        try {
            $this->country = $guess && in_array(strtoupper($guess), $this->covered(), true) ? strtoupper($guess) : null;
        } catch (\Throwable) {
            $this->country = null;
        }
    }

    public function updatedCountry(): void
    {
        $iso = strtoupper(trim((string) $this->country));
        $this->country = in_array($iso, $this->covered(), true) ? $iso : null;
        session(['nx_plans_country' => $this->country]);
    }

    /** ISO codes that at least one active plan covers, cached (the plan table is the source of truth). */
    private function covered(): array
    {
        return Cache::remember('nx.home.plan_countries', 600, function () {
            $set = [];
            foreach (EsimPlan::where('is_active', true)->pluck('countries') as $list) {
                foreach ((array) $list as $c) {
                    if (is_string($c) && strlen($c) === 2) {
                        $set[strtoupper($c)] = true;
                    }
                }
            }
            $codes = array_keys($set);
            sort($codes);

            return $codes;
        });
    }

    private function plansFor(?string $iso)
    {
        $key = 'nx.home.plans.'.($iso ?: 'popular');

        return Cache::remember($key, 300, function () use ($iso) {
            $q = EsimPlan::where('is_active', true);
            if ($iso) {
                $q->whereJsonContains('countries', $iso);
            }
            $plans = $q->orderByRaw("CASE coverage_type WHEN 'local' THEN 0 WHEN 'regional' THEN 1 ELSE 2 END")
                ->orderByDesc('is_featured')->orderBy('final_retail_usd')->limit(8)->get();
            if ($iso && $plans->count() < 3) {
                // Thin coverage: top up with GLOBAL plans (they work there too), never another country's local plans.
                $plans = $plans->concat(EsimPlan::where('is_active', true)->where('coverage_type', 'global')->whereNotIn('id', $plans->pluck('id'))
                    ->orderByDesc('is_featured')->orderBy('final_retail_usd')->limit(6 - $plans->count())->get());
            }
            if ($plans->isEmpty()) {
                $plans = EsimPlan::where('is_active', true)->orderByDesc('is_featured')->orderBy('final_retail_usd')->limit(6)->get();
            }

            return $plans;
        });
    }

    public static function dataLabel(?int $mb): string
    {
        if (! $mb) {
            return 'Unlimited';
        }

        return $mb >= 1024 ? rtrim(rtrim(number_format($mb / 1024, 1), '0'), '.').' GB' : $mb.' MB';
    }

    private function tiers(): array
    {
        try {
            return PricingDisplay::estimateTiers();
        } catch (\Throwable) {
            return PricingDisplay::defaultTiers();
        }
    }

    public function render()
    {
        // The marketing homepage must render even if the catalogue is unreachable (fresh install, DB hiccup): fall back to the estimate tiers.
        try {
            $live = PricingDisplay::mode() === 'live';
            $plans = $live ? $this->plansFor($this->country) : collect();
            $countries = $live ? collect($this->covered())->mapWithKeys(fn ($c) => [$c => CountryNames::name($c)])->sort() : collect();
        } catch (\Throwable $e) {
            report($e);
            $live = false;
            $plans = $countries = collect();
        }

        return view('livewire.marketing.plans-by-country', [
            'live' => $live,
            'plans' => $plans,
            'tiers' => $live ? [] : $this->tiers(),
            'countries' => $countries,
            'countryName' => $this->country ? CountryNames::name($this->country) : null,
        ]);
    }
}

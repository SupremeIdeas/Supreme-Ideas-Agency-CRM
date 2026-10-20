<?php

namespace App\Http\Controllers;

use App\Models\BrandSubscriptionPlan;
use App\Support\Business\BusinessPlanFeatures;
use App\Support\SiteContent;

/**
 * The public NaaraBusiness Suite page (MASTER-ONLY, behind the `business.suite` switch). Copy comes from the Site Editor (page `business-suite`); the
 * plan cards are LIVE from the plans table and the features each plan really grants, never typed here.
 */
class BusinessSuiteLandingController extends Controller
{
    public function __invoke()
    {
        $plans = BrandSubscriptionPlan::active()->ordered()->get()->map(function (BrandSubscriptionPlan $p) {
            $live = BusinessPlanFeatures::assignable();
            $features = collect($p->grantedFeatures())->filter(fn ($k) => in_array($k, $live, true))->map(fn ($k) => BusinessPlanFeatures::FEATURES[$k]['label'])->values()->all();
            $limits = collect(BusinessPlanFeatures::LIMITS)->map(fn ($d, $k) => $p->limitOverride($k) ? $p->limitOverride($k).' '.strtolower($d['label']) : null)->filter()->values()->all();

            return ['plan' => $p, 'features' => $features, 'limits' => $limits, 'credits' => $p->monthlyCredits(), 'skins' => $p->grantsPremiumSkins(), 'cpf' => $p->costPerGuaranteedFollower()];
        });

        $skins = collect((array) config('appearance.skins'))->filter(fn ($s, $k) => ($s['access'] ?? 'free') === 'pro' && in_array($k, (array) config('appearance.built'), true))
            ->take(8)->map(fn ($s) => ['label' => $s['label'], 'blurb' => $s['blurb']])->values();

        return view('marketing.business-suite', ['sections' => SiteContent::page('business-suite'), 'plans' => $plans, 'skins' => $skins]);
    }
}

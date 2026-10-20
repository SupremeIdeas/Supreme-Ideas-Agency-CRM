<?php

namespace App\Http\Controllers\MasterMarketing;

use App\Http\Controllers\Controller;
use App\Models\WhiteLabelLicensePlan;
use App\Support\BrandSettings;
use App\Support\FeatureEntitlements;
use App\Support\MasterMarketing\PageSwitch;
use App\Support\SiteContent;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * MASTER-ONLY. The public sales pages for Naara Chat, NaaraBrain and White Label. Copy is the Site Editor pages `naara-chat` / `white-label`; the admin can
 * switch either page off (PageSwitch) after it is built, and a switched-off page answers a friendly sheet, never a bare 404 to a person.
 */
class LandingController extends Controller
{
    public function chat(): Response|\Illuminate\Contracts\View\View
    {
        return $this->render('naara-chat', 'marketing.master-marketing.chat');
    }

    public function brain(): Response|\Illuminate\Contracts\View\View
    {
        return $this->render('naara-brain', 'marketing.master-marketing.brain');
    }

    public function gift(): Response|\Illuminate\Contracts\View\View
    {
        return $this->render('naara-gift', 'marketing.master-marketing.gift');
    }

    public function whiteLabel(): Response|\Illuminate\Contracts\View\View
    {
        return $this->render('white-label', 'marketing.master-marketing.white-label', ['plans' => $this->plans()]);
    }

    /** @return Response|\Illuminate\Contracts\View\View */
    private function render(string $page, string $view, array $extra = [])
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);

        if (! PageSwitch::isLive($page)) {
            return response()->view('marketing.master-marketing.off', ['label' => PageSwitch::label($page)], 200)
                ->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
        }

        return view($view, ['sections' => SiteContent::page($page), 'brand' => BrandSettings::name()] + $extra);
    }

    /** The live license plans (admin-managed prices). Empty when the catalogue is not seeded yet. */
    private function plans(): Collection
    {
        try {
            return WhiteLabelLicensePlan::query()->where('is_active', true)->orderBy('sort_order')->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}

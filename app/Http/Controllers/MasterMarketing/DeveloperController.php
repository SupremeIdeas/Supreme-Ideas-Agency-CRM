<?php

namespace App\Http\Controllers\MasterMarketing;

use App\Http\Controllers\Controller;
use App\Support\BrandSettings;
use App\Support\FeatureEntitlements;
use App\Support\MasterMarketing\DeveloperDocs;
use Illuminate\Contracts\View\View;

/**
 * MASTER-ONLY. The public developer marketing page (/developers) and the grouped documentation (/developers/docs/{page}). A white-label install keeps the plain
 * single-page reference at /developers; this controller is only registered when it exists (routes/web.php guards it with class_exists), and it refuses to answer
 * on a non-master install as a second line of defence.
 */
class DeveloperController extends Controller
{
    public function landing(): View
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);

        return view('marketing.master-marketing.developers-landing', ['brand' => BrandSettings::name(), 'groups' => DeveloperDocs::groups(), 'host' => $this->host()]);
    }

    public function docs(string $slug = 'introduction'): View
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        $page = DeveloperDocs::find($slug) ?? abort(404);

        return view('marketing.master-marketing.developers-docs', [
            'page' => $page, 'groups' => DeveloperDocs::groups(), 'nav' => DeveloperDocs::neighbours($slug), 'host' => $this->host(),
        ]);
    }

    private function host(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'your-domain';
    }
}

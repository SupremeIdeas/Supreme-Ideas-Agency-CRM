<?php

namespace App\Http\Middleware;

use App\Models\WalletGroup;
use App\Services\Business\BusinessAccess;
use App\Support\Business\BusinessPlanFeatures;
use App\Support\Business\BusinessSettings;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `business.plan:<feature>` (MASTER-ONLY): the workspace owner's Naara Pro plan must include the feature. A page load that fails gets a friendly
 * "needs a plan" page (never a bare 403) that says whether the plan is missing or has ended and that nothing was deleted; anything else (a POST, a
 * Livewire update, JSON) gets a 403. Runs after `business.suite` and `feature.live:*`, so a switched-off module still shows "Coming soon" first.
 */
class EnsureBusinessPlan
{
    public function __construct(private BusinessAccess $access) {}

    public function handle(Request $request, Closure $next, string $feature = BusinessPlanFeatures::WORKSPACE): Response
    {
        $user = $request->user();
        if ($user === null || BusinessSettings::accessModel() === 'open') {
            return $next($request);
        }

        // Whose plan decides: the active workspace's owner, else the workspace this person owns. With neither, the page is the "create one" page.
        $group = Workspace::active() ?? $user->ownedWalletGroups()->where('kind', WalletGroup::KIND_BUSINESS)->first();
        $owner = $group?->owner ?? $user;
        if ($group === null && $feature !== BusinessPlanFeatures::WORKSPACE) {
            return $next($request);   // the page itself explains that it needs a workspace
        }
        if ($this->access->allows($owner, $feature)) {
            return $next($request);
        }

        $isOwner = $owner->id === $user->id;
        $wantsPage = $request->isMethod('GET') && ! $request->headers->has('X-Livewire') && ! $request->expectsJson();
        if (! $wantsPage) {
            abort(403, 'This needs a NaaraBusiness Suite plan that includes it.');
        }

        return response()->view('business.plan-required', [
            'feature' => BusinessPlanFeatures::FEATURES[$feature] ?? BusinessPlanFeatures::FEATURES[BusinessPlanFeatures::WORKSPACE],
            'state' => $this->access->state($owner),
            'isOwner' => $isOwner,
            'ownerName' => $owner->name,
        ], 200)->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
    }
}

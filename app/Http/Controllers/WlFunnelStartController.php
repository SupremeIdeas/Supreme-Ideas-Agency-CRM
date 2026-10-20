<?php

namespace App\Http\Controllers;

use App\Services\WhiteLabel\Funnel\WlFunnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Landing plan button -> funnel instance (White Label funnel Phase 1, MASTER-ONLY). A server route, not a client-only link: it creates the instance, remembers the
 * browser (signed-by-encryption first-party cookie holding a random visitor id), records campaign tags, then sends a visitor to Register and a signed-in person
 * straight into the purchase. With the flag off it sends people back to the landing page with a plain explanation.
 */
class WlFunnelStartController extends Controller
{
    public function start(Request $request, WlFunnel $funnel)
    {
        if (! WlFunnel::enabled()) {
            return redirect()->route('white-label.landing')->with('flash_toast', ['type' => 'info', 'message' => 'Online purchase opens soon. Meanwhile you can contact us from this page.']);
        }
        $plan = (string) $request->query('plan', 'extended');
        $visitor = (string) $request->cookie(WlFunnel::COOKIE);
        if (! preg_match('/^[0-9a-f]{32}$/', $visitor)) {
            $visitor = $funnel->newVisitorId();
        }
        try {
            $inst = $funnel->start($plan, $visitor, $request->user(), $request->query());
        } catch (\InvalidArgumentException) {
            return redirect()->route('white-label.landing')->with('flash_toast', ['type' => 'info', 'message' => 'That plan is not available. Pick one from the list.']);
        }
        Cookie::queue(Cookie::make(WlFunnel::COOKIE, $visitor, WlFunnel::COOKIE_DAYS * 24 * 60, '/', null, $request->isSecure(), true, false, 'lax'));
        $request->session()->put('wl_funnel_code', $inst->public_code);   // the register banner reads this (cookie-blocked browsers still see it for this session)

        return $request->user() ? redirect()->route('wl-funnel.purchase') : redirect()->route('register');
    }
}

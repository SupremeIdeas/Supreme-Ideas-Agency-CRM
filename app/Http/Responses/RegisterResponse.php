<?php

namespace App\Http\Responses;

use App\Support\WelcomeSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * After a successful signup, route the brand-new user through the Aurora Welcome
 * entrance (first-login animation) before the dashboard. A `just_registered`
 * session flag is what the /welcome screen keys on; when the animation is turned
 * off in admin we skip straight to the normal home so nothing feels broken.
 */
class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        // MASTER-ONLY: a sign-in that came from a White Label landing button continues straight into the purchase.
        if (class_exists(\App\Services\WhiteLabel\Funnel\WlFunnel::class) && $request->session()->pull('wl_funnel_continue')) {
            return redirect()->route('wl-funnel.purchase');
        }

        if (WelcomeSettings::enabled()) {
            $request->session()->flash('just_registered', true);

            return redirect()->route('welcome');
        }

        return redirect()->intended(config('fortify.home', '/dashboard'));
    }
}

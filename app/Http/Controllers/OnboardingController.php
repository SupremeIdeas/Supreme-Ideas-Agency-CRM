<?php

namespace App\Http\Controllers;

use App\Support\AppExport;

/**
 * First-run onboarding (App Export). 3–4 admin-managed portrait slides the user
 * swipes/Next through on first app open, landing on REGISTER (new people) — returning
 * signed-out people go to LOGIN, signed-in people to the dashboard; never the marketing homepage. If onboarding is off
 * or empty, we send them straight to login so there's never a dead screen.
 */
class OnboardingController extends Controller
{
    public function __invoke()
    {
        // Already signed in → onboarding isn't for you.
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }
        if (! AppExport::onboardingEnabled()) {
            return redirect()->route('login');
        }

        return view('marketing.onboarding', [
            // a new person finishes on registration; sign-in stays one tap away (and is where returning people land)
            'registerUrl' => \Illuminate\Support\Facades\Route::has('register') ? route('register') : route('login'),
            'slides' => AppExport::onboardingSlides(),
            'appName' => AppExport::get('app_name'),
        ]);
    }
}

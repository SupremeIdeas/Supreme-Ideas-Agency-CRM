<?php

namespace App\Http\Controllers;

use App\Models\WalletGroup;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

/**
 * Business Suite Prompt 13 §3.3. The `signed` route middleware already
 * rejects a tampered/expired link before this ever runs — this only flips
 * the trust-signal timestamp, it never gates workspace creation or use.
 */
class BusinessEmailVerificationController extends Controller
{
    public function __invoke(Request $request, WalletGroup $walletGroup): RedirectResponse
    {
        if (! $walletGroup->business_email_verified_at) {
            $walletGroup->forceFill(['business_email_verified_at' => now()])->save();
        }

        return redirect()->route('business.workspace')->with('flash', 'Business email verified.');
    }
}

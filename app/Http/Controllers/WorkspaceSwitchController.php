<?php

namespace App\Http\Controllers;

use App\Models\WalletGroup;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Business Suite Prompt 13 §4.2 — "this replaces log in with a different
 * account," but it is never that: only a session key changes. No guard
 * change, no re-authentication, no effect on any other session/device.
 */
class WorkspaceSwitchController extends Controller
{
    public function __invoke(Request $request, ?WalletGroup $walletGroup = null): RedirectResponse
    {
        if ($walletGroup && ! Workspace::belongsTo($request->user(), $walletGroup)) {
            abort(403);
        }

        Workspace::setActive($walletGroup);

        return redirect()->back();
    }
}

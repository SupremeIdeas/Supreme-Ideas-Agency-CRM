<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The language switcher's one write path (header chip + marketing nav):
 * works for guests (session + cookie) and members (also saved to the profile).
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(Locale::available()))],
        ]);

        Locale::choose($request->user(), $data['locale']);

        // Back to where they were, but never to an off-site URL.
        $back = url()->previous();
        $host = parse_url($back, PHP_URL_HOST);
        $to = ($host !== null && $host === $request->getHost()) ? $back : url('/');

        return redirect()->to($to);
    }
}

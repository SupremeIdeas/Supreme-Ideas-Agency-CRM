<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * One behaviour for every feature switch: when a feature is switched off, a person who opens its page sees a friendly, skin-aware "Coming soon"
 * sheet (not a 404). Rules, in order:
 *  - a master-only feature on a non-master install is a plain 404 (it must not even be hinted at there);
 *  - anything that is not a browser page load (JSON, API, Livewire update, webhook, image, POST) stays a plain 404;
 *  - otherwise the sheet is shown with HTTP 200 and noindex.
 *
 * To put a new switch under this: add it to FEATURES (key, name, icon, blurb, master-only?), add its live check to isLive(), then guard the route with
 * the `feature.live:<key>` middleware (or ComingSoon::abortIfOff('<key>') inside a component).
 */
class ComingSoon
{
    /** @var array<string, array{name: string, icon: string, blurb: string, master: bool}> */
    public const FEATURES = [
        'business_suite' => ['name' => 'NaaraBusiness Suite', 'icon' => 'briefcase', 'master' => true,
            'blurb' => 'Get listed, run your team inbox and calls, and automate your business from one place. It is on the way.'],
        'business_call_flows' => ['name' => 'Call flows', 'icon' => 'phone', 'master' => true,
            'blurb' => 'Menus, ring groups and business hours for your team numbers are coming soon.'],
        'business_recording' => ['name' => 'Call recording', 'icon' => 'mic', 'master' => true,
            'blurb' => 'Record and review the calls on your team numbers, with a clear notice for every caller. Coming soon.'],
        'business_inbox' => ['name' => 'Shared inbox', 'icon' => 'message-circle', 'master' => true,
            'blurb' => 'Bring WhatsApp, Telegram and Instagram conversations into one team inbox. Coming soon.'],
        'omni_respond' => ['name' => 'Naara Respond inbox', 'icon' => 'message-circle', 'master' => true,
            'blurb' => 'One team inbox for Telegram, your website chat and more, with replies from your whole team. It is on the way.'],
        'business_ai_voice' => ['name' => 'AI voice assistant', 'icon' => 'mic', 'master' => true,
            'blurb' => 'An assistant that answers your team number, takes callback requests and hands over to a person. Coming soon.'],
        'n2n' => ['name' => 'Naara chat', 'icon' => 'message-circle', 'master' => true,
            'blurb' => 'Chat and call other Naara members straight from the app. We are getting it ready.'],
        'brain' => ['name' => 'NaaraBrain', 'icon' => 'sparkles', 'master' => true,
            'blurb' => 'An assistant that answers your questions about eSIMs, numbers and messages. We are getting it ready.'],
        'naara_gift' => ['name' => 'Naara Gift', 'icon' => 'gift', 'master' => false,
            'blurb' => 'Send gift cards for the brands people love, delivered instantly. The store opens soon.'],
        'merchant_widgets' => ['name' => 'Naara Widgets', 'icon' => 'globe', 'master' => true,
            'blurb' => 'Sell eSIMs and numbers on your own site with a ready-made widget. Coming soon.'],
        'app_download' => ['name' => 'The Naara app', 'icon' => 'download', 'master' => false,
            'blurb' => 'The mobile app is almost ready. We will let you know as soon as you can download it.'],
    ];

    /** Master-only modules are looked up by class name so this file is safe anywhere: a module that is not installed is simply "not live". */
    public static function isLive(string $key): bool
    {
        $call = static fn (string $class, string $method = 'enabled'): bool => class_exists($class) && (bool) $class::$method();

        return match ($key) {
            'business_suite' => $call('App\\Support\\Business\\BusinessSuiteSettings'),
            'business_call_flows' => $call('App\\Support\\Business\\BusinessSettings', 'callFlowsLive'),
            'business_ai_voice' => $call('App\\Support\\Business\\BusinessSettings', 'aiVoiceLive'),
            'omni_respond' => $call('App\\Support\\Business\\BusinessSettings', 'omniLive'),
            'business_inbox' => $call('App\\Support\\Business\\BusinessSettings', 'inboxLive'),
            'business_recording' => $call('App\\Support\\Business\\BusinessSettings', 'recordingLive'),
            'n2n' => $call('App\\Support\\N2N\\N2NSettings'),
            'brain' => $call('App\\Support\\Brain\\BrainSettings'),
            'merchant_widgets' => $call('App\\Support\\MerchantWidgetSettings'),
            // The page is live when ANY way to install is live: the direct APK, a store listing, or (master) a desktop/browser installer.
            'app_download' => (bool) AppExport::enabled() && ((bool) AppExport::get('download_enabled') || AppExport::androidBadgeVisible() || AppExport::iosBadgeVisible()
                || (class_exists(\App\Support\MasterMarketing\InstallerCatalog::class) && \App\Support\MasterMarketing\InstallerCatalog::live() !== [])),
            default => FeatureFlags::enabled($key),
        };
    }

    /** Null when the feature is live, else the response to send (the sheet, or a 404 where the sheet must not appear). */
    public static function responseIfOff(Request $request, string $key): ?Response
    {
        if (self::isLive($key)) {
            return null;
        }
        $f = self::FEATURES[$key] ?? ['name' => FeatureFlags::FEATURES[$key]['name'] ?? 'This feature', 'icon' => FeatureFlags::FEATURES[$key]['icon'] ?? 'sparkles', 'blurb' => 'We are getting it ready.', 'master' => false];
        if (($f['master'] && ! FeatureEntitlements::isMaster()) || ! self::isPageLoad($request)) {
            abort(404);
        }

        return response()->view('coming-soon', ['feature' => $f, 'key' => $key], 200)->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
    }

    /** For a component or controller: stop here with the sheet (or a 404) when the feature is off. */
    public static function abortIfOff(string $key): void
    {
        if (($r = self::responseIfOff(request(), $key)) !== null) {
            throw new HttpResponseException($r);
        }
    }

    /** A real browser page load: GET, wants HTML, and (where the browser says so) a document, not an image/script/XHR. */
    private static function isPageLoad(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->headers->has('X-Livewire') || $request->expectsJson()) {
            return false;
        }
        // An image/script request lists `*/*` too, so "accepts HTML" must be explicit (a bare Accept means a plain client, which counts as a page).
        $accept = (string) $request->headers->get('Accept', '');
        $dest = $request->headers->get('Sec-Fetch-Dest');

        return ($accept === '' || str_contains($accept, 'text/html')) && ($dest === null || $dest === 'document');
    }
}

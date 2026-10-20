<?php

namespace App\Http\Controllers;

use App\Support\AppExport;

/**
 * Public "Download the App" page (App Export §1). Android shows a direct APK
 * download the moment a signed APK build is marked ready — no store approval
 * needed for sideloading. iOS shows ONLY the App Store badge, and only once the
 * admin marks that listing live: outside the EU there is simply no web
 * direct-install path for iOS, so we never render a fake one.
 */
class DownloadAppController extends Controller
{
    public function __invoke()
    {
        \App\Support\ComingSoon::abortIfOff('app_download');

        $pageUrl = route('download');
        $hasInstallers = class_exists(\App\Support\MasterMarketing\InstallerCatalog::class);
        $ua = (string) request()->userAgent();
        $device = match (true) {
            (bool) preg_match('/android/i', $ua) => 'android',
            (bool) preg_match('/iphone|ipad|ipod/i', $ua) => 'ios',
            default => $hasInstallers ? \App\Support\MasterMarketing\InstallerCatalog::detect($ua) : null,
        };

        return view('marketing.download', [
            'c' => \App\Support\SiteContent::page('download', includeHidden: true)['hero'] ?? [],
            'appName' => AppExport::get('app_name'),
            'version' => AppExport::get('version'),
            'androidDownloadable' => AppExport::androidDownloadable(),
            'apkUrl' => AppExport::get('android_apk_url'),
            'androidBadge' => AppExport::androidBadgeVisible(),
            'androidStoreUrl' => AppExport::get('android_store_url'),
            'iosBadge' => AppExport::iosBadgeVisible(),
            'iosStoreUrl' => AppExport::get('ios_store_url'),
            'qr' => AppExport::qrDataUri($pageUrl, 200),
            // Desktop + browser installers (MASTER ONLY; absent on a fork)
            'installers' => class_exists(\App\Support\MasterMarketing\InstallerCatalog::class) ? \App\Support\MasterMarketing\InstallerCatalog::live() : [],
            'installerMeta' => class_exists(\App\Support\MasterMarketing\InstallerCatalog::class) ? \App\Support\MasterMarketing\InstallerCatalog::platforms() : [],
            'detected' => $hasInstallers ? \App\Support\MasterMarketing\InstallerCatalog::detect(request()->userAgent()) : null,
            'device' => $device,
            'families' => $hasInstallers ? \App\Support\MasterMarketing\InstallerCatalog::families() : [],
        ]);
    }
}

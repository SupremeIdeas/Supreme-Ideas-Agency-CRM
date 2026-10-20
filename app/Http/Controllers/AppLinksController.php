<?php

namespace App\Http\Controllers;

use App\Support\AppStudio;
use Illuminate\Http\JsonResponse;

/**
 * The two files the stores check before letting links to this site open the installed app (Android App Links, iOS Universal Links). Built from the App Studio settings:
 * the package/bundle id plus the Android release-signing fingerprint and the Apple Team ID. Empty (a plain 404) until those are set, so nothing half-configured is published.
 */
class AppLinksController extends Controller
{
    public function assetLinks(): JsonResponse
    {
        $prints = AppStudio::androidFingerprints();
        abort_if($prints === [], 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => AppStudio::androidPackage(), 'sha256_cert_fingerprints' => $prints],
        ]])->header('Cache-Control', 'public, max-age=3600');
    }

    public function appleAppSiteAssociation(): JsonResponse
    {
        $team = AppStudio::iosTeamId();
        abort_if($team === '', 404);
        $appId = $team.'.'.AppStudio::iosBundle();

        return response()->json([
            'applinks' => ['apps' => [], 'details' => [['appIDs' => [$appId], 'components' => [['/' => '/*']]]]],
            'webcredentials' => ['apps' => [$appId]],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}

<?php

namespace App\Http\Controllers\MasterMarketing;

use App\Http\Controllers\Controller;
use App\Support\MasterMarketing\VideoVault;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * MASTER ONLY. Streams a vault video to a browser's media element: signed and expiring link, Range requests (seeking), never as an attachment. A request that is a page
 * navigation (someone pasting the link in the address bar, or "save link as") is refused; so is a request from another website.
 */
class VideoStreamController extends Controller
{
    public function __invoke(Request $request)
    {
        $path = VideoVault::pathFromToken((string) $request->query('v'));
        abort_if($path === null, 404);
        abort_if(in_array($request->header('Sec-Fetch-Dest'), ['document', 'iframe', 'embed', 'object'], true), 403);
        $ref = $request->headers->get('referer');
        abort_if($ref && parse_url($ref, PHP_URL_HOST) !== $request->getHost(), 403);

        $disk = Storage::disk(VideoVault::disk());
        abort_unless($disk->exists($path), 404);
        $mime = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
        $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow', 'Content-Disposition' => 'inline'];

        // a local disk streams with Range support; an object store hands the browser a link that lives for minutes
        if ($disk->getAdapter() instanceof \League\Flysystem\Local\LocalFilesystemAdapter) {
            return response()->file($disk->path($path), $headers);
        }

        return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(10)), 302, ['Cache-Control' => 'private, no-store']);
    }
}

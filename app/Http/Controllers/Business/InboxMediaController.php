<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\Inbox\InboxMedia;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Storage;

/**
 * Serves a stored inbox file to whoever holds a valid signed link: the channel provider fetching media to deliver it, or a teammate viewing the conversation
 * (MASTER-ONLY, Business Suite). The route is `signed` and expiring, and only ever resolves a path under inbox-out/, so it can never be walked elsewhere.
 */
class InboxMediaController extends Controller
{
    private const TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', '3gp' => 'video/3gpp',
        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'amr' => 'audio/amr',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
    ];

    public function __invoke(string $path)
    {
        $decoded = base64_decode(strtr($path, '-_', '+/'), true);
        abort_unless(is_string($decoded) && preg_match('#^inbox-out/\d+/[a-f0-9\-]{36}\.[a-z0-9]{1,8}$#', $decoded), 404);

        $disk = Storage::disk(MediaStorage::privateDisk());
        abort_unless($disk->exists($decoded), 404);

        $ext = strtolower(pathinfo($decoded, PATHINFO_EXTENSION));
        // Anything not on the list is sent as a download, never rendered by the browser.
        $type = self::TYPES[$ext] ?? 'application/octet-stream';
        $inline = isset(self::TYPES[$ext]);

        return response($disk->get($decoded), 200, [
            'Content-Type' => $type,
            'Content-Disposition' => $inline ? 'inline' : 'attachment',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}

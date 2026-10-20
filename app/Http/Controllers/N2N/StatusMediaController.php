<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\BrandPartner;
use App\Models\N2N\StatusPost;
use App\Services\N2N\Updates\UpdatesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Serves a status post's photo, video or voice (MASTER-ONLY): only to its owner or a member the Updates rules let see it, only while it has not expired. Never a public URL. */
class StatusMediaController extends Controller
{
    public function show(Request $request, int $post, UpdatesService $updates): Response
    {
        $p = StatusPost::query()->active()->whereNotNull('storage_key')->find($post);
        $viewer = $request->user();
        $allowed = false;
        if ($p !== null) {
            $allowed = (int) $p->user_id === (int) $viewer->id;
            if (! $allowed) {
                $brandId = BrandPartner::query()->where('owner_user_id', $p->user_id)->value('id');
                $items = $brandId ? $updates->items($viewer, (int) $brandId, 'status') : null;
                $allowed = $items !== null && collect($items)->contains('id', $p->id);
            }
        }
        abort_unless($allowed, 404);   // missing, expired and not-yours answer the same

        $store = Storage::disk($p->disk);
        if (config("filesystems.disks.{$p->disk}.driver") === 's3') {
            try {
                return redirect()->away($store->temporaryUrl($p->storage_key, now()->addMinutes(10)))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
            } catch (\Throwable) {
                // fall through to streaming
            }
        }
        abort_unless($store->exists($p->storage_key), 404);

        return $store->response($p->storage_key, null, ['Content-Type' => $p->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"], 'inline');
    }
}

<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\N2N\ProfilePicture;
use App\Models\N2N\Thumbnail;
use App\Models\User;
use App\Services\N2N\Profiles\ProfileVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Serves profile pictures (only after the visibility check) and cached video thumbnails (N2N Batch 8, MASTER-ONLY). Every miss answers the same 404. */
class ProfileMediaController extends Controller
{
    public function picture(Request $request, int $userId, int $slot): Response
    {
        $subject = User::query()->find($userId);
        $pic = $subject ? ProfilePicture::query()->where('user_id', $subject->id)->where('slot', $slot)->first() : null;
        $r = $subject ? ProfileVisibility::resolve($request->user(), $subject) : null;
        abort_if($pic === null || $r === null || ! in_array('images', $r['fields'], true), 404);

        return $this->stream($pic->disk, $pic->storage_key, 'private, max-age=300');
    }

    public function thumb(string $provider, string $id): Response
    {
        $t = Thumbnail::query()->where('provider', $provider)->where('provider_id', $id)->where('status', 'ok')->first();
        abort_if($t === null, 404);

        return $this->stream($t->disk, $t->storage_key, 'public, max-age=86400');
    }

    private function stream(string $disk, string $key, string $cache): Response
    {
        $store = Storage::disk($disk);
        abort_unless($store->exists($key), 404);

        return response($store->get($key), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => $cache, 'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline']);
    }
}

<?php

namespace App\Http\Controllers\Handbooks;

use App\Http\Controllers\Controller;
use App\Models\Handbook;
use App\Services\Handbooks\HandbookStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Handbook files (MASTER-ONLY). Covers are marketing images and are public and cacheable; every other file is entitlement-checked on each request (see the reader and download routes). */
class HandbookFileController extends Controller
{
    public function cover(string $slug): StreamedResponse
    {
        $b = Handbook::query()->published()->where('slug', $slug)->firstOrFail();
        abort_unless($b->cover_path && Storage::disk(HandbookStorage::disk())->exists($b->cover_path), 404);
        $mime = match (strtolower(pathinfo($b->cover_path, PATHINFO_EXTENSION))) {
            'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg',
        };

        return Storage::disk(HandbookStorage::disk())->response($b->cover_path, null, ['Content-Type' => $mime, 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** The back cover image shown on the book page (published titles only). */
    public function backCover(string $slug): StreamedResponse
    {
        $b = Handbook::query()->published()->where('slug', $slug)->firstOrFail();
        abort_unless($b->back_cover_path && Storage::disk(HandbookStorage::disk())->exists($b->back_cover_path), 404);
        $mime = match (strtolower(pathinfo($b->back_cover_path, PATHINFO_EXTENSION))) {
            'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg',
        };

        return Storage::disk(HandbookStorage::disk())->response($b->back_cover_path, null, ['Content-Type' => $mime, 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}

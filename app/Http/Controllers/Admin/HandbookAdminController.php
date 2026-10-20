<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Handbook;
use App\Services\Handbooks\HandbookStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Admin-side handbook files and the "preview as reader" view (MASTER-ONLY). Previewing never consumes an entitlement and writes no reading state. */
class HandbookAdminController extends Controller
{
    private function book(int $id): Handbook
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);

        return Handbook::query()->findOrFail($id);
    }

    public function cover(int $id): Response
    {
        $b = $this->book($id);
        abort_unless($b->cover_path && Storage::disk(HandbookStorage::disk())->exists($b->cover_path), 404);

        return Storage::disk(HandbookStorage::disk())->response($b->cover_path, null, ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function backCover(int $id): Response
    {
        $b = $this->book($id);
        abort_unless($b->back_cover_path && Storage::disk(HandbookStorage::disk())->exists($b->back_cover_path), 404);

        return Storage::disk(HandbookStorage::disk())->response($b->back_cover_path, null, ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Download any file on record as an attachment (EPUB for offline reading, PDF, covers). Admin only, no entitlement involved. */
    public function download(int $id, string $kind): Response
    {
        $b = $this->book($id);
        $col = ['epub' => 'epub_path', 'pdf' => 'pdf_path', 'cover' => 'cover_path', 'back-cover' => 'back_cover_path'][$kind] ?? null;
        abort_if($col === null, 404);
        $path = $b->{$col};
        abort_unless($path && Storage::disk(HandbookStorage::disk())->exists($path), 404);
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: $kind;
        $name = ($b->catalogue_id ? $b->catalogue_id.'-' : '').$b->slug.($kind === 'cover' || $kind === 'back-cover' ? '-'.$kind : '').'.'.$ext;

        return Storage::disk(HandbookStorage::disk())->download($path, $name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(int $id)
    {
        $b = $this->book($id);
        abort_unless($b->epub_path, 404);

        return view('handbooks.reader', [
            'b' => $b, 'canDownload' => true, 'state' => null,
            'cfg' => ['uid' => 0, 'slug' => 'preview-'.$b->slug, 'edition' => $b->edition, 'title' => $b->title, 'revalidateDays' => 0, 'locator' => null, 'toc' => $b->toc ?: [],
                'urls' => ['epub' => route('admin.handbooks.preview.epub', $b->id), 'key' => route('admin.handbooks.preview.key', $b->id), 'progress' => route('admin.handbooks.preview.noop', $b->id), 'bookmarks' => route('admin.handbooks.preview.noop', $b->id)]],
        ]);
    }

    public function epub(int $id): Response
    {
        $b = $this->book($id);
        abort_unless($b->epub_path && Storage::disk(HandbookStorage::disk())->exists($b->epub_path), 404);

        return Storage::disk(HandbookStorage::disk())->response($b->epub_path, $b->slug.'.epub', ['Content-Type' => 'application/epub+zip', 'Cache-Control' => 'private, no-store'], 'inline');
    }

    public function key(int $id): JsonResponse
    {
        $this->book($id);

        return response()->json(['key' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='), 'edition' => 0, 'validated_at' => now()->timestamp, 'valid_for_days' => 0], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function noop(int $id): JsonResponse
    {
        $this->book($id);

        return response()->json([]);
    }
}

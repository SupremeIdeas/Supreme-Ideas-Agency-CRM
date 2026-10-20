<?php

namespace App\Http\Controllers\Handbooks;

use App\Http\Controllers\Controller;
use App\Jobs\AlertAdminJob;
use App\Models\Handbook;
use App\Models\HandbookBookmark;
use App\Models\HandbookDownload;
use App\Models\HandbookReadingState;
use App\Services\Handbooks\HandbookAccess;
use App\Services\Handbooks\HandbookBackCover;
use App\Services\Handbooks\HandbookStorage;
use App\Services\Handbooks\HandbookUnlockService;
use App\Services\Handbooks\HandbookWatermark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The reader and file delivery (MASTER-ONLY). EVERY request here re-checks the entitlement for the signed-in member (an IDOR-proof design: the slug picks a book, the
 * access matrix decides, and a book the person does not hold answers 404 exactly like one that does not exist). Files come only from the private disk.
 */
class HandbookReaderController extends Controller
{
    public function __construct(private readonly HandbookAccess $access, private readonly HandbookStorage $storage)
    {
    }

    /** The book the signed-in person may READ, or a 404 indistinguishable from "no such book". */
    private function readable(string $slug): Handbook
    {
        $user = auth()->user();
        app(HandbookUnlockService::class)->syncProLocks($user);
        $b = Handbook::query()->published()->where('slug', $slug)->first();
        abort_unless($b && $this->access->canRead($user, $b), 404);

        return $b;
    }

    /** The offline worker for the reader (MASTER-ONLY). It caches only the reader page and the built scripts and styles; the book, the key, progress and bookmarks are never cached by it. */
    public function worker(): Response
    {
        $js = <<<'JS'
const SHELL = 'naara-reader-shell-v1';
self.addEventListener('install', (e) => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (e) => {
    const r = e.request;
    if (r.method !== 'GET') return;
    const u = new URL(r.url);
    if (u.origin !== location.origin) return;
    if (/^\/library\/[a-z0-9-]+\/(epub|key|progress|bookmarks|download|audio)/.test(u.pathname)) return;   // never cached here
    const isReader = r.mode === 'navigate' && /^\/library\/[a-z0-9-]+\/read$/.test(u.pathname);
    const isAsset = u.pathname.startsWith('/build/assets/');
    if (isReader) {
        e.respondWith(fetch(r).then((res) => { if (res.ok) { const copy = res.clone(); caches.open(SHELL).then((c) => c.put(r, copy)); } return res; }).catch(() => caches.match(r)));
    } else if (isAsset) {
        e.respondWith(caches.match(r).then((hit) => hit || fetch(r).then((res) => { if (res.ok) { const copy = res.clone(); caches.open(SHELL).then((c) => c.put(r, copy)); } return res; })));
    }
});
JS;

        return response($js, 200, ['Content-Type' => 'text/javascript; charset=UTF-8', 'Cache-Control' => 'no-cache', 'Service-Worker-Allowed' => '/library/']);
    }

    public function show(string $slug)
    {
        $b = $this->readable($slug);
        $state = HandbookReadingState::query()->where('user_id', auth()->id())->where('handbook_id', $b->id)->first();

        return view('handbooks.reader', [
            'b' => $b, 'canDownload' => $this->access->canDownload(auth()->user(), $b), 'state' => $state,
            'cfg' => [
                'uid' => (int) auth()->id(), 'slug' => $b->slug, 'edition' => $b->edition, 'title' => $b->title, 'revalidateDays' => (int) config('handbooks.revalidate_days', 30),
                'urls' => ['epub' => route('handbooks.file.read', $b->slug), 'key' => route('handbooks.key', $b->slug), 'progress' => route('handbooks.progress', $b->slug), 'bookmarks' => route('handbooks.bookmarks', $b->slug)],
                'locator' => $state?->locator, 'toc' => $b->toc ?: [],
            ],
        ]);
    }

    /** The EPUB bytes for the in-app reader. Short-lived, never cached, never a download prompt. */
    public function read(string $slug): Response
    {
        $b = $this->readable($slug);
        abort_unless($this->storage->exists($b->epub_path), 404);
        $path = app(HandbookBackCover::class)->epubPath($b);   // closed with the back cover when there is one

        return Storage::disk(HandbookStorage::disk())->response($path, $b->slug.'.epub', ['Content-Type' => 'application/epub+zip', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    /** The per-person key that encrypts the offline copy, derived on the server and only handed to someone who currently holds the book; it also says how long the copy may be used offline. */
    public function key(string $slug): JsonResponse
    {
        $b = $this->readable($slug);
        $raw = hash_hmac('sha256', 'handbook-offline|'.auth()->id().'|'.$b->id.'|'.$b->edition, (string) config('app.key'), true);

        return response()->json([
            'key' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 'edition' => $b->edition, 'validated_at' => now()->timestamp, 'valid_for_days' => (int) config('handbooks.revalidate_days', 30),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function progress(Request $request, string $slug): JsonResponse
    {
        $b = $this->readable($slug);
        if ($request->isMethod('post')) {
            $d = $request->validate(['locator' => ['required', 'string', 'max:400'], 'percent' => ['required', 'numeric', 'min:0', 'max:100']]);
            HandbookReadingState::query()->updateOrCreate(['user_id' => auth()->id(), 'handbook_id' => $b->id], ['locator' => $d['locator'], 'percent' => round((float) $d['percent'], 2), 'updated_at' => now()]);

            return response()->json(['ok' => true]);
        }
        $s = HandbookReadingState::query()->where('user_id', auth()->id())->where('handbook_id', $b->id)->first();

        return response()->json(['locator' => $s?->locator, 'percent' => (float) ($s?->percent ?? 0)]);
    }

    public function bookmarks(Request $request, string $slug): JsonResponse
    {
        $b = $this->readable($slug);
        $q = HandbookBookmark::query()->where('user_id', auth()->id())->where('handbook_id', $b->id);
        if ($request->isMethod('post')) {
            $d = $request->validate(['locator' => ['required', 'string', 'max:400'], 'label' => ['nullable', 'string', 'max:160']]);
            abort_if((clone $q)->count() >= 200, 422, 'Too many bookmarks.');
            $m = HandbookBookmark::query()->firstOrCreate(['user_id' => auth()->id(), 'handbook_id' => $b->id, 'locator' => $d['locator']], ['label' => $d['label'] ?? null]);

            return response()->json(['id' => $m->id, 'locator' => $m->locator, 'label' => $m->label]);
        }
        if ($request->isMethod('delete')) {
            (clone $q)->whereKey((int) $request->input('id'))->delete();

            return response()->json(['ok' => true]);
        }

        return response()->json((clone $q)->orderBy('id')->get(['id', 'locator', 'label']));
    }

    /** Owned only: the current edition as a per-buyer-watermarked EPUB or PDF. Rate-limited and logged. */
    public function download(Request $request, string $slug, string $format): Response
    {
        abort_unless(in_array($format, ['epub', 'pdf'], true), 404);
        $user = auth()->user();
        $b = Handbook::query()->published()->where('slug', $slug)->first();
        abort_unless($b && $this->access->canDownload($user, $b), 404);
        $path = $format === 'epub' ? $b->epub_path : $b->pdf_path;
        abort_unless($this->storage->exists($path), 404);
        $key = 'handbook-dl:'.$user->id;
        abort_if(RateLimiter::tooManyAttempts($key, (int) config('handbooks.download_per_hour', 6)), 429, 'You have reached the download limit for this hour. Please try again later.');
        RateLimiter::hit($key, 3600);

        $ref = 'HB-'.$b->id.'-'.$user->id;
        $wm = app(HandbookWatermark::class);
        $closed = app(HandbookBackCover::class);
        $path = $format === 'epub' ? $closed->epubPath($b) : $closed->pdfPath($b);   // the back cover goes in before the buyer's licence stamp
        $tmp = $this->storage->tempCopy($path);
        try {
            $bytes = $format === 'epub' ? $wm->stampEpub($tmp, $user, $b, $ref) : $wm->stampPdf($tmp, $user, $ref);
        } finally {
            @unlink($tmp);
        }
        $stamped = $bytes !== null;
        if (! $stamped) {
            $bytes = $this->storage->get($path);   // the original: the buyer paid for a file and gets it, but the admin is told it went out unstamped
            AlertAdminJob::dispatch(code: 'handbook_unstamped_download', message: 'A '.strtoupper($format).' of "'.$b->title.'" went out without a buyer watermark (the file could not be stamped).', context: ['handbook_id' => $b->id, 'user_id' => $user->id]);
        }
        HandbookDownload::query()->create(['user_id' => $user->id, 'handbook_id' => $b->id, 'format' => $format, 'edition' => $b->edition, 'stamped' => $stamped, 'ip_hash' => \App\Support\CookieConsent\CookieConsent::hashIp($request->ip()), 'created_at' => now()]);

        return response($bytes, 200, [
            'Content-Type' => $format === 'epub' ? 'application/epub+zip' : 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$b->slug.'-e'.$b->edition.'.'.$format.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

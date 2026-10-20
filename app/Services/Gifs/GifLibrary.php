<?php

namespace App\Services\Gifs;

use App\Models\GifCategory;
use App\Models\GifLibraryItem;
use App\Support\Security\SsrfGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The composer's GIF and sticker library: reading it for the chat, registering the shipped set, and letting an admin add to it from an upload or any
 * web address. Every added file is checked to be a real GIF of a sane size before it is stored, and a web address goes through the SSRF guard (no private
 * addresses, no redirects into them) before a single byte is fetched.
 */
class GifLibrary
{
    /** Every shelf, in the admin's order. @return \Illuminate\Database\Eloquent\Collection<int, GifCategory> */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return GifCategory::query()->orderBy('sort')->orderBy('id')->get();
    }

    /** @return list<string> every slug an item can sit on */
    public function slugs(): array
    {
        return GifCategory::query()->orderBy('sort')->orderBy('id')->pluck('slug')->all();
    }

    public static function validSurface(?string $surface): ?string
    {
        return is_string($surface) && array_key_exists($surface, (array) config('gif_library.surfaces')) ? $surface : null;
    }

    /**
     * The shelves a chat shows: active ones that are allowed on its surface (and, by default, that have something on them).
     *
     * @return list<array{slug: string, label: string, count: int}>
     */
    public function categories(bool $onlyWithItems = true, ?string $surface = null): array
    {
        $counts = GifLibraryItem::query()->where('is_active', true)->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category');
        $out = [];
        foreach ($this->all() as $c) {
            if (! $c->is_active || ! $c->showsOn($surface)) {
                continue;
            }
            $n = (int) ($counts[$c->slug] ?? 0);
            if ($n > 0 || ! $onlyWithItems) {
                $out[] = ['slug' => $c->slug, 'label' => $c->label(), 'count' => $n];
            }
        }

        return $out;
    }

    /** What the composer shows: active items on active shelves allowed on this surface, optionally on one shelf or matching a search. @return list<array{id: int, name: string, category: string, url: string}> */
    public function listing(?string $category = null, ?string $q = null, int $limit = 600, ?string $surface = null): array
    {
        $q = trim((string) $q);
        $allowed = collect($this->categories(false, $surface))->pluck('slug')->all();
        $order = array_flip($allowed);
        if ($category && ! in_array($category, $allowed, true)) {
            return [];   // asked for a shelf this chat may not show (or that does not exist)
        }

        return GifLibraryItem::query()->where('is_active', true)->whereIn('category', $allowed)
            ->when($category, fn ($x) => $x->where('category', $category))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%')->orWhere('category', 'like', '%'.Str::lower($q).'%')))
            ->orderBy('sort')->orderBy('id')->limit($limit)->get()
            ->sortBy(fn (GifLibraryItem $i) => $order[$i->category] ?? 999)->values()
            ->map(fn (GifLibraryItem $i) => ['id' => $i->id, 'name' => $i->name, 'category' => $i->category, 'url' => $i->url()])->all();
    }

    // ── shelves (categories) ────────────────────────────────────────────────

    /** @param list<string>|null $surfaces null = every chat. @throws \InvalidArgumentException */
    public function createCategory(string $name, ?array $surfaces = null): GifCategory
    {
        $name = $this->cleanName($name);
        if (GifCategory::count() >= (int) config('gif_library.max_categories')) {
            throw new \InvalidArgumentException('You have reached the limit of '.config('gif_library.max_categories').' categories. Remove one you no longer use first.');
        }
        if (GifCategory::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            throw new \InvalidArgumentException('A category called “'.$name.'” already exists.');
        }
        $base = Str::limit(Str::slug($name) ?: 'shelf', 20, '');
        $slug = $base;
        for ($n = 2; GifCategory::where('slug', $slug)->exists(); $n++) {
            $slug = Str::limit($base, 20 - strlen((string) $n), '').'-'.$n;
        }

        return GifCategory::create(['slug' => $slug, 'name' => $name, 'sort' => (int) GifCategory::max('sort') + 1, 'is_active' => true, 'is_builtin' => false, 'surfaces' => $this->cleanSurfaces($surfaces)]);
    }

    /** @param list<string>|null $surfaces @throws \InvalidArgumentException */
    public function updateCategory(GifCategory $c, string $name, ?array $surfaces, bool $active): GifCategory
    {
        $name = $this->cleanName($name);
        if (GifCategory::where('id', '!=', $c->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            throw new \InvalidArgumentException('A category called “'.$name.'” already exists.');
        }
        $c->forceFill(['name' => $name, 'surfaces' => $this->cleanSurfaces($surfaces), 'is_active' => $active])->save();

        return $c;
    }

    /** Move a shelf one place up (-1) or down (+1). */
    public function moveCategory(GifCategory $c, int $dir): void
    {
        $all = $this->all()->values();
        $i = $all->search(fn ($x) => $x->id === $c->id);
        $j = $i + ($dir < 0 ? -1 : 1);
        if ($i === false || ! isset($all[$j])) {
            return;
        }
        $order = $all->pluck('id')->all();
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        foreach ($order as $pos => $id) {
            GifCategory::whereKey($id)->update(['sort' => $pos]);
        }
    }

    /** Delete a shelf. Its GIFs move to `$moveTo` (or are removed when null). @throws \InvalidArgumentException */
    public function deleteCategory(GifCategory $c, ?string $moveTo): int
    {
        if (GifCategory::count() <= 1) {
            throw new \InvalidArgumentException('Keep at least one category.');
        }
        $n = GifLibraryItem::where('category', $c->slug)->count();
        if ($moveTo !== null && $moveTo !== '') {
            if ($moveTo === $c->slug || ! GifCategory::where('slug', $moveTo)->exists()) {
                throw new \InvalidArgumentException('Pick another category to move the GIFs to.');
            }
            GifLibraryItem::where('category', $c->slug)->update(['category' => $moveTo]);
        } else {
            GifLibraryItem::where('category', $c->slug)->get()->each->delete();   // model delete so uploaded files are removed from disk
        }
        $c->delete();

        return $n;
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', strip_tags($name)));
        if ($name === '' || mb_strlen($name) > 40) {
            throw new \InvalidArgumentException('Give the category a name of up to 40 characters.');
        }

        return $name;
    }

    /** @param list<string>|null $surfaces @return list<string>|null */
    private function cleanSurfaces(?array $surfaces): ?array
    {
        if ($surfaces === null) {
            return null;
        }
        $known = array_keys((array) config('gif_library.surfaces'));
        $kept = array_values(array_intersect($known, $surfaces));

        return ($kept === [] || count($kept) === count($known)) ? null : $kept;   // none ticked or all ticked both mean "every chat"
    }

    /** Register every GIF shipped under public/images/gifs/<category>/. Safe to run again: it only adds what is missing. @return int rows added */
    public function syncSeeds(): int
    {
        $added = 0;
        $have = \Illuminate\Support\Facades\Schema::hasTable('gif_categories') ? GifCategory::pluck('slug')->all() : null;   // null on the very first migrations, before shelves exist
        foreach ((array) config('gif_library.categories') as $cat) {
            if ($have !== null && ! in_array($cat, $have, true)) {
                continue;   // the admin deleted this shelf on purpose: do not bring it back
            }
            $dir = public_path('images/gifs/'.$cat);
            if (! is_dir($dir)) {
                continue;
            }
            $files = collect(File::files($dir))->filter(fn ($f) => strtolower($f->getExtension()) === 'gif')->sortBy(fn ($f) => $f->getFilename())->values();
            foreach ($files as $i => $f) {
                $path = $cat.'/'.$f->getFilename();
                $size = @getimagesize($f->getPathname()) ?: [null, null];
                $row = GifLibraryItem::firstOrCreate(['disk' => null, 'path' => $path], [
                    'category' => $cat, 'name' => Str::of(pathinfo($f->getFilename(), PATHINFO_FILENAME))->replace('-', ' ')->ucfirst()->toString(),
                    'source' => 'seed', 'license' => 'Original artwork made for Naara', 'width' => $size[0], 'height' => $size[1], 'bytes' => $f->getSize(), 'sort' => $i,
                ]);
                $added += $row->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $added;
    }

    /** @throws \InvalidArgumentException with a sentence the admin can read */
    public function addFromUpload(UploadedFile $file, string $category, ?string $name = null): GifLibraryItem
    {
        if ($file->getSize() > (int) config('gif_library.max_bytes')) {
            throw new \InvalidArgumentException('“'.$file->getClientOriginalName().'” is larger than '.round(config('gif_library.max_bytes') / 1048576).' MB.');
        }

        return $this->store((string) file_get_contents($file->getRealPath()), $category, $name ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'upload', null);
    }

    /** @throws \InvalidArgumentException */
    public function addFromUrl(string $url, string $category, ?string $name = null): GifLibraryItem
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('Paste a full web address that starts with https://.');
        }
        $max = (int) config('gif_library.max_bytes');
        $body = null;
        for ($hop = 0; $hop < 4; $hop++) {
            try {
                SsrfGuard::assert($url);
            } catch (\Throwable) {
                throw new \InvalidArgumentException('That address is not allowed (it points to a private or internal network).');
            }
            try {
                $r = Http::timeout(12)->withHeaders(['Accept' => 'image/gif,*/*;q=0.5', 'User-Agent' => 'NaaraGifImporter/1.0'])
                    ->withOptions(['allow_redirects' => false, 'on_headers' => function ($resp) use ($max) {
                        if ((int) $resp->getHeaderLine('Content-Length') > $max) {
                            throw new \RuntimeException('too large');
                        }
                    }])->get($url);
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException(str_contains($e->getMessage(), 'too large') ? 'That file is larger than '.round($max / 1048576).' MB.' : 'That address could not be reached.');
            }
            if (in_array($r->status(), [301, 302, 303, 307, 308], true) && $r->header('Location')) {
                $url = self::absolute($url, (string) $r->header('Location'));

                continue;
            }
            if (! $r->successful()) {
                throw new \InvalidArgumentException('That address answered with an error ('.$r->status().').');
            }
            $body = $r->body();
            break;
        }
        if ($body === null) {
            throw new \InvalidArgumentException('That address redirects too many times.');
        }
        $guess = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);

        return $this->store($body, $category, $name ?: ($guess !== '' ? $guess : 'GIF'), 'url', $url);
    }

    private static function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $p = parse_url($base);

        return ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '').(str_starts_with($location, '/') ? $location : '/'.$location);
    }

    /** @throws \InvalidArgumentException */
    private function store(string $bytes, string $category, string $name, string $source, ?string $sourceUrl): GifLibraryItem
    {
        if (! GifCategory::where('slug', $category)->exists()) {
            throw new \InvalidArgumentException('Pick a category.');
        }
        if (strlen($bytes) > (int) config('gif_library.max_bytes')) {
            throw new \InvalidArgumentException('That file is larger than '.round(config('gif_library.max_bytes') / 1048576).' MB.');
        }
        if (! preg_match('/^GIF8[79]a/', $bytes)) {
            throw new \InvalidArgumentException('That is not a GIF file.');
        }
        $w = unpack('v', substr($bytes, 6, 2))[1];
        $h = unpack('v', substr($bytes, 8, 2))[1];
        if ($w < 16 || $h < 16 || max($w, $h) > (int) config('gif_library.max_dimension')) {
            throw new \InvalidArgumentException('Pick a GIF between 16 and '.config('gif_library.max_dimension').' pixels on its longest side (this one is '.$w.' x '.$h.').');
        }
        if (GifLibraryItem::where('category', $category)->count() >= (int) config('gif_library.max_per_category')) {
            throw new \InvalidArgumentException('That category is full.');
        }
        $disk = GifLibraryItem::mediaDisk();
        $path = 'gif-library/'.$category.'/'.Str::uuid().'.gif';
        Storage::disk($disk)->put($path, $bytes, 'public');

        return GifLibraryItem::create([
            'category' => $category, 'name' => Str::limit(trim(preg_replace('/[^\p{L}\p{N} \-\'!?.,]+/u', ' ', $name)) ?: 'GIF', 120, ''), 'path' => $path, 'disk' => $disk,
            'source' => $source, 'source_url' => $sourceUrl ? Str::limit($sourceUrl, 490, '') : null, 'width' => $w, 'height' => $h, 'bytes' => strlen($bytes),
            'sort' => (int) GifLibraryItem::where('category', $category)->max('sort') + 1,
        ]);
    }
}

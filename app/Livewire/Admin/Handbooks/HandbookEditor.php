<?php

namespace App\Livewire\Admin\Handbooks;

use App\Models\Handbook;
use App\Models\HandbookAudioTrack;
use App\Models\HandbookCategory;
use App\Models\HandbookExternalLink;
use App\Models\HandbookMood;
use App\Models\HandbookSeries;
use App\Services\Handbooks\HandbookCatalogueService;
use App\Services\Handbooks\HandbookStorage;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Admin handbook editor (MASTER-ONLY): details, cover, EPUB (validated, with extracted contents), PDF, taxonomy, series, marketplace links, audiobook, editions, publish checklist. */
#[Layout('components.layouts.admin')]
class HandbookEditor extends Component
{
    use WithFileUploads;

    public ?int $handbookId = null;

    public string $title = '';

    public string $subtitle = '';

    public string $slug = '';

    public string $catalogueId = '';

    public string $description = '';

    public string $language = 'en';

    public string $countryCode = '';

    public string $seriesSlot = '';

    public string $seriesId = '';

    public string $newSeries = '';

    public string $price = '';

    public string $lastVerified = '';

    public string $isbnEbook = '';

    public string $isbnPaperback = '';

    public string $isbnHardcover = '';

    /** @var list<int> */
    public array $categoryIds = [];

    /** @var list<int> */
    public array $moodIds = [];

    public string $newCategory = '';

    /** @var array<string, array{url:string, enabled:bool, availability:string}> */
    public array $links = [];

    public bool $isFeatured = false;

    public bool $unlockViaEsim = true;

    public bool $unlockViaPro = true;

    public bool $allowPurchase = true;

    public string $extraCountries = '';

    public string $audioPrice = '';

    public bool $audioEnabled = false;

    public bool $audioStream = true;

    public bool $audioDownload = false;

    public string $trackTitle = '';

    public string $trackSeconds = '';

    public $coverUpload;

    public $backCoverUpload;

    public $epubUpload;

    public $pdfUpload;

    public $trackUpload;

    public string $editionNotes = '';

    /** @var array{errors:list<string>, warnings:list<string>}|null */
    public ?array $report = null;

    public ?string $flash = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(?int $handbook = null): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        foreach ((array) config('handbooks.moods') as $mood) {
            HandbookMood::query()->firstOrCreate(['slug' => Str::slug($mood)], ['name' => $mood]);
        }
        foreach (array_keys(HandbookExternalLink::MARKETPLACES) as $m) {
            $this->links[$m] = ['url' => '', 'enabled' => false, 'availability' => 'coming_soon'];
        }
        if ($handbook) {
            $b = Handbook::query()->with(['categories', 'moods', 'links', 'audiobook'])->findOrFail($handbook);
            $this->handbookId = $b->id;
            $this->title = $b->title;
            $this->subtitle = (string) $b->subtitle;
            $this->slug = $b->slug;
            $this->catalogueId = (string) $b->catalogue_id;
            $this->description = (string) $b->description;
            $this->language = $b->language;
            $this->countryCode = (string) $b->country_code;
            $this->seriesSlot = (string) $b->series_slot;
            $this->seriesId = (string) ($b->series_id ?? '');
            $this->price = number_format((float) $b->list_price_usd, 2, '.', '');
            $this->lastVerified = $b->last_verified_on?->toDateString() ?? '';
            $this->isFeatured = (bool) $b->is_featured;
            $this->unlockViaEsim = (bool) $b->unlock_via_esim;
            $this->unlockViaPro = (bool) $b->unlock_via_pro;
            $this->allowPurchase = (bool) $b->allow_purchase;
            $this->extraCountries = implode(', ', (array) $b->extra_unlock_countries);
            $this->isbnEbook = (string) $b->isbn_ebook;
            $this->isbnPaperback = (string) $b->isbn_paperback;
            $this->isbnHardcover = (string) $b->isbn_hardcover;
            $this->categoryIds = $b->categories->pluck('id')->all();
            $this->moodIds = $b->moods->pluck('id')->all();
            foreach ($b->links as $l) {
                $this->links[$l->marketplace] = ['url' => (string) $l->url, 'enabled' => (bool) $l->is_enabled, 'availability' => $l->availability];
            }
            if ($b->audiobook) {
                $this->audioPrice = number_format((float) $b->audiobook->price_usd, 2, '.', '');
                $this->audioEnabled = (bool) $b->audiobook->is_enabled;
                $this->audioStream = (bool) $b->audiobook->stream_enabled;
                $this->audioDownload = (bool) $b->audiobook->download_enabled;
            }
        }
    }

    private function guard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    private function book(): ?Handbook
    {
        return $this->handbookId ? Handbook::query()->find($this->handbookId) : null;
    }

    public function save(HandbookCatalogueService $svc): void
    {
        $this->guard();
        $this->flash = null;
        $uniq = fn (string $col) => \Illuminate\Validation\Rule::unique('handbooks', $col)->ignore($this->handbookId);
        $this->validate([
            'title' => ['required', 'string', 'max:200'], 'subtitle' => ['nullable', 'string', 'max:240'], 'description' => ['nullable', 'string', 'max:6000'],
            'slug' => ['nullable', 'regex:/^[a-z0-9-]{1,120}$/', 'not_in:all,series,new', $uniq('slug')], 'extraCountries' => ['nullable', 'regex:/^\s*([A-Za-z]{2}\s*(,\s*|$))*$/'], 'catalogueId' => ['nullable', 'regex:/^[A-Za-z0-9_-]{2,20}$/', $uniq('catalogue_id')],
            'language' => ['required', 'regex:/^[a-z]{2}(-[A-Z]{2})?$/'], 'countryCode' => ['nullable', 'regex:/^[A-Za-z]{2}$/'], 'seriesSlot' => ['nullable', 'regex:/^S[1-9]$/'],
            'price' => ['required', 'numeric', 'min:0', 'max:999'], 'lastVerified' => ['nullable', 'date', 'before_or_equal:today'],
            'isbnEbook' => ['nullable', 'string', 'max:20'], 'isbnPaperback' => ['nullable', 'string', 'max:20'], 'isbnHardcover' => ['nullable', 'string', 'max:20'],
            'links.*.url' => ['nullable', 'url:https', 'max:400'], 'links.*.availability' => ['in:coming_soon,live'],
        ], ['links.*.url' => 'Marketplace links must be full https:// addresses.']);
        $seriesId = $this->seriesId !== '' ? (int) $this->seriesId : null;
        if (trim($this->newSeries) !== '') {
            $seriesId = HandbookSeries::query()->firstOrCreate(['name' => trim($this->newSeries), 'country_code' => $this->countryCode ? strtoupper($this->countryCode) : null])->id;
            $this->seriesId = (string) $seriesId;
            $this->newSeries = '';
        }
        $b = $this->book() ?? new Handbook(['status' => Handbook::DRAFT]);
        $b->fill([
            'title' => trim($this->title), 'subtitle' => $this->subtitle ?: null, 'description' => $this->description ?: null, 'language' => $this->language, 'country_code' => $this->countryCode ? strtoupper($this->countryCode) : null,
            'series_slot' => $this->seriesSlot ?: null, 'series_id' => $seriesId, 'list_price_usd' => round((float) $this->price, 2), 'last_verified_on' => $this->lastVerified ?: null,
            'isbn_ebook' => $this->isbnEbook ?: null, 'isbn_paperback' => $this->isbnPaperback ?: null, 'isbn_hardcover' => $this->isbnHardcover ?: null, 'catalogue_id' => $this->catalogueId ?: null,
            'is_featured' => $this->isFeatured, 'unlock_via_esim' => $this->unlockViaEsim, 'unlock_via_pro' => $this->unlockViaPro, 'allow_purchase' => $this->allowPurchase,
            'extra_unlock_countries' => array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', $this->extraCountries))))) ?: null,
        ]);
        $b->slug = $this->slug !== '' ? $this->slug : ($b->slug ?: $svc->slugFor($this->title, $b->id));
        $b->save();
        $this->handbookId = $b->id;
        $this->slug = $b->slug;

        if (trim($this->newCategory) !== '') {
            $c = HandbookCategory::query()->firstOrCreate(['slug' => Str::slug($this->newCategory)], ['name' => trim($this->newCategory)]);
            $this->categoryIds[] = $c->id;
            $this->newCategory = '';
        }
        $b->categories()->sync(array_values(array_unique(array_map('intval', $this->categoryIds))));
        $b->moods()->sync(array_values(array_unique(array_map('intval', $this->moodIds))));
        foreach ($this->links as $market => $l) {
            if (! array_key_exists($market, HandbookExternalLink::MARKETPLACES)) {
                continue;
            }
            if (trim((string) $l['url']) === '' && ! $l['enabled']) {
                HandbookExternalLink::query()->where('handbook_id', $b->id)->where('marketplace', $market)->delete();

                continue;
            }
            HandbookExternalLink::query()->updateOrCreate(['handbook_id' => $b->id, 'marketplace' => $market], ['url' => trim((string) $l['url']) ?: null, 'is_enabled' => (bool) $l['enabled'], 'availability' => $l['availability'] === 'live' ? 'live' : 'coming_soon']);
        }
        if ($this->audioPrice !== '' || $this->audioEnabled) {
            $this->validate(['audioPrice' => ['nullable', 'numeric', 'min:0', 'max:999']]);
            $b->audiobook()->updateOrCreate([], ['price_usd' => round((float) ($this->audioPrice ?: 0), 2), 'is_enabled' => $this->audioEnabled && (float) $this->audioPrice > 0, 'stream_enabled' => $this->audioStream, 'download_enabled' => $this->audioDownload]);
        }
        Auditor::log('handbook.saved', Handbook::class, $b->id, ['by' => Auth::id()]);
        $this->problems = $b->fresh()->publishProblems();
        $this->flash = 'Saved.';
    }

    public function uploadCover(HandbookCatalogueService $svc): void
    {
        $this->guard();
        $b = $this->book();
        if (! $b || ! $this->coverUpload) {
            $this->flash = 'Save the details first, then choose a cover.';

            return;
        }
        $this->validate(['coverUpload' => ['file', 'max:'.((int) config('handbooks.max_cover_mb', 8) * 1024)]]);
        $r = $svc->attachCover($b, $this->coverUpload);
        $this->coverUpload = null;
        $this->report = ['errors' => $r['errors'], 'warnings' => []];
        $this->flash = $r['ok'] ? 'Cover saved.' : null;
    }

    public function uploadBackCover(HandbookCatalogueService $svc): void
    {
        $this->guard();
        $b = $this->book();
        if (! $b || ! $this->backCoverUpload) {
            $this->flash = 'Save the details first, then choose a back cover.';

            return;
        }
        $this->validate(['backCoverUpload' => ['file', 'max:'.((int) config('handbooks.max_cover_mb', 8) * 1024)]]);
        $r = $svc->attachBackCover($b, $this->backCoverUpload);
        $this->backCoverUpload = null;
        $this->report = ['errors' => $r['errors'], 'warnings' => []];
        $this->flash = $r['ok'] ? 'Back cover saved. It is closed into the reader copy and every download.' : null;
    }

    public function uploadEpub(HandbookCatalogueService $svc): void
    {
        $this->guard();
        $b = $this->book();
        if (! $b || ! $this->epubUpload) {
            $this->flash = 'Save the details first, then choose an EPUB.';

            return;
        }
        $this->validate(['epubUpload' => ['file', 'max:'.((int) config('handbooks.max_epub_mb', 80) * 1024)]]);
        $r = $svc->attachEpub($b, $this->epubUpload, Auth::user());
        $this->epubUpload = null;
        $this->report = ['errors' => $r['errors'], 'warnings' => $r['warnings']];
        $this->flash = $r['ok'] ? ($r['pending'] ? 'New edition '.$r['edition'].' uploaded. Readers keep the current edition until you publish it below.' : 'EPUB checked and saved. The table of contents was read from it.') : null;
        $this->problems = $b->fresh()->publishProblems();
    }

    public function uploadPdf(HandbookCatalogueService $svc): void
    {
        $this->guard();
        $b = $this->book();
        if (! $b || ! $this->pdfUpload) {
            $this->flash = 'Save the details first, then choose a PDF.';

            return;
        }
        $this->validate(['pdfUpload' => ['file', 'max:'.((int) config('handbooks.max_pdf_mb', 120) * 1024)]]);
        $r = $svc->attachPdf($b, $this->pdfUpload, Auth::user());
        $this->pdfUpload = null;
        $this->report = ['errors' => $r['errors'], 'warnings' => []];
        $this->flash = $r['ok'] ? 'PDF saved.' : null;
    }

    public function publish(HandbookCatalogueService $svc): void
    {
        $this->guard();
        $b = $this->book();
        $r = $b ? $svc->publish($b, Auth::user()) : ['ok' => false, 'problems' => ['Save the details first.']];
        $this->problems = $r['problems'];
        $this->flash = $r['ok'] ? 'Published. It is now in the store.' : null;
    }

    public function archive(HandbookCatalogueService $svc): void
    {
        $this->guard();
        if ($b = $this->book()) {
            $svc->archive($b, Auth::user());
            $this->flash = 'Archived. Existing owners keep access; it is hidden from the store.';
        }
    }

    public function publishEdition(HandbookCatalogueService $svc, int $edition): void
    {
        $this->guard();
        $b = $this->book();
        $ok = $b && $svc->publishEdition($b, $edition, trim($this->editionNotes) ?: null, Auth::user());
        $this->editionNotes = '';
        $this->flash = $ok ? "Edition {$edition} is live. Everyone who holds this book was notified." : 'That edition could not be published.';
    }

    public function addTrack(): void
    {
        $this->guard();
        $b = $this->book();
        if (! $b || ! $this->trackUpload || trim($this->trackTitle) === '') {
            $this->flash = 'Choose an audio file and give the chapter a title.';

            return;
        }
        $this->validate(['trackUpload' => ['file', 'mimes:mp3,m4a,aac,ogg,wav', 'max:512000'], 'trackSeconds' => ['nullable', 'integer', 'min:0', 'max:86400']]);
        $a = $b->audiobook()->firstOrCreate([], ['price_usd' => 0]);
        $path = 'handbooks/'.$b->id.'/audio/'.Str::random(24).'.'.($this->trackUpload->getClientOriginalExtension() ?: 'mp3');
        \Illuminate\Support\Facades\Storage::disk(HandbookStorage::disk())->put($path, fopen($this->trackUpload->getRealPath(), 'rb'));
        HandbookAudioTrack::query()->create(['audiobook_id' => $a->id, 'position' => (int) $a->tracks()->max('position') + 1, 'chapter_title' => trim($this->trackTitle), 'file_path' => $path, 'seconds' => (int) $this->trackSeconds]);
        $a->update(['total_seconds' => (int) $a->tracks()->sum('seconds')]);
        $this->reset('trackUpload', 'trackTitle', 'trackSeconds');
        $this->flash = 'Chapter added.';
    }

    public function removeTrack(int $id): void
    {
        $this->guard();
        $b = $this->book();
        $t = $b?->audiobook ? HandbookAudioTrack::query()->where('audiobook_id', $b->audiobook->id)->find($id) : null;
        if ($t) {
            \Illuminate\Support\Facades\Storage::disk(HandbookStorage::disk())->delete($t->file_path);
            $t->delete();
            $b->audiobook->update(['total_seconds' => (int) $b->audiobook->tracks()->sum('seconds')]);
        }
    }

    public function render()
    {
        $b = $this->book();
        $b?->load(['editions', 'audiobook.tracks']);

        return view('livewire.admin.handbooks.editor', [
            'b' => $b, 'categories' => HandbookCategory::query()->orderBy('name')->get(), 'moods' => HandbookMood::query()->orderBy('name')->get(),
            'seriesList' => HandbookSeries::query()->orderBy('name')->get(), 'pending' => $b ? $b->editions->filter(fn ($e) => $e->published_at === null) : collect(),
            'checklist' => $b ? $b->publishProblems() : [],
        ]);
    }
}

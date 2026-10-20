<?php

namespace App\Livewire\Admin\Handbooks;

use App\Models\Handbook;
use App\Services\Handbooks\EpubInspector;
use App\Services\Handbooks\HandbookCatalogueService;
use App\Services\Handbooks\HandbookStorage;
use App\Services\Handbooks\Manuscript\EpubImageOptimizer;
use App\Services\Handbooks\Manuscript\EpubPackage;
use App\Services\Handbooks\Manuscript\ManuscriptStaging;
use App\Support\Auditor;
use App\Support\Handbooks\HandbookSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Manuscript studio (MASTER-ONLY): upload a manuscript as EPUB or Word, see it as readers will (phone, tablet, desktop), find pictures that are too big, fit them all in one
 * batch, and only then save it onto the handbook. Nothing touches the live handbook until "Save as the manuscript". The offline PDF has its own upload here too.
 */
#[Layout('components.layouts.admin')]
class HandbookManuscript extends Component
{
    use WithFileUploads;

    public int $handbookId;

    public $manuscript;

    public $pdfUpload;

    public ?string $token = null;

    public string $source = 'epub';

    public int $maxWidth = 1400;

    public int $quality = 82;

    public int $flagKb = 400;

    /** @var array<string, bool> image href => include in the batch fit */
    public array $pick = [];

    public int $chapter = 0;

    public string $device = 'phone';

    /** @var array{ok:bool, errors:list<string>, warnings:list<string>, title:?string, pages:int}|null */
    public ?array $check = null;

    /** @var array{saved:int, changed:int, total:int}|null */
    public ?array $fitted = null;

    public ?string $flash = null;

    /** @var list<string> */
    public array $problems = [];

    public function mount(int $handbook): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->handbookId = Handbook::query()->findOrFail($handbook)->id;
        $this->maxWidth = (int) HandbookSettings::get('image_max_width');
        $this->quality = (int) HandbookSettings::get('image_quality');
        $this->flagKb = (int) HandbookSettings::get('image_flag_kb');
    }

    private function book(): Handbook
    {
        return Handbook::query()->findOrFail($this->handbookId);
    }

    private function staging(): ManuscriptStaging
    {
        return app(ManuscriptStaging::class);
    }

    public function updatedManuscript(): void
    {
        $this->reset('flash', 'problems', 'fitted');
        $this->validate(['manuscript' => ['required', 'file', 'max:'.((int) config('handbooks.max_epub_mb', 80) * 1024), 'extensions:epub,docx']]);
        $b = $this->book();
        $old = $this->token;
        $r = $this->staging()->stage($this->manuscript, $b->title, $b->language ?: 'en');
        $this->manuscript = null;
        if (! $r['ok']) {
            $this->problems = $r['errors'];

            return;
        }
        if ($old) {
            $this->staging()->forget($old);
        }
        $this->token = $r['token'];
        $this->source = $r['source'];
        $this->chapter = 0;
        $this->flash = $r['source'] === 'docx' ? 'Your Word file was turned into an EPUB. Check the preview, then save it.' : 'Manuscript uploaded. Check the preview, then save it.';
        array_map(fn ($w) => $this->problems[] = $w, $r['warnings']);
        $this->analyse(true);
    }

    /** Work on the manuscript already on the handbook (to fit its pictures, for example). */
    public function useCurrent(): void
    {
        $b = $this->book();
        abort_unless($b->epub_path && Storage::disk(HandbookStorage::disk())->exists($b->epub_path), 404);
        $token = bin2hex(random_bytes(16));
        $tmp = tempnam(sys_get_temp_dir(), 'nmc').'.epub';
        file_put_contents($tmp, Storage::disk(HandbookStorage::disk())->get($b->epub_path));
        $this->staging()->replace($token, $tmp);
        @unlink($tmp);
        if ($this->token) {
            $this->staging()->forget($this->token);
        }
        $this->token = $token;
        $this->source = 'epub';
        $this->reset('flash', 'problems', 'fitted');
        $this->analyse(true);
    }

    /** @return array{pkg:EpubPackage, local:string}|null */
    private function open(): ?array
    {
        if (! $this->token || ! $this->staging()->exists($this->token)) {
            return null;
        }
        $local = $this->staging()->localCopy($this->token);
        try {
            return ['pkg' => new EpubPackage($local), 'local' => $local];
        } catch (\Throwable) {
            @unlink($local);

            return null;
        }
    }

    private function analyse(bool $resetPick = false): void
    {
        $o = $this->open();
        if (! $o) {
            $this->check = ['ok' => false, 'errors' => ['The manuscript could not be read.'], 'warnings' => [], 'title' => null, 'pages' => 0];

            return;
        }
        $r = app(EpubInspector::class)->inspect($o['local']);
        $this->check = ['ok' => $r['ok'], 'errors' => $r['errors'], 'warnings' => $r['warnings'], 'title' => $r['title'], 'pages' => $r['pages']];
        if ($resetPick || $this->pick === []) {
            $this->pick = [];
            foreach ($o['pkg']->images() as $img) {
                $this->pick[$img['href']] = $this->tooBig($img);
            }
        }
        @unlink($o['local']);
    }

    /** @param array{width:int,bytes:int} $img */
    private function tooBig(array $img): bool
    {
        return $img['width'] > $this->maxWidth || $img['bytes'] > $this->flagKb * 1024;
    }

    public function selectFlagged(): void
    {
        $o = $this->open();
        if (! $o) {
            return;
        }
        foreach ($o['pkg']->images() as $img) {
            $this->pick[$img['href']] = $this->tooBig($img);
        }
        @unlink($o['local']);
    }

    public function fitImages(): void
    {
        $this->reset('flash', 'problems', 'fitted');
        $this->validate(['maxWidth' => ['integer', 'min:480', 'max:4000'], 'quality' => ['integer', 'min:50', 'max:95'], 'flagKb' => ['integer', 'min:50', 'max:8000']]);
        $o = $this->open();
        if (! $o) {
            $this->problems = ['Upload a manuscript first.'];

            return;
        }
        $only = array_keys(array_filter($this->pick));
        if ($only === []) {
            @unlink($o['local']);
            $this->problems = ['Tick at least one picture to fit.'];

            return;
        }
        $out = tempnam(sys_get_temp_dir(), 'nmo').'.epub';
        try {
            $res = app(EpubImageOptimizer::class)->optimise($o['local'], $out, $this->maxWidth, $this->quality, $this->flagKb, $only);
            $this->staging()->replace($this->token, $out);
        } catch (\Throwable $e) {
            $this->problems = ['The pictures could not be fitted: '.$e->getMessage()];

            return;
        } finally {
            @unlink($o['local']);
            @unlink($out);
        }
        $changed = count(array_filter($res['items'], fn ($i) => $i['changed']));
        $this->fitted = ['saved' => $res['saved'], 'changed' => $changed, 'total' => count($res['items'])];
        $this->flash = $changed > 0 ? "Fitted {$changed} picture(s), saving ".number_format($res['saved'] / 1048576, 2).' MB. Nothing is live until you save.' : 'Every ticked picture already fits.';
        $this->analyse(true);
    }

    public function saveManuscript(HandbookCatalogueService $svc): void
    {
        $this->reset('flash', 'problems');
        $o = $this->open();
        if (! $o) {
            $this->problems = ['Upload a manuscript first.'];

            return;
        }
        @unlink($o['local']);
        $local = $this->staging()->localCopy($this->token);
        try {
            $r = $svc->attachEpub($this->book(), $local, Auth::user());
        } finally {
            @unlink($local);
        }
        if (! $r['ok']) {
            $this->problems = $r['errors'];

            return;
        }
        $this->staging()->forget($this->token);
        $this->token = null;
        $this->check = null;
        $this->pick = [];
        $this->flash = $r['pending'] ? 'Saved as edition '.$r['edition'].'. Publish the edition when you are happy with it.' : 'The manuscript is saved on this handbook.';
        array_map(fn ($w) => $this->problems[] = $w, $r['warnings']);
        Auditor::log('handbook.manuscript_saved', Handbook::class, $this->handbookId, ['by' => Auth::id(), 'source' => $this->source]);
    }

    public function discard(): void
    {
        if ($this->token) {
            $this->staging()->forget($this->token);
        }
        $this->reset('token', 'check', 'pick', 'fitted', 'flash', 'problems', 'chapter');
    }

    public function updatedPdfUpload(HandbookCatalogueService $svc): void
    {
        $this->reset('flash', 'problems');
        $this->validate(['pdfUpload' => ['required', 'file', 'mimes:pdf', 'max:'.((int) config('handbooks.max_pdf_mb', 120) * 1024)]]);
        $r = $svc->attachPdf($this->book(), $this->pdfUpload, Auth::user());
        $this->pdfUpload = null;
        if ($r['ok']) {
            $this->flash = 'The offline PDF is saved.';
        } else {
            $this->problems = $r['errors'];
        }
    }

    public function render()
    {
        $chapters = [];
        $images = [];
        $words = 0;
        if ($this->token && ($o = $this->open())) {
            $chapters = $o['pkg']->chapters();
            $images = $o['pkg']->images();
            $words = array_sum(array_column($chapters, 'words'));
            @unlink($o['local']);
        }
        $chapter = max(0, min($this->chapter, max(0, count($chapters) - 1)));

        return view('livewire.admin.handbooks.manuscript', [
            'book' => $this->book(), 'chapters' => $chapters, 'images' => $images, 'words' => $words, 'chapterIndex' => $chapter,
            'staged' => $this->token ? $this->staging()->size($this->token) : 0,
            'flagged' => count(array_filter($images, fn ($i) => $this->tooBig($i))),
        ]);
    }
}

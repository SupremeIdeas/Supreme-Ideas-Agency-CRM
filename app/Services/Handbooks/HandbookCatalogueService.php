<?php

namespace App\Services\Handbooks;

use App\Jobs\AlertAdminJob;
use App\Models\Handbook;
use App\Models\HandbookEdition;
use App\Models\HandbookEntitlement;
use App\Models\User;
use App\Support\Auditor;
use App\Support\Notifications\Notify;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Admin-side catalogue operations (MASTER-ONLY): attach and validate the EPUB, PDF and cover, publish behind the checklist, and manage editions. Used by the Handbook
 * Manager screens and by the `handbooks:import` bulk command so both follow the same rules.
 */
class HandbookCatalogueService
{
    public function __construct(private readonly HandbookStorage $storage, private readonly EpubInspector $inspector)
    {
    }

    /** A unique slug from a title. */
    public function slugFor(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'handbook';
        $slug = $base;
        for ($i = 2; Handbook::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * Validate and attach an EPUB. On a draft the file simply becomes the book's file. On a PUBLISHED book it becomes a new, unpublished edition so readers keep the
     * current one until the admin publishes the edition.
     *
     * @return array{ok:bool, errors:list<string>, warnings:list<string>, edition:?int, pending:bool}
     */
    public function attachEpub(Handbook $b, UploadedFile|string $file, ?User $by = null): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $r = $this->inspector->inspect((string) $path);
        if (! $r['ok']) {
            AlertAdminJob::dispatch(code: 'handbook_upload_rejected', message: 'An EPUB upload for "'.$b->title.'" was rejected: '.($r['errors'][0] ?? 'invalid'), context: ['handbook_id' => $b->id]);

            return ['ok' => false, 'errors' => $r['errors'], 'warnings' => $r['warnings'], 'edition' => null, 'pending' => false];
        }
        if ($b->status === Handbook::PUBLISHED && $b->epub_path) {
            $next = (int) max($b->edition, (int) HandbookEdition::query()->where('handbook_id', $b->id)->max('edition')) + 1;
            $stored = $this->storage->put($b, $file, 'epub', $next);
            HandbookEdition::query()->updateOrCreate(['handbook_id' => $b->id, 'edition' => $next], ['epub_path' => $stored, 'pdf_path' => null, 'published_at' => null, 'notes' => null]);
            Auditor::log('handbook.edition_uploaded', Handbook::class, $b->id, ['edition' => $next, 'by' => $by?->id]);

            return ['ok' => true, 'errors' => [], 'warnings' => $r['warnings'], 'edition' => $next, 'pending' => true];
        }
        $this->storage->delete($b->epub_path);
        $stored = $this->storage->put($b, $file, 'epub');
        $b->forceFill(['epub_path' => $stored, 'toc' => $r['toc'], 'page_count' => $r['pages']])->save();
        HandbookEdition::query()->updateOrCreate(['handbook_id' => $b->id, 'edition' => $b->edition], ['epub_path' => $stored, 'published_at' => $b->published_at]);
        Auditor::log('handbook.epub_attached', Handbook::class, $b->id, ['pages' => $r['pages'], 'by' => $by?->id]);

        return ['ok' => true, 'errors' => [], 'warnings' => $r['warnings'], 'edition' => $b->edition, 'pending' => false];
    }

    /** @return array{ok:bool, errors:list<string>} */
    public function attachPdf(Handbook $b, UploadedFile|string $file, ?User $by = null): array
    {
        $path = (string) ($file instanceof UploadedFile ? $file->getRealPath() : $file);
        $head = is_file($path) ? (string) file_get_contents($path, false, null, 0, 5) : '';
        if ($head !== '%PDF-') {
            return ['ok' => false, 'errors' => ['That is not a PDF file.']];
        }
        if (filesize($path) > (int) config('handbooks.max_pdf_mb', 120) * 1048576) {
            return ['ok' => false, 'errors' => ['The PDF is larger than '.(int) config('handbooks.max_pdf_mb', 120).' MB.']];
        }
        $pending = $b->status === Handbook::PUBLISHED && $b->pdf_path !== null;
        if ($pending) {
            $ed = HandbookEdition::query()->where('handbook_id', $b->id)->whereNull('published_at')->orderByDesc('edition')->first();
            if ($ed) {
                $this->storage->delete($ed->pdf_path);
                $ed->forceFill(['pdf_path' => $this->storage->put($b, $file, 'pdf', $ed->edition)])->save();
            } else {
                return ['ok' => false, 'errors' => ['Upload the new EPUB first; the PDF joins that new edition.']];
            }
        } else {
            $this->storage->delete($b->pdf_path);
            $b->forceFill(['pdf_path' => $this->storage->put($b, $file, 'pdf')])->save();
        }
        Auditor::log('handbook.pdf_attached', Handbook::class, $b->id, ['by' => $by?->id]);

        return ['ok' => true, 'errors' => []];
    }

    /** Cover: a portrait 2:3 or 3:4 image of at least the configured size, JPG, PNG or WebP. @return array{ok:bool, errors:list<string>} */
    public function attachCover(Handbook $b, UploadedFile|string $file): array
    {
        $path = (string) ($file instanceof UploadedFile ? $file->getRealPath() : $file);
        $bad = $this->coverProblem($path, 'cover');
        if ($bad !== null) {
            return ['ok' => false, 'errors' => [$bad]];
        }
        $this->storage->delete($b->cover_path);
        $b->forceFill(['cover_path' => $this->storage->put($b, $file, 'cover')])->save();

        return ['ok' => true, 'errors' => []];
    }

    /** The back cover (about the book and the Naara Family): same image rules as the front, and the same shape as the front cover when there is one. @return array{ok:bool, errors:list<string>} */
    public function attachBackCover(Handbook $b, UploadedFile|string $file): array
    {
        $path = (string) ($file instanceof UploadedFile ? $file->getRealPath() : $file);
        $bad = $this->coverProblem($path, 'back cover');
        if ($bad !== null) {
            return ['ok' => false, 'errors' => [$bad]];
        }
        $this->storage->delete($b->back_cover_path);
        $b->forceFill(['back_cover_path' => $this->storage->put($b, $file, 'back')])->save();
        HandbookBackCover::forget($b);

        return ['ok' => true, 'errors' => []];
    }

    private function coverProblem(string $path, string $what): ?string
    {
        $info = is_file($path) ? @getimagesize($path) : false;
        if ($info === false || ! in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return 'The '.$what.' must be a JPG, PNG or WebP image.';
        }
        [$w, $h] = $info;
        $minW = (int) config('handbooks.cover_min_width', 800);
        $minH = (int) config('handbooks.cover_min_height', 1100);
        if ($w < $minW || $h < $minH) {
            return "The {$what} is {$w}x{$h}px. It must be at least {$minW}x{$minH} px.";
        }
        $ok = collect((array) config('handbooks.cover_ratios', [2 / 3, 3 / 4]))->contains(fn ($r) => abs(($w / $h) - (float) $r) <= 0.04);
        if (! $ok) {
            return 'The '.$what.' must be a portrait 2:3 or 3:4 image (for example 1600x2400 or 896x1200).';
        }
        if (filesize($path) > (int) config('handbooks.max_cover_mb', 8) * 1048576) {
            return 'The '.$what.' is larger than '.(int) config('handbooks.max_cover_mb', 8).' MB.';
        }

        return null;
    }

    /** @return array{ok:bool, problems:list<string>} */
    public function publish(Handbook $b, ?User $by = null): array
    {
        $problems = $b->publishProblems();
        if ($problems !== []) {
            return ['ok' => false, 'problems' => $problems];
        }
        $b->forceFill(['status' => Handbook::PUBLISHED, 'published_at' => $b->published_at ?? now()])->save();
        Auditor::log('handbook.published', Handbook::class, $b->id, ['by' => $by?->id]);

        return ['ok' => true, 'problems' => []];
    }

    public function archive(Handbook $b, ?User $by = null): void
    {
        $b->forceFill(['status' => Handbook::ARCHIVED])->save();
        Auditor::log('handbook.archived', Handbook::class, $b->id, ['by' => $by?->id]);
    }

    /** Make a pending edition current and tell everyone who holds the book (free update). */
    public function publishEdition(Handbook $b, int $edition, ?string $notes = null, ?User $by = null): bool
    {
        $ed = HandbookEdition::query()->where('handbook_id', $b->id)->where('edition', $edition)->whereNull('published_at')->first();
        if (! $ed || ! $ed->epub_path) {
            return false;
        }
        $insp = null;
        $tmp = $this->storage->tempCopy($ed->epub_path);
        try {
            $insp = $this->inspector->inspect($tmp);
        } finally {
            @unlink($tmp);
        }
        $ed->forceFill(['published_at' => now(), 'notes' => $notes])->save();
        $b->forceFill(['edition' => $edition, 'epub_path' => $ed->epub_path, 'pdf_path' => $ed->pdf_path ?: $b->pdf_path, 'toc' => $insp['toc'] ?? $b->toc, 'page_count' => $insp['pages'] ?? $b->page_count, 'last_verified_on' => now()->toDateString()])->save();
        Auditor::log('handbook.edition_published', Handbook::class, $b->id, ['edition' => $edition, 'by' => $by?->id]);
        HandbookEntitlement::query()->where('handbook_id', $b->id)->whereNull('locked_at')->with('user')->chunkById(200, function ($rows) use ($b, $edition) {
            foreach ($rows as $e) {
                if ($e->user) {
                    Notify::event($e->user, 'handbook.new_edition', ['title' => $b->title, 'edition' => $edition], dedupe: 'hb-ed-'.$b->id.'-'.$edition);
                }
            }
        });

        return true;
    }
}

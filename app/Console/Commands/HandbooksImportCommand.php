<?php

namespace App\Console\Commands;

use App\Models\Handbook;
use App\Models\HandbookCategory;
use App\Models\HandbookMood;
use App\Models\HandbookSeries;
use App\Services\Handbooks\HandbookCatalogueService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * handbooks:import {folder} (MASTER-ONLY): load many titles at once. For each ID (matching the title catalogue, e.g. GBR-01) it reads {ID}.json (metadata), {ID}.epub,
 * {ID}.pdf (optional) and {ID}-cover.jpg (or .png/.webp) and {ID}-back.jpg (or .png/.webp, the back cover). Re-running updates the same title (matched on the ID) and never creates a second copy. Titles land as DRAFTS;
 * `--missing-only` makes it idempotent for the scheduler (see routes/console.php), so a deploy that ships a new seed pack lands its titles without a manual command. `--publish` publishes the ones that pass the publish checklist. An invalid EPUB or cover skips that file with the reason and the rest carry on.
 */
class HandbooksImportCommand extends Command
{
    protected $signature = 'handbooks:import {folder : Folder with one metadata json, epub, optional pdf and cover per title id} {--publish : Publish titles that pass the checklist} {--refresh-files : When a title already exists and the pack ships a DIFFERENT EPUB (or PDF), attach it: a draft takes it directly, a published title gets a new unpublished edition to review} {--dry : Show what would happen} {--missing-only : Only create titles that do not exist yet, or finish a draft that still has no EPUB; never touch a title that already has its manuscript (safe to run on a schedule)}';

    protected $description = 'Bulk-import handbook titles from a folder of files and metadata';

    public function handle(HandbookCatalogueService $svc): int
    {
        $dir = rtrim((string) $this->argument('folder'), '/');
        if (! is_dir($dir)) {
            $this->error("Folder not found: {$dir}");

            return self::FAILURE;
        }
        $ids = collect(glob($dir.'/*.json') ?: [])->map(fn ($f) => basename($f, '.json'))->filter(fn ($id) => preg_match('/^[A-Za-z0-9_-]{2,20}$/', $id) === 1)->sort()->values();
        if ($ids->isEmpty()) {
            $this->warn('No {ID}.json files found.');

            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry');
        $ok = $skipped = 0;
        foreach ($ids as $id) {
            $meta = json_decode((string) file_get_contents("{$dir}/{$id}.json"), true);
            if (! is_array($meta) || blank($meta['title'] ?? null)) {
                $this->warn("{$id}: metadata is missing or has no title; skipped.");
                $skipped++;

                continue;
            }
            if ($dry) {
                $this->line("[dry] {$id}: {$meta['title']}");

                continue;
            }
            $b = Handbook::query()->where('catalogue_id', $id)->first() ?? new Handbook(['catalogue_id' => $id, 'status' => Handbook::DRAFT]);
            // Already in the catalogue: with --missing-only the owner's edits (title, price, description, published state) are theirs and are never overwritten;
            // only files the title still lacks are attached, so a draft picks up its manuscript when the pack ships it.
            $existing = $b->exists && $this->option('missing-only');
            $b->fill([
                'title' => (string) $meta['title'], 'subtitle' => $meta['subtitle'] ?? null, 'description' => $meta['description'] ?? null, 'language' => $meta['language'] ?? 'en',
                'country_code' => isset($meta['country_code']) ? strtoupper(substr((string) $meta['country_code'], 0, 2)) : null, 'series_slot' => $meta['series_slot'] ?? null,
                'list_price_usd' => (float) ($meta['list_price_usd'] ?? 0), 'last_verified_on' => $meta['last_verified_on'] ?? null,
                'isbn_ebook' => $meta['isbn_ebook'] ?? null, 'isbn_paperback' => $meta['isbn_paperback'] ?? null, 'isbn_hardcover' => $meta['isbn_hardcover'] ?? null, 'sort_order' => (int) ($meta['sort_order'] ?? 0),
            ]);
            if ($existing) {
                $b->refresh();
            }
            if (! $b->exists) {
                $b->slug = $svc->slugFor((string) $meta['title']);
            }
            if ($existing) {
                // metadata untouched
            } elseif (! empty($meta['series'])) {
                $b->series_id = HandbookSeries::query()->firstOrCreate(['name' => (string) $meta['series'], 'country_code' => $b->country_code])->id;
            }
            if (! $existing) {
                $b->save();
                $b->categories()->sync(collect((array) ($meta['categories'] ?? []))->map(fn ($n) => HandbookCategory::query()->firstOrCreate(['slug' => Str::slug((string) $n)], ['name' => (string) $n])->id)->all());
                $b->moods()->sync(collect((array) ($meta['moods'] ?? []))->map(fn ($n) => HandbookMood::query()->firstOrCreate(['slug' => Str::slug((string) $n)], ['name' => (string) $n])->id)->all());
            }

            $gained = false;
            if (is_file("{$dir}/{$id}.epub") && (! $existing || blank($b->epub_path))) {
                $gained = true;
                $r = $svc->attachEpub($b, "{$dir}/{$id}.epub");
                $r['ok'] ? $this->line("{$id}: EPUB ok".($r['warnings'] ? ' ('.count($r['warnings']).' warning(s))' : '')) : $this->warn("{$id}: EPUB rejected: ".$r['errors'][0]);
            }
            $refreshed = false;
            if ($this->option('refresh-files') && $existing && ! $gained && is_file("{$dir}/{$id}.epub") && $b->fresh()->epub_path && ! $this->alreadyHas($b->fresh(), "{$dir}/{$id}.epub")) {
                $r = $svc->attachEpub($b->fresh(), "{$dir}/{$id}.epub");
                if ($r['ok']) {
                    $refreshed = true;
                    $this->line("{$id}: new EPUB attached".($r['pending'] ? " as unpublished edition {$r['edition']} (publish it in Admin > Handbooks)" : ''));
                    if (is_file("{$dir}/{$id}.pdf")) {
                        $pr = $svc->attachPdf($b->fresh(), "{$dir}/{$id}.pdf");
                        $pr['ok'] ?: $this->warn("{$id}: PDF rejected: ".$pr['errors'][0]);
                    }
                } else {
                    $this->warn("{$id}: refreshed EPUB rejected: ".$r['errors'][0]);
                }
            }
            if (! $refreshed && is_file("{$dir}/{$id}.pdf") && (! $existing || blank($b->fresh()->pdf_path))) {
                $r = $svc->attachPdf($b->fresh(), "{$dir}/{$id}.pdf");
                $r['ok'] ?: $this->warn("{$id}: PDF rejected: ".$r['errors'][0]);
            }
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                if (is_file("{$dir}/{$id}-cover.{$ext}") && (! $existing || blank($b->fresh()->cover_path))) {
                    $r = $svc->attachCover($b->fresh(), "{$dir}/{$id}-cover.{$ext}");
                    $r['ok'] ?: $this->warn("{$id}: cover rejected: ".$r['errors'][0]);
                    break;
                }
            }
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                if (is_file("{$dir}/{$id}-back.{$ext}") && (! $existing || blank($b->fresh()->back_cover_path))) {
                    $r = $svc->attachBackCover($b->fresh(), "{$dir}/{$id}-back.{$ext}");
                    $r['ok'] ?: $this->warn("{$id}: back cover rejected: ".$r['errors'][0]);
                    break;
                }
            }
            if ($this->option('publish') && (! $existing || $gained)) {   // a title the owner already had is never re-published by the sync
                $p = $svc->publish($b->fresh());
                $p['ok'] ? $this->info("{$id}: published") : $this->line("{$id}: left as draft (".implode(' ', $p['problems']).')');
            }
            $ok++;
        }
        $this->info("Imported {$ok} title(s), skipped {$skipped}.");

        return self::SUCCESS;
    }

    /** True when the pack's file is byte-identical to the title's current file or to its newest unpublished edition (so a re-run never stacks duplicate editions). */
    private function alreadyHas(Handbook $b, string $seedPath): bool
    {
        $storage = app(\App\Services\Handbooks\HandbookStorage::class);
        $want = sha1_file($seedPath);
        $paths = array_filter([$b->epub_path]);
        foreach (\App\Models\HandbookEdition::query()->where('handbook_id', $b->id)->whereNull('published_at')->pluck('epub_path') as $p) {
            $paths[] = $p;
        }
        foreach ($paths as $path) {
            try {
                if ($storage->exists($path) && sha1($storage->get($path)) === $want) {
                    return true;
                }
            } catch (\Throwable $e) {
                // an unreadable stored copy is treated as different
            }
        }

        return false;
    }
}

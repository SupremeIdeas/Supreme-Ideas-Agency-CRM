<?php

namespace Tests\Feature\Handbooks;

use App\Models\Handbook;
use App\Models\HandbookEntitlement;
use App\Models\User;
use App\Services\Handbooks\EpubInspector;
use App\Services\Handbooks\HandbookBackCover;
use App\Services\Handbooks\HandbookCatalogueService;
use App\Services\Handbooks\HandbookStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\Support\MakesEpubs;
use Tests\TestCase;
use ZipArchive;

/** Handbook back covers (MASTER-ONLY): validation, closing the back cover into EPUB and PDF, the reader and buyer copies, the store page, and the committed seed pack. */
class HandbookBackCoverTest extends TestCase
{
    use MakesEpubs;
    use RefreshDatabase;

    private function book(bool $back = true, string $slug = 'uk-1'): Handbook
    {
        Storage::fake(HandbookStorage::disk());
        $b = Handbook::create(['slug' => $slug, 'title' => 'UK Handbook', 'country_code' => 'GB', 'series_slot' => 'S1', 'list_price_usd' => 9.99, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'cover_path' => 'c.jpg', 'last_verified_on' => now()->toDateString()]);
        $epub = 'handbooks/'.$b->id.'/e1/book.epub';
        Storage::disk(HandbookStorage::disk())->put($epub, file_get_contents($this->makeEpub()));
        $b->forceFill(['epub_path' => $epub])->save();
        if ($back) {
            $r = app(HandbookCatalogueService::class)->attachBackCover($b->fresh(), $this->makeCover(896, 1200));
            $this->assertTrue($r['ok'], implode(' ', $r['errors']));
        }

        return $b->fresh();
    }

    private function spine(string $epubBytes): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sp').'.epub';
        file_put_contents($tmp, $epubBytes);
        $z = new ZipArchive;
        $z->open($tmp);
        $opf = (string) $z->getFromName('OEBPS/content.opf');
        $z->close();
        @unlink($tmp);
        preg_match_all('/<itemref idref="([^"]+)"/', $opf, $m);

        return $m[1];
    }

    public function test_covers_may_be_two_by_three_or_three_by_four_but_not_tiny_or_wide(): void
    {
        Storage::fake(HandbookStorage::disk());
        $b = Handbook::create(['slug' => 'c-1', 'title' => 'C', 'list_price_usd' => 5, 'status' => Handbook::DRAFT]);
        $svc = app(HandbookCatalogueService::class);
        $this->assertTrue($svc->attachCover($b, $this->makeCover(896, 1200))['ok']);    // 3:4 house artwork
        $this->assertTrue($svc->attachCover($b->fresh(), $this->makeCover(1600, 2400))['ok']);
        $this->assertFalse($svc->attachCover($b->fresh(), $this->makeCover(300, 400))['ok']);   // too small
        $this->assertFalse($svc->attachCover($b->fresh(), $this->makeCover(1200, 1200))['ok']);  // square
        $bad = $svc->attachBackCover($b->fresh(), $this->makeCover(1200, 1200));
        $this->assertFalse($bad['ok']);
        $this->assertStringContainsString('back cover', $bad['errors'][0]);
    }

    public function test_the_epub_is_closed_with_the_back_cover_and_stays_valid(): void
    {
        $b = $this->book();
        $svc = app(HandbookBackCover::class);
        $path = $svc->epubPath($b);
        $this->assertNotSame($b->epub_path, $path);
        $bytes = Storage::disk(HandbookStorage::disk())->get($path);
        $this->assertSame('naara-back-cover', collect($this->spine($bytes))->last());
        $tmp = tempnam(sys_get_temp_dir(), 'ck').'.epub';
        file_put_contents($tmp, $bytes);
        $this->assertTrue(app(EpubInspector::class)->inspect($tmp)['ok']);
        @unlink($tmp);
        $this->assertSame($path, $svc->epubPath($b), 'built once, then reused');
        $this->assertSame(1, substr_count(implode(',', $this->spine($bytes)), 'naara-back-cover'));
    }

    public function test_a_book_without_a_back_cover_is_served_unchanged(): void
    {
        $b = $this->book(false);
        $this->assertSame($b->epub_path, app(HandbookBackCover::class)->epubPath($b));
    }

    public function test_the_pdf_gets_one_extra_page_for_the_back_cover(): void
    {
        $b = $this->book();
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(40, 10, 'Page one');
        $pdf->AddPage();
        $pdf->Cell(40, 10, 'Page two');
        $src = tempnam(sys_get_temp_dir(), 'pd').'.pdf';
        file_put_contents($src, $pdf->Output('S'));
        $b->forceFill(['pdf_path' => 'handbooks/'.$b->id.'/e1/book.pdf'])->save();
        Storage::disk(HandbookStorage::disk())->put($b->pdf_path, file_get_contents($src));
        $path = app(HandbookBackCover::class)->pdfPath($b->fresh());
        $this->assertNotSame($b->pdf_path, $path);
        $out = tempnam(sys_get_temp_dir(), 'po').'.pdf';
        file_put_contents($out, Storage::disk(HandbookStorage::disk())->get($path));
        $this->assertSame(3, (new Fpdi)->setSourceFile($out));
    }

    public function test_the_reader_copy_and_the_buyer_download_both_end_on_the_back_cover(): void
    {
        $b = $this->book();
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        HandbookEntitlement::query()->create(['user_id' => $u->id, 'handbook_id' => $b->id, 'level' => 'owned', 'source' => 'purchase', 'granted_at' => now()]);
        $read = $this->actingAs($u)->get(route('handbooks.file.read', $b->slug))->assertOk();
        $this->assertSame('naara-back-cover', collect($this->spine($read->streamedContent()))->last());

        $dl = $this->actingAs($u)->get('/library/'.$b->slug.'/download/epub')->assertOk();
        $spine = $this->spine($dl->getContent());
        $this->assertContains('naara-back-cover', $spine);
        $this->assertTrue(array_search('naara-back-cover', $spine, true) < count($spine) - 1, 'the buyer licence page comes after the back cover');
    }

    public function test_the_store_page_offers_the_back_cover_only_when_there_is_one(): void
    {
        $b = $this->book();
        $this->get('/handbooks/'.$b->slug)->assertOk()->assertSee('Back cover', false)->assertSee('data-back-cover', false);
        $this->get(route('handbooks.backcover', $b->slug))->assertOk();
        $this->book(false, 'uk-2');
        $this->get('/handbooks/uk-2')->assertOk()->assertDontSee('data-back-cover', false);
        $this->get(route('handbooks.backcover', 'uk-2'))->assertNotFound();
    }

    public function test_the_seed_pack_publishes_only_the_finished_books_and_keeps_the_rest_as_drafts(): void
    {
        Storage::fake(HandbookStorage::disk());
        Artisan::call('handbooks:import', ['folder' => database_path('seeders/handbooks'), '--publish' => true]);
        $all = Handbook::query()->whereIn('catalogue_id', ['GBR-01', 'GBR-02', 'GBR-03', 'GBR-04', 'GBR-05', 'USA-01', 'USA-02'])->get()->keyBy('catalogue_id');
        $this->assertCount(7, $all);
        foreach (['GBR-01', 'GBR-02', 'GBR-03', 'GBR-04', 'GBR-05'] as $id) {
            $b = $all[$id];
            $this->assertSame(Handbook::PUBLISHED, $b->status, $id.' has a manuscript, a PDF and a cover, so it goes live');
            foreach (['cover_path', 'back_cover_path', 'epub_path', 'pdf_path'] as $col) {
                $this->assertNotNull($b->{$col}, $id.' '.$col);
            }
            $this->assertNotEmpty($b->toc);
        }
        $this->assertEqualsWithDelta(9.99, (float) $all['GBR-01']->list_price_usd, 0.001);
        foreach (['USA-01', 'USA-02'] as $id) {
            $b = $all[$id];
            $this->assertSame(Handbook::DRAFT, $b->status, $id.' stays a draft until its manuscript and PDF are added');
            $this->assertNotNull($b->cover_path);
            $this->assertNotNull($b->back_cover_path);
            $this->assertNull($b->epub_path);
            $this->assertNull($b->pdf_path);
            $this->assertContains('Add the manuscript as an EPUB file.', $b->publishProblems());
        }
        $this->assertSame(['GBR-01', 'GBR-02', 'GBR-03', 'GBR-04', 'GBR-05', 'USA-01', 'USA-02'], $all->sortBy('sort_order')->keys()->all(), 'shelf order follows the catalogue');
        // The storefront lists the finished books and none of the drafts.
        $page = $this->get('/handbooks/all')->assertOk();
        foreach (['Land and Live in the UK', 'UK Visa Routes Decoded', 'Work and Earn in Britain', 'Study in the UK', 'Renting in the UK'] as $t) {
            $page->assertSee($t);
        }
        foreach (['Land and Live in the USA', 'US Visas and Green Card'] as $t) {
            $page->assertDontSee($t);
        }
        $this->get('/handbooks/'.$all['USA-01']->slug)->assertNotFound();
    }

    public function test_every_finished_book_can_be_read_and_downloaded_as_epub_and_pdf_with_the_back_cover(): void
    {
        Storage::fake(HandbookStorage::disk());
        Artisan::call('handbooks:import', ['folder' => database_path('seeders/handbooks'), '--publish' => true]);
        config(['handbooks.download_per_hour' => 50]);
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        foreach (['GBR-01', 'GBR-02', 'GBR-03', 'GBR-04', 'GBR-05'] as $id) {
            $b = Handbook::query()->where('catalogue_id', $id)->firstOrFail();
            HandbookEntitlement::query()->create(['user_id' => $u->id, 'handbook_id' => $b->id, 'level' => 'owned', 'source' => 'purchase', 'price_paid_usd' => 9.99, 'granted_at' => now()]);
            $epub = $this->actingAs($u)->get('/library/'.$b->slug.'/download/epub')->assertOk();
            $this->assertContains('naara-back-cover', $this->spineOf($epub->getContent()), $id.' EPUB ends with the back cover');
            $pdf = $this->actingAs($u)->get('/library/'.$b->slug.'/download/pdf')->assertOk();
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
            $tmp = tempnam(sys_get_temp_dir(), 'dl').'.pdf';
            file_put_contents($tmp, $pdf->getContent());
            $pages = (new Fpdi)->setSourceFile($tmp);
            $orig = (new Fpdi)->setSourceFile(Storage::disk(HandbookStorage::disk())->path($b->pdf_path));
            $this->assertSame($orig + 1, $pages, $id.' PDF has the back cover page added');
            @unlink($tmp);
        }
        $this->assertSame(0, \App\Models\HandbookDownload::query()->where('stamped', false)->count(), 'every download carried the buyer licence stamp');
    }

    public function test_missing_only_sync_seeds_new_titles_and_never_touches_an_existing_one(): void
    {
        Storage::fake(HandbookStorage::disk());
        $dir = database_path('seeders/handbooks');
        Artisan::call('handbooks:import', ['folder' => $dir, '--missing-only' => true, '--publish' => true]);
        $this->assertSame(5, Handbook::query()->where('status', Handbook::PUBLISHED)->count());
        $b = Handbook::query()->where('catalogue_id', 'GBR-01')->firstOrFail();
        $b->forceFill(['list_price_usd' => 12.34, 'status' => Handbook::DRAFT])->save();   // the owner edited the price and unpublished it

        Artisan::call('handbooks:import', ['folder' => $dir, '--missing-only' => true, '--publish' => true]);

        $b->refresh();
        $this->assertEquals(12.34, (float) $b->list_price_usd, 'owner price survives the scheduled sync');
        $this->assertSame(Handbook::DRAFT, $b->status, 'an owner-unpublished title is not re-published');
        $this->assertSame(count(glob($dir.'/*.json')), Handbook::query()->count(), 'no duplicates');
    }

    private function spineOf(string $bytes): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sp').'.epub';
        file_put_contents($tmp, $bytes);
        $z = new ZipArchive;
        $z->open($tmp);
        $opfPath = 'EPUB/content.opf';
        $opf = (string) $z->getFromName($opfPath);
        $z->close();
        @unlink($tmp);
        preg_match_all('/<itemref idref="([^"]+)"/', $opf, $m);

        return $m[1];
    }

    public function test_a_title_is_never_a_blank_cover_on_any_surface(): void
    {
        Storage::fake(HandbookStorage::disk());
        $b = Handbook::create(['slug' => 'no-cover', 'catalogue_id' => 'GBR-09', 'title' => 'Plain Title Book', 'country_code' => 'GB', 'series_slot' => 'S1', 'list_price_usd' => 5, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'last_verified_on' => now()->toDateString()]);
        $withImage = Handbook::create(['slug' => 'has-cover', 'title' => 'Image Title Book', 'country_code' => 'GB', 'list_price_usd' => 5, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'cover_path' => 'handbooks/x/cover/missing.webp', 'last_verified_on' => now()->toDateString()]);
        // The typeset cover (title and catalogue id) is always rendered under the image, so a missing or broken image still shows the book.
        foreach ([$b, $withImage] as $book) {
            $html = (string) view('components.handbook-cover', ['book' => $book])->render();
            $this->assertStringContainsString('hbk__type', $html);
            $this->assertStringContainsString($book->title, $html);
        }
        $this->assertStringContainsString('onerror="this.remove()"', (string) view('components.handbook-cover', ['book' => $withImage])->render());
        $this->assertStringContainsString('GBR-09', (string) view('components.handbook-cover', ['book' => $b])->render());
        $this->get('/handbooks')->assertOk()->assertSee('Plain Title Book');
        $this->get('/handbooks/all')->assertOk()->assertSee('Plain Title Book');
    }

    public function test_the_landing_hero_deck_and_slider_always_lead_with_the_newest_titles(): void
    {
        Storage::fake(HandbookStorage::disk());
        foreach (range(1, 7) as $n) {
            Handbook::create(['slug' => 'book-'.$n, 'title' => 'Book Number '.$n, 'country_code' => 'GB', 'series_slot' => 'S1', 'list_price_usd' => 5, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'is_featured' => $n === 1, 'published_at' => now()->subDays(20 - $n), 'last_verified_on' => now()->toDateString()]);
        }
        $html = $this->get('/handbooks')->assertOk()->getContent();
        $this->assertStringContainsString('data-hero-deck', $html);
        preg_match_all('/data-hero-book data-slug="([^"]+)"/', $html, $m);
        $this->assertSame(['book-7', 'book-6', 'book-5', 'book-4', 'book-3'], $m[1], 'newest first, five titles, a featured older book does not take the front');
        $this->assertStringContainsString('data-new-slider', $html);
        preg_match('/data-new-slider.*?(?=<\/section>)/s', $html, $slider);
        $this->assertSame(7, substr_count($slider[0], 'data-handbook-card'));
        $this->assertLessThan(strpos($slider[0], 'Book Number 6'), strpos($slider[0], 'Book Number 7'));
        Handbook::create(['slug' => 'book-8', 'title' => 'Brand New Title', 'list_price_usd' => 5, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'published_at' => now(), 'last_verified_on' => now()->toDateString()]);
        preg_match_all('/data-hero-book data-slug="([^"]+)"/', $this->get('/handbooks')->getContent(), $m2);
        $this->assertSame('book-8', $m2[1][0], 'a freshly published book is at the front at once');
    }
}

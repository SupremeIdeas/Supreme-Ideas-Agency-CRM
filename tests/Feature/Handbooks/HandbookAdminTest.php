<?php

namespace Tests\Feature\Handbooks;

use App\Livewire\Admin\Handbooks\HandbookEditor;
use App\Livewire\Admin\Handbooks\HandbookManager;
use App\Models\Handbook;
use App\Models\HandbookEntitlement;
use App\Models\User;
use App\Services\Handbooks\HandbookStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\MakesEpubs;
use Tests\TestCase;

/** Handbook admin (MASTER-ONLY): staff-only, EPUB upload validation, publish checklist, new-edition flow keeps readers on the live edition until published. */
class HandbookAdminTest extends TestCase
{
    use MakesEpubs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_only_staff_can_open_the_manager_or_editor(): void
    {
        $member = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->actingAs($member)->get('/adminmaster/handbooks')->assertNotFound();
        $this->actingAs($this->admin())->get('/adminmaster/handbooks')->assertOk();
        Livewire::actingAs($this->admin())->test(HandbookManager::class)->assertOk();
    }

    public function test_admin_can_download_the_epub_and_pdf_on_record_and_members_cannot(): void
    {
        Storage::fake(HandbookStorage::disk());
        $d = Storage::disk(HandbookStorage::disk());
        $d->put('hb/x.epub', 'EPUBDATA');
        $d->put('hb/x.pdf', '%PDF-1.4 data');
        $b = Handbook::create(['slug' => 'dl-book', 'title' => 'D', 'country_code' => 'GH', 'series_slot' => 'S1', 'list_price_usd' => 5, 'status' => Handbook::DRAFT, 'epub_path' => 'hb/x.epub', 'pdf_path' => 'hb/x.pdf']);
        $r = $this->actingAs($this->admin())->get('/adminmaster/handbooks/'.$b->id.'/download/epub')->assertOk();
        $this->assertStringContainsString('dl-book.epub', $r->headers->get('content-disposition'));
        $this->assertStringContainsString('attachment', $r->headers->get('content-disposition'));
        $this->actingAs($this->admin())->get('/adminmaster/handbooks/'.$b->id.'/download/pdf')->assertOk();
        $this->actingAs($this->admin())->get('/adminmaster/handbooks/'.$b->id.'/download/cover')->assertNotFound();
        $this->actingAs($this->admin())->get('/adminmaster/handbooks/'.$b->id.'/edit')->assertSee('data-hb-download="pdf"', false)->assertSee('data-hb-download="epub"', false);
        $member = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->actingAs($member)->get('/adminmaster/handbooks/'.$b->id.'/download/epub')->assertNotFound();
    }

    public function test_admin_cover_preview_and_key_routes_answer(): void
    {
        Storage::fake(HandbookStorage::disk());
        $a = $this->admin();
        $b = Handbook::create(['slug' => 'p-book', 'title' => 'P', 'country_code' => 'GH', 'series_slot' => 'S1', 'list_price_usd' => 5, 'status' => Handbook::DRAFT]);
        Storage::disk(HandbookStorage::disk())->put('c.jpg', file_get_contents($this->makeCover(400)));
        Storage::disk(HandbookStorage::disk())->put('b.epub', file_get_contents($this->makeEpub()));
        $b->forceFill(['cover_path' => 'c.jpg', 'epub_path' => 'b.epub'])->save();

        $this->actingAs($a)->get(route('admin.handbooks.cover', $b->id))->assertOk();
        $this->actingAs($a)->get(route('admin.handbooks.preview', $b->id))->assertOk();
        $this->actingAs($a)->get(route('admin.handbooks.preview.epub', $b->id))->assertOk();
        $this->actingAs($a)->get(route('admin.handbooks.preview.key', $b->id))->assertOk();
    }

    public function test_a_book_cannot_publish_until_the_checklist_passes_then_can(): void
    {
        Storage::fake(HandbookStorage::disk());
        $a = $this->admin();
        $c = Livewire::actingAs($a)->test(HandbookEditor::class)
            ->set('title', 'Ghana Handbook')->set('slug', 'ghana-handbook')->set('countryCode', 'GH')->set('seriesSlot', 'S1')->set('price', '9.99')->set('description', 'Moving to Ghana.')->set('lastVerified', now()->toDateString())
            ->call('save');
        $b = Handbook::query()->where('slug', 'ghana-handbook')->firstOrFail();
        $c->call('publish');
        $this->assertSame(Handbook::DRAFT, $b->fresh()->status);   // no EPUB or cover yet

        $epub = UploadedFile::fake()->createWithContent('g.epub', file_get_contents($this->makeEpub()));
        $cover = UploadedFile::fake()->createWithContent('c.jpg', file_get_contents($this->makeCover()));
        $cat = \App\Models\HandbookCategory::query()->create(['name' => 'Moving', 'slug' => 'moving']);
        $pdf = UploadedFile::fake()->createWithContent('g.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
        $step = Livewire::actingAs($a)->test(HandbookEditor::class, ['handbook' => $b->id])->set('categoryIds', [$cat->id])->call('save')->set('epubUpload', $epub)->call('uploadEpub')->set('coverUpload', $cover)->call('uploadCover')->call('publish');
        $this->assertSame(Handbook::DRAFT, $b->fresh()->status, 'no PDF yet: buyers could not download, so it stays a draft');
        $step->set('pdfUpload', $pdf)->call('uploadPdf')->call('publish');

        $this->assertSame(Handbook::PUBLISHED, $b->fresh()->status);
        $this->assertNotEmpty($b->fresh()->toc);
    }

    public function test_a_corrupt_epub_is_rejected_and_nothing_is_stored(): void
    {
        Storage::fake(HandbookStorage::disk());
        $a = $this->admin();
        $b = Handbook::create(['slug' => 'x-book', 'title' => 'X', 'country_code' => 'GH', 'series_slot' => 'S1', 'list_price_usd' => 5, 'status' => Handbook::DRAFT]);
        $bad = UploadedFile::fake()->createWithContent('bad.epub', 'not a zip at all');

        Livewire::actingAs($a)->test(HandbookEditor::class, ['handbook' => $b->id])->set('epubUpload', $bad)->call('uploadEpub');

        $this->assertNull($b->fresh()->epub_path);
    }

    public function test_a_new_edition_of_a_live_book_waits_until_published(): void
    {
        Storage::fake(HandbookStorage::disk());
        $a = $this->admin();
        $b = Handbook::create(['slug' => 'live-book', 'title' => 'Live', 'country_code' => 'GH', 'series_slot' => 'S1', 'list_price_usd' => 5, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'cover_path' => 'c.jpg', 'epub_path' => 'old.epub', 'last_verified_on' => now()->toDateString()]);
        $holder = User::factory()->create();
        HandbookEntitlement::create(['user_id' => $holder->id, 'handbook_id' => $b->id, 'level' => 'owned', 'source' => 'purchase', 'granted_at' => now()]);

        Livewire::actingAs($a)->test(HandbookEditor::class, ['handbook' => $b->id])->set('epubUpload', UploadedFile::fake()->createWithContent('g.epub', file_get_contents($this->makeEpub())))->call('uploadEpub');

        $this->assertSame('old.epub', $b->fresh()->epub_path);   // readers still get the live edition
        $this->assertSame(1, (int) $b->fresh()->edition);
    }
}

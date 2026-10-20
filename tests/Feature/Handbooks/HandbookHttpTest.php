<?php

namespace Tests\Feature\Handbooks;

use App\Livewire\Admin\Handbooks\HandbookManager;
use App\Models\Handbook;
use App\Models\HandbookDownload;
use App\Models\HandbookEntitlement;
use App\Models\User;
use App\Services\Handbooks\HandbookStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\MakesEpubs;
use Tests\TestCase;

/** Handbook Store HTTP surface (MASTER-ONLY): storefront pages, library gate, and the IDOR guarantee that only the holder can read/download. */
class HandbookHttpTest extends TestCase
{
    use MakesEpubs;
    use RefreshDatabase;

    private function book(): Handbook
    {
        Storage::fake(HandbookStorage::disk());
        $b = Handbook::create(['slug' => 'ghana-1', 'title' => 'Ghana Handbook', 'country_code' => 'GH', 'series_slot' => 'S1', 'list_price_usd' => 9.99, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'cover_path' => 'c.jpg', 'last_verified_on' => now()->toDateString()]);
        $path = 'handbooks/'.$b->id.'/e1/book.epub';
        Storage::disk(HandbookStorage::disk())->put($path, file_get_contents($this->makeEpub()));
        $b->forceFill(['epub_path' => $path])->save();

        return $b;
    }

    private function own(User $u, Handbook $b): void
    {
        HandbookEntitlement::query()->create(['user_id' => $u->id, 'handbook_id' => $b->id, 'level' => 'owned', 'source' => 'purchase', 'granted_at' => now()]);
    }

    public function test_the_store_and_book_page_are_public(): void
    {
        $b = $this->book();
        $this->get('/handbooks')->assertOk();
        $this->get('/handbooks/'.$b->slug)->assertOk()->assertSee('Ghana Handbook');
        $this->get('/handbooks/nope-nope')->assertNotFound();
    }

    public function test_the_library_and_files_need_sign_in(): void
    {
        $b = $this->book();
        $this->get('/library')->assertRedirect();
        $this->get('/library/'.$b->slug.'/epub')->assertRedirect();
    }

    public function test_the_holder_can_read_but_another_member_gets_a_404(): void
    {
        $b = $this->book();
        $a = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $other = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->own($a, $b);

        $this->actingAs($a)->get('/library/'.$b->slug.'/epub')->assertOk(); 
        $this->actingAs($a)->get('/library/'.$b->slug.'/key')->assertOk()->assertJsonStructure(['key', 'edition']);
        $this->actingAs($a)->get('/library/'.$b->slug.'/read')->assertOk();

        foreach (['epub', 'key', 'read', 'progress', 'bookmarks', 'download/epub'] as $p) {
            $this->actingAs($other)->get('/library/'.$b->slug.'/'.$p)->assertNotFound();
        }
    }

    public function test_download_is_stamped_logged_and_rate_limited(): void
    {
        $b = $this->book();
        $a = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->own($a, $b);

        $this->actingAs($a)->get('/library/'.$b->slug.'/download/epub')->assertOk()->assertHeader('Content-Type', 'application/epub+zip');
        $this->assertSame(1, HandbookDownload::query()->where('user_id', $a->id)->count());

        config(['handbooks.download_per_hour' => 1]);
        $this->actingAs($a)->get('/library/'.$b->slug.'/download/epub')->assertStatus(429);
    }

    public function test_progress_and_bookmarks_round_trip_for_the_holder(): void
    {
        $b = $this->book();
        $a = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->own($a, $b);

        $this->actingAs($a)->postJson('/library/'.$b->slug.'/progress', ['locator' => 'epubcfi(/6/2)', 'percent' => 12.5])->assertOk();
        $this->actingAs($a)->getJson('/library/'.$b->slug.'/progress')->assertJson(['locator' => 'epubcfi(/6/2)']);
        $this->actingAs($a)->postJson('/library/'.$b->slug.'/bookmarks', ['locator' => 'epubcfi(/6/4)', 'label' => 'Visas'])->assertOk();
        $this->actingAs($a)->getJson('/library/'.$b->slug.'/bookmarks')->assertJsonCount(1);
    }

    public function test_an_unpublished_book_is_invisible_to_the_public_and_to_holders(): void
    {
        $b = $this->book();
        $a = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->own($a, $b);
        $b->forceFill(['status' => Handbook::DRAFT])->save();

        $this->get('/handbooks/'.$b->slug)->assertNotFound();
        $this->actingAs($a)->get('/library/'.$b->slug.'/epub')->assertNotFound();
    }

    public function test_the_admin_manager_is_staff_only(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now(), 'is_active' => true]))->get('/adminmaster/handbooks')->assertNotFound();
    }
}

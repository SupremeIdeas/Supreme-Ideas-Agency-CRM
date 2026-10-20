<?php

namespace Tests\Feature\Handbooks;

use App\Models\Handbook;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The reader carries the read-aloud player, and the book page the professional title block (MASTER-ONLY). */
class HandbookReadAloudPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reader_has_the_listen_button_and_player(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $b = Handbook::create(['title' => 'T', 'slug' => 't', 'catalogue_id' => 'TST-09', 'status' => Handbook::DRAFT, 'list_price_usd' => 5, 'epub_path' => 'handbooks/x/e1/a.epub', 'language' => 'en']);
        $this->actingAs($admin)->get(route('admin.handbooks.preview', $b->id))->assertOk()->assertSee('data-act="listen"', false)->assertSee('data-tts-voice', false)->assertSee('data-tts-play', false);
    }

    public function test_the_book_page_shows_series_glance_description_and_features(): void
    {
        $b = Handbook::create(['title' => 'Land and Live in Kenya', 'slug' => 'land-kenya', 'catalogue_id' => 'KEN-01', 'status' => Handbook::PUBLISHED, 'published_at' => now(), 'list_price_usd' => 9.99, 'language' => 'en', 'page_count' => 120, 'edition' => 1,
            'subtitle' => 'Before you land', 'description' => "A calm opening paragraph.\n\nMore detail follows here.\n\nGeneral information, not legal advice.", 'toc' => [['title' => 'One', 'href' => 'a.xhtml', 'depth' => 0], ['title' => 'Two', 'href' => 'b.xhtml', 'depth' => 0]]]);
        $this->get(route('handbooks.show', $b->slug))->assertOk()->assertSeeHtml('data-book-glance')->assertSeeHtml('data-book-description')->assertSeeHtml('data-book-features')
            ->assertSeeHtml('data-book-notice')->assertSee('Listen instead of reading')->assertSee('~120');
    }
}

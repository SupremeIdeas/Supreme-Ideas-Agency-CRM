<?php

namespace Tests\Feature;

use App\Livewire\Admin\GifLibrary as GifLibraryPage;
use App\Models\GifLibraryItem;
use App\Models\User;
use App\Services\Gifs\GifLibrary;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The composer's GIF and sticker library: the shipped set, the shelves endpoint, admin import (files and web addresses) and live search. */
class GifLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function gifBytes(int $w = 64, int $h = 48): string
    {
        return "GIF89a".pack('v', $w).pack('v', $h)."\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00".pack('v', $w).pack('v', $h)."\x00\x02\x02D\x01\x00;";
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $u->assignRole('super_admin');

        return $u;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GifLibraryItem::mediaDisk());
    }

    public function test_the_shipped_gifs_and_stickers_are_registered_on_every_shelf(): void
    {
        app(GifLibrary::class)->syncSeeds();
        $cats = collect(app(GifLibrary::class)->categories())->keyBy('slug');
        foreach (['stickers', 'funny', 'love', 'reactions', 'celebrate', 'thanks', 'greetings', 'business', 'motivation'] as $c) {
            $this->assertGreaterThanOrEqual(6, $cats[$c]['count'], "$c shelf is stocked");
        }
        $this->assertGreaterThanOrEqual(30, $cats['stickers']['count']);
        $this->assertSame(0, app(GifLibrary::class)->syncSeeds(), 'registering twice adds nothing');
        $this->assertFileExists(public_path('images/gifs/stickers/laughing.gif'));
    }

    public function test_the_shelves_endpoint_needs_a_login_and_filters_by_shelf_and_search(): void
    {
        app(GifLibrary::class)->syncSeeds();
        $this->getJson('/gifs/library')->assertUnauthorized();

        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $all = $this->actingAs($u)->getJson('/gifs/library')->assertOk()->json();
        $this->assertGreaterThan(70, count($all['items']));
        $this->assertSame('Stickers', $all['categories'][0]['label']);
        $this->assertStringContainsString('/images/gifs/', $all['items'][0]['url']);

        $love = $this->actingAs($u)->getJson('/gifs/library?category=love')->json('items');
        $this->assertNotEmpty($love);
        $this->assertSame(['love'], array_values(array_unique(array_column($love, 'category'))));
        $this->assertContains('Cat love', array_column($this->actingAs($u)->getJson('/gifs/library?q=cat+love')->json('items'), 'name'));
    }

    public function test_a_real_gif_can_be_added_and_a_fake_or_oversized_one_is_refused(): void
    {
        $lib = app(GifLibrary::class);
        $item = $lib->addFromUpload(UploadedFile::fake()->createWithContent('thanks.gif', $this->gifBytes()), 'thanks', 'Big thanks');
        $this->assertSame('Big thanks', $item->name);
        $this->assertSame([64, 48], [$item->width, $item->height]);
        Storage::disk($item->disk)->assertExists($item->path);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a GIF');
        $lib->addFromUpload(UploadedFile::fake()->createWithContent('x.gif', "\x89PNG\r\n\x1a\n".str_repeat('a', 200)), 'funny');
    }

    public function test_a_gif_that_is_too_big_in_pixels_or_bytes_is_refused(): void
    {
        $lib = app(GifLibrary::class);
        try {
            $lib->addFromUpload(UploadedFile::fake()->createWithContent('huge.gif', $this->gifBytes(2000, 2000)), 'funny');
            $this->fail('oversized dimensions should be refused');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('pixels', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $lib->addFromUpload(UploadedFile::fake()->create('big.gif', 4096, 'image/gif'), 'funny');
    }

    public function test_a_web_address_is_fetched_once_checked_and_private_addresses_are_refused(): void
    {
        config(['security.ssrf_allowed_hosts' => ['cdn.example.test']]);
        Http::fake(['cdn.example.test/*' => Http::response($this->gifBytes(), 200, ['Content-Type' => 'image/gif'])]);
        $item = app(GifLibrary::class)->addFromUrl('https://cdn.example.test/dance.gif', 'funny');
        $this->assertSame('url', $item->source);
        $this->assertSame('dance', $item->name);
        $this->assertSame('https://cdn.example.test/dance.gif', $item->source_url);

        foreach (['http://127.0.0.1/a.gif', 'http://169.254.169.254/latest/meta-data', 'file:///etc/passwd', 'ftp://x/y.gif'] as $bad) {
            try {
                app(GifLibrary::class)->addFromUrl($bad, 'funny');
                $this->fail("$bad should be refused");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        Http::assertSentCount(1);
    }

    public function test_a_redirect_into_a_private_address_is_refused_before_it_is_followed(): void
    {
        config(['security.ssrf_allowed_hosts' => ['cdn.example.test']]);
        Http::fake(['cdn.example.test/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret.gif'])]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed');
        app(GifLibrary::class)->addFromUrl('https://cdn.example.test/r.gif', 'funny');
    }

    public function test_the_admin_page_adds_hides_moves_and_removes_and_is_closed_to_everyone_else(): void
    {
        $admin = $this->admin();
        $c = Livewire::actingAs($admin)->test(GifLibraryPage::class)->set('category', 'celebrate')
            ->set('files', [UploadedFile::fake()->createWithContent('party.gif', $this->gifBytes())])->call('addFiles')->assertSee('added to Celebrate');
        $item = GifLibraryItem::where('source', 'upload')->firstOrFail();
        $this->assertSame('celebrate', $item->category);

        $c->call('toggle', $item->id);
        $this->assertNotContains($item->id, array_column(app(GifLibrary::class)->listing('celebrate'), 'id'), 'a hidden GIF leaves the chat');
        $c->call('toggle', $item->id)->call('move', $item->id, 'thanks');
        $this->assertSame('thanks', $item->fresh()->category);

        $c->call('remove', $item->id);
        $this->assertNull(GifLibraryItem::find($item->id));
        Storage::disk($item->disk)->assertMissing($item->path);

        $plain = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        Livewire::actingAs($plain)->test(GifLibraryPage::class)->assertForbidden();
    }

    public function test_live_search_uses_the_admin_key_hides_it_from_the_browser_and_is_empty_without_one(): void
    {
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->actingAs($u)->getJson('/gifs/search?q=cat')->assertOk()->assertJson(['items' => []]);

        config(['services.giphy.api_key' => 'giphy-secret-key']);
        Http::fake(['api.giphy.com/*' => Http::response(['data' => [['title' => 'Happy cat', 'images' => ['fixed_height_small' => ['url' => 'https://media.giphy.com/a.gif']]], ['title' => 'Bad', 'images' => ['fixed_height' => ['url' => 'http://insecure/x.gif']]]]], 200)]);
        $r = $this->actingAs($u)->getJson('/gifs/search?q=cat')->assertOk();
        $r->assertJsonPath('attribution', 'GIPHY')->assertJsonCount(1, 'items')->assertJsonPath('items.0.name', 'Happy cat');
        $this->assertStringNotContainsString('giphy-secret-key', $r->getContent());
        Http::assertSent(fn ($req) => $req['rating'] === 'g');

        config(['services.giphy.api_key' => '', 'services.tenor.api_key' => 'tenor-key']);
        Http::fake(['tenor.googleapis.com/*' => Http::response(['results' => [['content_description' => 'Dance', 'media_formats' => ['tinygif' => ['url' => 'https://media.tenor.com/d.gif']]]]], 200)]);
        $this->actingAs($u)->getJson('/gifs/search?q=dance')->assertJsonPath('attribution', 'Tenor')->assertJsonPath('items.0.url', 'https://media.tenor.com/d.gif');
        Http::assertSent(fn ($req) => str_contains($req->url(), 'tenor') && $req['contentfilter'] === 'high');
    }

    public function test_the_composer_is_given_the_library_endpoint_only_with_the_gif_feature(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));
        $with = \Illuminate\Support\Facades\Blade::render('<x-composer :features="[\'gif\']" />');
        $without = \Illuminate\Support\Facades\Blade::render('<x-composer :features="[\'emoji\']" />');
        $this->assertStringContainsString('"gifLibraryEndpoint":"/gifs/library"', html_entity_decode($with));
        $this->assertStringContainsString('"gifLibraryEndpoint":""', html_entity_decode($without));
    }
}

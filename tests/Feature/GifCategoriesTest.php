<?php

namespace Tests\Feature;

use App\Livewire\Admin\GifLibrary as GifLibraryPage;
use App\Models\GifCategory;
use App\Models\GifLibraryItem;
use App\Models\User;
use App\Services\Gifs\GifLibrary;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin-made sticker categories: create, limit to certain chats, reorder, delete, and a GIF made in the studio landing on the chosen shelf. */
class GifCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function gifBytes(): string
    {
        return 'GIF89a'.pack('v', 64).pack('v', 48)."\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00".pack('v', 64).pack('v', 48)."\x00\x02\x02D\x01\x00;";
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

    public function test_the_nine_shipped_shelves_exist_after_install_and_a_translated_label_follows_the_member(): void
    {
        $this->assertSame(9, GifCategory::count());
        $this->assertTrue(GifCategory::where('slug', 'stickers')->firstOrFail()->is_builtin);
        $this->assertSame('Stickers', GifCategory::where('slug', 'stickers')->first()->label());
    }

    public function test_an_admin_creates_a_category_with_a_clean_unique_slug_and_cannot_duplicate_a_name(): void
    {
        $lib = app(GifLibrary::class);
        $a = $lib->createCategory('Football fever', null);
        $b = $lib->createCategory('Football  Fever!', null);   // different words once cleaned: still gets its own slug

        $this->assertSame('football-fever', $a->slug);
        $this->assertNotSame($a->slug, $b->slug);
        $this->assertLessThanOrEqual(24, strlen($b->slug));
        $this->assertFalse($a->is_builtin);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');
        $lib->createCategory('football fever', null);
    }

    public function test_a_new_category_shows_in_chat_only_once_it_has_a_sticker_and_only_on_the_surfaces_chosen(): void
    {
        $lib = app(GifLibrary::class);
        $c = $lib->createCategory('Business wins', ['inbox', 'naara_chat']);
        $this->assertNotContains($c->slug, array_column($lib->categories(), 'slug'), 'empty shelves are not offered');

        $lib->addFromUpload(UploadedFile::fake()->createWithContent('win.gif', $this->gifBytes()), $c->slug, 'Big win');

        $this->assertContains($c->slug, array_column($lib->categories(true, 'inbox'), 'slug'));
        $this->assertContains($c->slug, array_column($lib->categories(true, 'naara_chat'), 'slug'));
        $this->assertNotContains($c->slug, array_column($lib->categories(true, 'support'), 'slug'));
        $this->assertSame([], $lib->listing($c->slug, null, 600, 'support'), 'a chat the shelf is not allowed in gets none of its GIFs, even by asking for the slug');
        $this->assertCount(1, $lib->listing($c->slug, null, 600, 'inbox'));
        $this->assertContains('Big win', array_column($lib->listing(null, 'big win', 600), 'name'));   // no surface = every chat
    }

    public function test_the_endpoint_filters_by_surface_and_ignores_an_unknown_one(): void
    {
        $lib = app(GifLibrary::class);
        $c = $lib->createCategory('Staff only', ['staff']);
        $lib->addFromUpload(UploadedFile::fake()->createWithContent('s.gif', $this->gifBytes()), $c->slug, 'Staff');
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $on = $this->actingAs($u)->getJson('/gifs/library?surface=staff')->json();
        $off = $this->actingAs($u)->getJson('/gifs/library?surface=inbox')->json();
        $weird = $this->actingAs($u)->getJson('/gifs/library?surface=%3Cscript%3E')->json();

        $this->assertContains($c->slug, array_column($on['categories'], 'slug'));
        $this->assertNotContains($c->slug, array_column($off['categories'], 'slug'));
        $this->assertContains($c->slug, array_column($weird['categories'], 'slug'), 'an unknown surface behaves like no surface');
    }

    public function test_a_hidden_category_leaves_the_chat_and_all_surfaces_ticked_means_every_chat(): void
    {
        $lib = app(GifLibrary::class);
        $c = $lib->createCategory('Seasonal', array_keys(config('gif_library.surfaces')));
        $this->assertNull($c->surfaces);
        $lib->addFromUpload(UploadedFile::fake()->createWithContent('x.gif', $this->gifBytes()), $c->slug);
        $lib->updateCategory($c, 'Seasonal', null, false);

        $this->assertNotContains($c->slug, array_column($lib->categories(), 'slug'));
        $this->assertSame([], $lib->listing($c->slug));
    }

    public function test_shelves_can_be_reordered_and_the_chat_follows_that_order(): void
    {
        $lib = app(GifLibrary::class);
        $first = $lib->all()->first();
        $lib->moveCategory($first, 1);

        $this->assertNotSame($first->slug, $lib->slugs()[0]);
        $this->assertSame($first->slug, $lib->slugs()[1]);
    }

    public function test_deleting_a_category_moves_its_gifs_or_removes_them_and_the_last_one_cannot_go(): void
    {
        $lib = app(GifLibrary::class);
        $c = $lib->createCategory('Temp', null);
        $item = $lib->addFromUpload(UploadedFile::fake()->createWithContent('t.gif', $this->gifBytes()), $c->slug);

        $this->assertSame(1, $lib->deleteCategory($c, 'funny'));
        $this->assertSame('funny', $item->fresh()->category);
        $this->assertNull(GifCategory::find($c->id));

        $d = $lib->createCategory('Temp2', null);
        $gone = $lib->addFromUpload(UploadedFile::fake()->createWithContent('t2.gif', $this->gifBytes()), $d->slug);
        $lib->deleteCategory($d, null);
        $this->assertNull(GifLibraryItem::find($gone->id));
        Storage::disk($gone->disk)->assertMissing($gone->path);

        GifCategory::where('slug', '!=', 'funny')->delete();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one');
        $lib->deleteCategory(GifCategory::firstOrFail(), null);
    }

    public function test_a_deleted_shipped_shelf_is_not_brought_back_by_registering_the_shipped_gifs_again(): void
    {
        $lib = app(GifLibrary::class);
        $lib->deleteCategory(GifCategory::where('slug', 'love')->firstOrFail(), null);
        $lib->syncSeeds();

        $this->assertNull(GifCategory::where('slug', 'love')->first());
        $this->assertSame(0, GifLibraryItem::where('category', 'love')->count());
    }

    public function test_the_admin_page_manages_categories_and_saves_a_studio_made_gif_to_the_chosen_one(): void
    {
        $page = Livewire::actingAs($this->admin())->test(GifLibraryPage::class)
            ->set('newName', 'Match day')->set('newSurfaces', ['inbox'])->call('createCategory')->assertSee('created');
        $c = GifCategory::where('name', 'Match day')->firstOrFail();
        $this->assertSame(['inbox'], $c->surfaces);
        $this->assertSame($c->slug, $page->get('category'), 'the new category is selected so the next upload goes to it');

        $page->set('made', UploadedFile::fake()->createWithContent('made.gif', $this->gifBytes()))->call('saveMade', 'Goal!')->assertSee('is live in chat now');
        $item = GifLibraryItem::where('category', $c->slug)->firstOrFail();
        $this->assertSame('Goal!', $item->name);
        $this->assertSame('upload', $item->source);
        $this->assertContains($item->id, array_column(app(GifLibrary::class)->listing(null, null, 600, 'inbox'), 'id'));

        $page->call('editCategory', $c->id)->set('editName', 'Match day live')->set('editSurfaces', ['inbox', 'support'])->call('saveCategory');
        $this->assertSame('Match day live', $c->fresh()->name);
        $this->assertEqualsCanonicalizing(['inbox', 'support'], $c->fresh()->surfaces);

        $page->call('askDelete', $c->id)->set('moveTo', 'funny')->call('deleteCategory')->assertSee('deleted');
        $this->assertSame('funny', $item->fresh()->category);
    }

    public function test_a_studio_gif_over_the_size_limit_is_refused_with_advice(): void
    {
        $big = UploadedFile::fake()->createWithContent('big.gif', $this->gifBytes().str_repeat("\0", 3 * 1048576 + 10));
        Livewire::actingAs($this->admin())->test(GifLibraryPage::class)->set('made', $big)->call('saveMade', 'Big')->assertSee('over 3 MB');
        $this->assertSame(0, GifLibraryItem::where('source', 'upload')->count());
    }

    public function test_every_composer_surface_in_the_app_names_where_it_sits(): void
    {
        $map = [
            'resources/views/livewire/n2n/chat.blade.php' => 'naara_chat',
            'resources/views/livewire/support-chat.blade.php' => 'support',
            'resources/views/livewire/messages.blade.php' => 'inbox',
            'resources/views/livewire/send-message.blade.php' => 'messages',
            'resources/views/livewire/admin/support-queue.blade.php' => 'staff',
        ];
        foreach ($map as $file => $surface) {
            $this->assertStringContainsString('surface="'.$surface.'"', (string) file_get_contents(base_path($file)), $file);
            $this->assertArrayHasKey($surface, config('gif_library.surfaces'));
        }
    }
}

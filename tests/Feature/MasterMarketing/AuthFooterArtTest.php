<?php

namespace Tests\Feature\MasterMarketing;

use App\Livewire\Admin\SiteChromePage;
use App\Models\User;
use App\Support\SiteChrome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** The master-only illustration that closes the user and admin sign-in pages (committed transparent WebP, no hotlink). */
class AuthFooterArtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_user_and_admin_sign_in_end_with_the_illustration(): void
    {
        $admin = '/'.trim((string) env('ADMIN_PATH', 'adminmaster'), '/').'/login';

        foreach (['/login', $admin] as $url) {
            $res = $this->get($url);
            $res->assertOk();
            $res->assertSee('data-auth-footer-art', false);
            $res->assertSee('images/master-marketing/auth-footer.webp', false);
        }
    }

    public function test_other_auth_pages_do_not_carry_it_and_the_asset_is_a_transparent_webp(): void
    {
        $this->get('/register')->assertDontSee('data-auth-footer-art', false);

        $path = public_path('images/master-marketing/auth-footer.webp');
        $this->assertFileExists($path);
        $head = file_get_contents($path, false, null, 0, 32);
        $this->assertSame('RIFF', substr($head, 0, 4));
        $this->assertSame('WEBP', substr($head, 8, 4));
        $this->assertLessThan(120 * 1024, filesize($path));
    }

    public function test_defaults_show_the_art_a_light_glass_card_and_a_calm_aurora(): void
    {
        $this->assertSame(['enabled' => true, 'overlap' => 'light', 'size' => 'medium', 'glass' => 'light', 'aurora' => 'calm'],
            collect(SiteChrome::art())->only(['enabled', 'overlap', 'size', 'glass', 'aurora'])->all());

        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('data-art-glass="light"', $html);
        $this->assertStringContainsString('data-aurora="calm"', $html);
        $this->assertStringContainsString('data-glow-fields', $html);
    }

    public function test_the_admin_can_change_every_part_and_the_sign_in_page_follows(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SiteChromePage::class)
            ->set('art_overlap', 'none')->set('art_size', 'large')->set('art_glass', 'strong')->set('art_aurora', 'soft')
            ->set('art_image', UploadedFile::fake()->image('art.png', 800, 240))
            ->call('save')->assertHasNoErrors();

        auth()->logout();
        $art = SiteChrome::art();
        $this->assertSame(['none', 'large', 'strong', 'soft'], [$art['overlap'], $art['size'], $art['glass'], $art['aurora']]);
        $this->assertStringNotContainsString('images/master-marketing/auth-footer.webp', $art['src'], 'the replacement artwork is used');

        $this->get('/login')->assertSee('data-art-size="large"', false)->assertSee('data-art-glass="strong"', false)->assertSee('data-aurora="soft"', false);

        // Back to the standard artwork.
        Livewire::actingAs($admin)->test(SiteChromePage::class)->set('art_reset', true)->call('save')->assertHasNoErrors();
        auth()->logout();
        $this->assertStringContainsString('images/master-marketing/auth-footer.webp', SiteChrome::art()['src']);
    }

    public function test_switching_the_illustration_off_also_drops_the_glass_card_and_bad_values_fall_back(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(SiteChromePage::class)->set('art_enabled', false)->call('save')->assertHasNoErrors();
        auth()->logout();

        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-auth-footer-art', $html);
        $this->assertStringNotContainsString('data-art-glass="', $html);

        \App\Models\Setting::setValue('site.auth.art_enabled', true, 'site');
        \App\Models\Setting::setValue('site.auth.art_overlap', 'huge', 'site');
        \App\Models\Setting::setValue('site.auth.art_aurora', 'x', 'site');
        SiteChrome::flush();
        $this->assertSame(['light', 'calm'], [SiteChrome::art()['overlap'], SiteChrome::art()['aurora']]);
    }

    public function test_an_admin_cannot_save_an_invalid_choice_or_a_non_image(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(SiteChromePage::class)
            ->set('art_glass', 'neon')->call('save')->assertHasErrors(['art_glass']);
        Livewire::actingAs($admin)->test(SiteChromePage::class)
            ->set('art_image', UploadedFile::fake()->create('x.exe', 10))->call('save')->assertHasErrors(['art_image']);
    }
}

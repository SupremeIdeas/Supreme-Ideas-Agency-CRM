<?php

namespace Tests\Feature;

use App\Livewire\Admin\BusinessWorkspacePage;
use App\Models\Setting;
use App\Models\User;
use App\Support\Business\WorkspaceHero;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessWorkspacePageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_defaults_apply_until_an_admin_changes_them_and_the_migration_seeded_them(): void
    {
        $this->assertSame('Business workspace', WorkspaceHero::title());
        $this->assertSame(['calls', 'messages', 'team', 'wallet'], WorkspaceHero::words());
        $this->assertNotNull(Setting::query()->where('key', 'business.workspace_page.word_4')->first(), 'seeded so it shows in the admin settings');
        $this->assertStringContainsString('workspace-hero-800.webp', WorkspaceHero::image()['src']);
    }

    public function test_an_admin_edits_wording_with_up_to_four_words_and_markup_is_stripped(): void
    {
        Livewire::actingAs($this->admin())->test(BusinessWorkspacePage::class)
            ->set('title', 'Team <b>HQ</b>')->set('words', ['sales', 'support', '', ''])->set('description', 'Run <script>x</script>everything here.')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('Team HQ', WorkspaceHero::title());
        $this->assertSame(['sales', 'support'], WorkspaceHero::words());
        $this->assertStringNotContainsString('<', WorkspaceHero::description());
        Livewire::actingAs(User::query()->first())->test(BusinessWorkspacePage::class)->call('resetAll');
        $this->assertSame(['calls', 'messages', 'team', 'wallet'], WorkspaceHero::words(), 'reset restores the four defaults');
    }

    public function test_at_least_one_word_is_required_and_a_fifth_word_is_impossible(): void
    {
        Livewire::actingAs($this->admin())->test(BusinessWorkspacePage::class)->set('words', ['', '', '', ''])->call('save')->assertHasErrors('words.0');
        $this->assertSame(4, WorkspaceHero::MAX_WORDS);
    }

    public function test_empty_stored_values_fall_back_so_the_page_never_renders_blank(): void
    {
        Setting::setValue('business.workspace_page.title', '', 'business');
        Setting::setValue('business.workspace_page.description', '  ', 'business');
        $this->assertSame('Business workspace', WorkspaceHero::title());
        $this->assertNotSame('', WorkspaceHero::description());
    }

    public function test_the_picture_can_be_replaced_and_reset(): void
    {
        Storage::fake('public');
        Queue::fake();
        config(['filesystems.default' => 'public']);
        $c = Livewire::actingAs($this->admin())->test(BusinessWorkspacePage::class);
        $c->set('image', UploadedFile::fake()->image('hero.png', 1400, 400))->call('uploadImage')->assertHasNoErrors();
        $this->assertNotNull(WorkspaceHero::customImage());
        $this->assertNull(WorkspaceHero::image()['srcset']);
        $c->call('resetImage');
        $this->assertNull(WorkspaceHero::customImage());
    }

    public function test_a_non_admin_cannot_open_the_page(): void
    {
        $this->seed(RoleSeeder::class);
        Livewire::actingAs(User::factory()->create(['is_active' => true]))->test(BusinessWorkspacePage::class)->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Admin\SiteEditor;
use App\Models\User;
use App\Support\SiteContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Marketing/Chat blueprint Phase C — rebuilt home bands are editable + corner radius. */
class SiteEditorPhaseCTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifesto_and_diagnosis_are_registered_editable_home_sections(): void
    {
        $sections = SiteContent::page('home', includeHidden: true);

        $this->assertArrayHasKey('manifesto', $sections);
        $this->assertArrayHasKey('diagnosis', $sections);
        $this->assertSame('30', $sections['manifesto']['radius_top']);
    }

    public function test_the_radius_and_copy_save_and_render_on_the_homepage(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->set('sections.manifesto.radius_top', '32')
            ->set('sections.manifesto.tag', 'Why we exist')
            ->call('save');

        $page = SiteContent::page('home');
        $this->assertSame('32', $page['manifesto']['radius_top']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Why we exist', $html);
        $this->assertStringContainsString('border-top-left-radius:32px', $html);
    }

    public function test_a_hidden_manifesto_does_not_take_the_video_and_story_bands_with_it(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->set('sections.manifesto.visible', false)->call('save');

        $this->assertArrayNotHasKey('manifesto', SiteContent::page('home'));
        $this->get('/')->assertOk()->assertDontSee('Our Manifesto');
    }
}

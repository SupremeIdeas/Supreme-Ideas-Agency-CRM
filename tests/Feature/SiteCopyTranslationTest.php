<?php

namespace Tests\Feature;

use App\Jobs\TranslateSiteCopyJob;
use App\Livewire\Admin\SiteEditor;
use App\Models\User;
use App\Support\SiteContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Copy an admin edits in the Site Editor must follow the visitor's language: the
 * edit is translated per language by a queued job, a translation is only used
 * while it still matches the English it came from, and a visitor never sees a
 * stale or half-broken translation.
 */
class SiteCopyTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function edit(string $headline): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(SiteEditor::class)->set('sections.hero.headline', $headline)->call('save');
    }

    private function fakeClaude(string $prefix): void
    {
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake(fn ($request) => Http::response(['content' => [['type' => 'text', 'text' => json_encode(
            collect(json_decode($request['messages'][0]['content'], true))->map(fn ($v) => is_string($v) ? $prefix.$v : $v)->all(), JSON_UNESCAPED_UNICODE
        )]]]));
    }

    public function test_an_edited_headline_shows_in_english_until_it_is_translated_then_in_each_language(): void
    {
        Bus::fake();
        config(['services.anthropic.api_key' => 'test-key']);
        $this->edit('Brand new headline');
        app()->setLocale('fr');

        // Not translated yet: the visitor sees the admin's English, never the stale shipped French.
        $this->assertSame('Brand new headline', SiteContent::page('home')['hero']['headline']);

        Bus::assertDispatched(TranslateSiteCopyJob::class, fn ($j) => $j->page === 'home');
    }

    public function test_the_job_translates_edited_copy_per_language_and_stale_translations_are_ignored(): void
    {
        $this->edit('Brand new headline');
        $this->fakeClaude('[T] ');

        (new TranslateSiteCopyJob('home'))->handle(app(\App\Services\AI\AnthropicClient::class));

        app()->setLocale('fr');
        $this->assertSame('[T] Brand new headline', SiteContent::page('home')['hero']['headline']);
        app()->setLocale('ha');
        $this->assertSame('[T] Brand new headline', SiteContent::page('home')['hero']['headline']);
        // Untouched fields keep their curated shipped translation, not the machine one.
        app()->setLocale('fr');
        $this->assertStringNotContainsString('[T]', SiteContent::page('home')['hero']['eyebrow']);

        // The admin edits the English again: the old translation goes inert immediately (job held back here to prove it).
        Bus::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(SiteEditor::class)->set('sections.hero.headline', 'Edited again')->call('save');
        app()->setLocale('fr');
        $this->assertSame('Edited again', SiteContent::page('home')['hero']['headline']);

        // English visitors always see the English edit.
        app()->setLocale('en');
        $this->assertSame('Edited again', SiteContent::page('home')['hero']['headline']);
    }

    public function test_a_malformed_model_reply_never_corrupts_the_page(): void
    {
        $this->edit('Brand new headline');
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => '{"hero|headline": ["not", "a", "string"]}']]])]);

        (new TranslateSiteCopyJob('home'))->handle(app(\App\Services\AI\AnthropicClient::class));

        app()->setLocale('fr');
        $this->assertSame('Brand new headline', SiteContent::page('home')['hero']['headline']);
    }

    public function test_urls_colours_and_numbers_are_never_sent_for_translation(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(SiteEditor::class)
            ->set('sections.hero.cta_primary_url', '/somewhere')
            ->set('sections.hero.radius_top', '40')
            ->set('sections.hero.headline', 'Words to translate')
            ->call('save');

        $sent = TranslateSiteCopyJob::editedText('home');
        $this->assertArrayHasKey('headline', $sent['hero']);
        $this->assertArrayNotHasKey('radius_top', $sent['hero']);
        $this->assertArrayNotHasKey('cta_primary_url', $sent['hero'] ?? []);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Admin\Announcements;
use App\Livewire\Admin\Updater;
use App\Models\ChangelogEntry;
use App\Models\PlatformUpdateAttempt;
use App\Models\User;
use App\Services\AI\AnthropicClient;
use App\Support\UserDataExporter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeAnthropicClient;
use Tests\TestCase;

/**
 * Content/billing/hot-menu blueprint Phase E — the updater changelog system:
 * drafting a "What's New" entry off a succeeded update, publishing it
 * (append-only), the in-app "What's New" screen, the one-click announce
 * hand-off, and the data-export appendix.
 */
class ChangelogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function succeededAttempt(?string $changelog = 'Fixed a bug. Added a feature.'): PlatformUpdateAttempt
    {
        return PlatformUpdateAttempt::create([
            'package_id' => 'pkg-1',
            'from_version' => '1.0.0',
            'to_version' => '1.1.0',
            'changelog' => $changelog,
            'status' => PlatformUpdateAttempt::STATUS_SUCCEEDED,
        ]);
    }

    public function test_a_changelog_entry_can_only_be_drafted_from_a_succeeded_attempt(): void
    {
        $pending = PlatformUpdateAttempt::create([
            'package_id' => 'pkg-2', 'to_version' => '1.2.0', 'status' => PlatformUpdateAttempt::STATUS_APPLYING,
        ]);

        Livewire::actingAs($this->superAdmin())->test(Updater::class)
            ->call('startChangelogDraft', $pending->id)
            ->assertSet('draftingForAttemptId', null);
    }

    public function test_ai_draft_is_unavailable_without_an_anthropic_key(): void
    {
        $this->app->instance(AnthropicClient::class, new FakeAnthropicClient([], on: false));
        $attempt = $this->succeededAttempt();

        Livewire::actingAs($this->superAdmin())->test(Updater::class)
            ->call('startChangelogDraft', $attempt->id)
            ->call('aiDraftChangelog')
            ->assertSet('changelogSummary', '')
            ->assertSet('changelogAiError', fn ($e) => is_string($e) && str_contains($e, 'API key'));
    }

    public function test_ai_draft_errors_when_the_package_carried_no_raw_changelog(): void
    {
        $this->app->instance(AnthropicClient::class, new FakeAnthropicClient(['summary' => 'x', 'notes' => 'y']));
        $attempt = $this->succeededAttempt(changelog: '');

        Livewire::actingAs($this->superAdmin())->test(Updater::class)
            ->call('startChangelogDraft', $attempt->id)
            ->call('aiDraftChangelog')
            ->assertSet('changelogAiError', fn ($e) => is_string($e) && str_contains($e, 'no raw changelog'));
    }

    public function test_ai_drafts_a_summary_and_notes_from_the_raw_changelog(): void
    {
        $this->app->instance(AnthropicClient::class, new FakeAnthropicClient([
            'summary' => 'Faster eSIM activation', 'notes' => 'We sped up activation and fixed a rare crash.',
        ]));
        $attempt = $this->succeededAttempt();

        Livewire::actingAs($this->superAdmin())->test(Updater::class)
            ->call('startChangelogDraft', $attempt->id)
            ->call('aiDraftChangelog')
            ->assertSet('changelogAiError', null)
            ->assertSet('changelogSummary', 'Faster eSIM activation')
            ->assertSet('changelogNotes', 'We sped up activation and fixed a rare crash.');
    }

    public function test_publishing_creates_an_append_only_entry_and_updater_reflects_it(): void
    {
        $attempt = $this->succeededAttempt();
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)->test(Updater::class)
            ->call('startChangelogDraft', $attempt->id)
            ->set('changelogSummary', 'Faster eSIM activation')
            ->set('changelogNotes', 'Details here.')
            ->call('saveChangelogEntry')
            ->assertHasNoErrors()
            ->assertSet('draftingForAttemptId', null)
            ->assertSet('changelogStatus', fn ($s) => str_contains((string) $s, '1.1.0'));

        $this->assertDatabaseHas('changelog_entries', [
            'version' => '1.1.0', 'summary' => 'Faster eSIM activation', 'notes' => 'Details here.', 'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'changelog.entry_created']);

        // The updater's own history table now shows it as published, not draftable again.
        Livewire::actingAs($admin)->test(Updater::class)
            ->assertSee('Published');
    }

    public function test_the_summary_is_required_to_publish(): void
    {
        $attempt = $this->succeededAttempt();

        Livewire::actingAs($this->superAdmin())->test(Updater::class)
            ->call('startChangelogDraft', $attempt->id)
            ->set('changelogSummary', '')
            ->call('saveChangelogEntry')
            ->assertHasErrors('changelogSummary');

        $this->assertDatabaseCount('changelog_entries', 0);
    }

    public function test_announce_this_update_prefills_a_new_announcement(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->withQueryParams(['prefill_title' => 'New in 1.1.0', 'prefill_body' => 'Faster eSIM activation.'])
            ->test(Announcements::class)
            ->assertSet('title', 'New in 1.1.0')
            ->assertSet('body', 'Faster eSIM activation.');
    }

    public function test_the_whats_new_page_lists_published_entries(): void
    {
        ChangelogEntry::create(['version' => '1.1.0', 'released_at' => now(), 'summary' => 'Faster eSIM activation']);

        $this->get(route('whats-new'))
            ->assertOk()
            ->assertSee('Faster eSIM activation')
            ->assertSee('1.1.0');
    }

    public function test_the_whats_new_page_handles_no_entries_gracefully(): void
    {
        $this->get(route('whats-new'))->assertOk()->assertSee('Nothing published yet');
    }

    public function test_a_users_data_export_includes_a_platform_version_history_appendix(): void
    {
        ChangelogEntry::create(['version' => '1.1.0', 'released_at' => '2026-01-01', 'summary' => 'Faster eSIM activation']);
        $user = User::factory()->create();

        $data = UserDataExporter::for($user);

        $this->assertArrayHasKey('platform_version_history', $data);
        $this->assertSame('1.1.0', $data['platform_version_history']['entries'][0]['version']);
        $this->assertSame('Faster eSIM activation', $data['platform_version_history']['entries'][0]['summary']);
        // Never mixed into the personal-data keys.
        $this->assertArrayNotHasKey('platform_version_history', $data['account']);
    }
}

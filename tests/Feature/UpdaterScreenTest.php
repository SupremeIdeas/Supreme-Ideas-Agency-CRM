<?php

namespace Tests\Feature;

use App\Jobs\ApplyUpdateJob;
use App\Livewire\Admin\Updater;
use App\Models\User;
use App\Services\Updater\PackageBuilder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch 2 §4 — the admin Updater screen. Proves the gate (super_admin only),
 * the verify-before-apply confirmation moment, and that Apply hands off to the
 * queued job rather than doing the work inline.
 */
class UpdaterScreenTest extends TestCase
{
    use RefreshDatabase;

    private array $keys;

    private string $pkgDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);

        $this->pkgDir = storage_path('app/testing/screen-'.uniqid());
        File::ensureDirectoryExists($this->pkgDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pkgDir);
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function validUpload(): UploadedFile
    {
        $path = app(PackageBuilder::class)->build([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'changelog' => 'screen test',
            'files' => [['path' => 'app/Foo.php', 'action' => 'add', 'content' => '<?php // foo']],
        ]);

        return UploadedFile::fake()->createWithContent('update.naaraupdate', File::get($path));
    }

    public function test_a_non_super_admin_cannot_open_the_updater(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(Updater::class)
            ->assertStatus(403);
    }

    public function test_a_super_admin_verifies_a_package_then_queues_the_apply(): void
    {
        Queue::fake();

        Livewire::actingAs($this->user('super_admin'))
            ->test(Updater::class)
            ->set('package', $this->validUpload())
            ->call('verifyPackage')
            ->assertSet('verifyError', null)
            ->assertSet('verified.product', 'naarasim-core')
            ->assertSet('verified.files', 1)
            ->call('applyPackage')
            ->assertSet('status', fn ($s) => str_contains((string) $s, 'queued'));

        Queue::assertPushed(ApplyUpdateJob::class);
    }

    public function test_a_tampered_upload_is_rejected_and_not_queued(): void
    {
        Queue::fake();

        // Wrong public key configured → signature can't verify.
        config()->set('updater.public_key', PackageBuilder::generateKeypair()['public']);

        Livewire::actingAs($this->user('super_admin'))
            ->test(Updater::class)
            ->set('package', $this->validUpload())
            ->call('verifyPackage')
            ->assertSet('verified', null)
            ->assertSet('verifyError', fn ($e) => $e !== null);

        Queue::assertNotPushed(ApplyUpdateJob::class);
    }

    // -- Tier 5 #14: Capability Radar tab (master-only) ----------------------

    private function proposal(string $status = \App\Models\CapabilityProposal::NEW): \App\Models\CapabilityProposal
    {
        $snapshot = \App\Models\ProviderCapabilitySnapshot::create([
            'provider' => 'stripe', 'source_type' => 'changelog',
            'content_hash' => 'h1', 'raw_content' => 'evidence text', 'fetched_at' => now(),
        ]);

        return \App\Models\CapabilityProposal::create([
            'provider' => 'stripe', 'source_snapshot_id' => $snapshot->id,
            'raw_diff' => '+ new bulk-charge endpoint',
            'summary' => 'Stripe added a bulk-charge endpoint.',
            'capability_added' => 'POST /v1/bulk_charges',
            'relevance_to_naara' => 'Could batch merchant payouts.',
            'proposed_feature' => 'Batch merchant payout via bulk-charge',
            'estimated_effort' => 'medium',
            'confidence' => 'high',
            'requires_human_verification_because' => 'Confirm the endpoint is GA, not beta.',
            'status' => $status,
        ]);
    }

    public function test_a_non_super_admin_cannot_see_capability_proposals(): void
    {
        Livewire::actingAs($this->user('admin'))->test(Updater::class)->assertStatus(403);
    }

    public function test_the_radar_tab_lists_open_proposals_with_their_evidence(): void
    {
        $proposal = $this->proposal();

        Livewire::actingAs($this->user('super_admin'))
            ->test(Updater::class)
            ->set('tab', 'radar')
            ->assertSee('Batch merchant payout via bulk-charge')
            ->assertDontSee('+ new bulk-charge endpoint') // collapsed by default
            ->call('toggleProposalEvidence', $proposal->id)
            ->assertSee('+ new bulk-charge endpoint');
    }

    public function test_accepting_a_proposal_records_who_and_when(): void
    {
        $proposal = $this->proposal();
        $admin = $this->user('super_admin');

        Livewire::actingAs($admin)
            ->test(Updater::class)
            ->set('tab', 'radar')
            ->call('acceptProposal', $proposal->id);

        $proposal->refresh();
        $this->assertSame(\App\Models\CapabilityProposal::ACCEPTED, $proposal->status);
        $this->assertSame($admin->id, $proposal->reviewed_by);
        $this->assertNotNull($proposal->accepted_at);
    }

    public function test_deferring_a_proposal_removes_it_from_the_open_list_until_its_window_passes(): void
    {
        $proposal = $this->proposal();

        Livewire::actingAs($this->user('super_admin'))
            ->test(Updater::class)
            ->set('tab', 'radar')
            ->set('deferDays', '7')
            ->call('deferProposal', $proposal->id)
            ->assertDontSee('Batch merchant payout via bulk-charge');

        $proposal->refresh();
        $this->assertSame(\App\Models\CapabilityProposal::DEFERRED, $proposal->status);
        $this->assertTrue($proposal->deferred_until->isFuture());

        // Once the window has passed, it re-surfaces exactly like a new one.
        $proposal->forceFill(['deferred_until' => now()->subMinute()])->save();
        Livewire::actingAs($this->user('super_admin'))
            ->test(Updater::class)
            ->set('tab', 'radar')
            ->assertSee('Batch merchant payout via bulk-charge');
    }

    public function test_dismissing_a_proposal_captures_the_reason(): void
    {
        $proposal = $this->proposal();

        Livewire::actingAs($this->user('super_admin'))
            ->test(Updater::class)
            ->set('tab', 'radar')
            ->call('startDismiss', $proposal->id)
            ->set('dismissReasonInput', 'Not relevant to our roadmap')
            ->call('dismissProposal', $proposal->id);

        $proposal->refresh();
        $this->assertSame(\App\Models\CapabilityProposal::DISMISSED, $proposal->status);
        $this->assertSame('Not relevant to our roadmap', $proposal->dismissed_reason);
    }

    public function test_accepted_and_dismissed_proposals_move_to_recently_reviewed(): void
    {
        $this->proposal(\App\Models\CapabilityProposal::ACCEPTED);

        Livewire::actingAs($this->user('super_admin'))
            ->test(Updater::class)
            ->set('tab', 'radar')
            ->assertSee('Recently reviewed')
            ->assertSee('Accepted')
            ->assertSee('Batch merchant payout via bulk-charge');
    }
}

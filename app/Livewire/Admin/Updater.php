<?php

namespace App\Livewire\Admin;

use App\Jobs\ApplyUpdateJob;
use App\Models\CapabilityProposal;
use App\Models\ChangelogEntry;
use App\Models\PlatformUpdateAttempt;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Services\Marketing\MarketingCopywriter;
use App\Services\Updater\ThemeInstaller;
use App\Services\Updater\UpdateApplier;
use App\Services\Updater\PackageVerifier;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\ThemePreset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Platform Updater (Batch 2 §4, Batch 3 §3). The single most sensitive
 * "install something onto the platform" screen in the admin panel, in three tabs:
 *
 * - Code updates: upload a signed `.naaraupdate`, see its verified manifest
 *   BEFORE applying (a human-confirmation moment with real information, not a
 *   blind-trust click), then apply it as a queued job that self-rolls-back on
 *   any failure.
 * - Themes: upload a signed theme `.naaraupdate` (package_type: "theme"), see
 *   its name/persona/colour swatches/font warnings before installing, then
 *   activate or remove installed (non-built-in) themes.
 * - Capability Radar (Tier 5 #14, master-only): reviewable proposals for
 *   possible new features, generated from a REAL detected change in a
 *   provider's own API — never auto-implemented. Visually and functionally
 *   distinct from the other two tabs: this is about surfacing candidate
 *   changes nobody has decided on yet, not installing something already
 *   decided.
 *
 * All tabs are gated super_admin only. Per Batch 3 §0, a theme install is
 * genuinely lower-risk than a code update (ThemePreset's render path only ever
 * emits a small whitelisted set of CSS variables, so even a malicious theme
 * can't inject CSS or execute code) — but this stays the one screen for
 * "install a package onto this platform," so it keeps the same strict gate as
 * the code-update tab rather than splitting access by action. The existing
 * day-to-day Admin\ThemePicker screen (activate/tweak an already-installed
 * theme) keeps its own broader admin/theme.manage gate unchanged — this tab is
 * specifically for installing a NEW theme package, which is the batch-like,
 * more consequential action.
 */
#[Layout('components.layouts.admin')]
class Updater extends Component
{
    use WithFileUploads;

    /** Which tab is open: 'code' or 'themes'. */
    public string $tab = 'code';

    public $package;

    /** Verified manifest summary shown before the Apply button appears. */
    public ?array $verified = null;

    public ?string $verifyError = null;

    public ?string $status = null;

    /** Absolute path to the staged (verified) package awaiting apply. */
    public ?string $stagedPath = null;

    // --- Themes tab state ---

    public $themePackage;

    /** Preview summary shown before the Install button appears. */
    public ?array $themePreview = null;

    public ?string $themeVerifyError = null;

    public ?string $themeStatus = null;

    /** Absolute path to the staged (previewed) theme package awaiting install. */
    public ?string $themeStagedPath = null;

    // --- Changelog drafting (content/billing/hot-menu blueprint Phase E) ---

    /** The attempt currently being drafted into a changelog entry, or null. */
    public ?int $draftingForAttemptId = null;

    public string $changelogSummary = '';

    public string $changelogNotes = '';

    public ?string $changelogAiError = null;

    public ?string $changelogStatus = null;

    // --- Capability Radar (Tier 5 #14, master-only) ---

    /** The proposal currently expanded to show its raw evidence, or null. */
    public ?int $expandedProposalId = null;

    public string $deferDays = '30';

    public ?string $dismissReasonInput = null;

    /** The proposal a dismiss-reason prompt is currently open for, or null. */
    public ?int $dismissingProposalId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
    }

    public function verifyPackage(PackageVerifier $verifier): void
    {
        $this->reset(['verified', 'verifyError', 'stagedPath', 'status']);

        $this->validate([
            'package' => 'required|file|max:1048576', // up to 1 GB
        ]);

        if (! str_ends_with(strtolower($this->package->getClientOriginalName()), '.naaraupdate')) {
            $this->verifyError = 'That is not a .naaraupdate file.';

            return;
        }

        // Move off the volatile livewire-tmp area to a stable local path.
        $relative = $this->package->storeAs('update-incoming', 'upload-'.Str::uuid().'.naaraupdate', 'local');
        $absolute = Storage::disk('local')->path($relative);

        $result = $verifier->verify($absolute);
        if (! $result->passed) {
            @unlink($absolute);
            $this->verifyError = $result->reason;
            Auditor::log('update.rejected', null, null, ['reason' => $result->reason, 'stage' => 'admin-upload']);

            return;
        }

        $m = $result->manifest;

        // A theme package went into the wrong tab — route it correctly rather
        // than letting it run the full maintenance-mode/backup/health-check
        // pipeline for something that doesn't need it.
        if ($m->packageType === 'theme') {
            @unlink($absolute);
            $this->verifyError = 'This is a theme package — install it from the Themes tab instead.';

            return;
        }

        $this->stagedPath = $absolute;
        $this->verified = [
            'product' => $m->product,
            'version' => $m->version,
            'min_compatible' => $m->minCompatibleVersion,
            'package_type' => $m->packageType,
            'files' => count($m->files),
            'migrations' => count($m->migrations),
            'deletions' => count($m->deletions),
            'requires_composer' => $m->requiresComposerInstall,
            'requires_npm' => $m->requiresNpmBuild,
            'changelog' => $m->changelog,
        ];
    }

    public function applyPackage(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        if ($this->stagedPath === null || ! is_file($this->stagedPath)) {
            $this->verifyError = 'No verified package is staged — upload and verify one first.';

            return;
        }

        ApplyUpdateJob::dispatch($this->stagedPath, Auth::id());
        Auditor::log('update.apply_requested', null, null, ['version' => $this->verified['version'] ?? null]);

        $this->reset(['package', 'verified', 'stagedPath']);
        $this->status = 'Update queued. It runs in the background with a full backup and automatic rollback — watch the status below.';
    }

    public function getCurrentVersionProperty(): ?string
    {
        return Setting::getValue(UpdateApplier::VERSION_SETTING);
    }

    public function getAttemptsProperty()
    {
        return PlatformUpdateAttempt::query()
            ->latest()
            ->limit(15)
            ->get();
    }

    /** Whether a version already has a published "What's New" entry. */
    public function hasChangelogEntry(string $version): bool
    {
        return ChangelogEntry::where('version', $version)->exists();
    }

    /** Open the drafting form for one succeeded attempt. */
    public function startChangelogDraft(int $attemptId): void
    {
        $attempt = PlatformUpdateAttempt::find($attemptId);
        if ($attempt === null || $attempt->status !== PlatformUpdateAttempt::STATUS_SUCCEEDED) {
            return;
        }

        $this->draftingForAttemptId = $attemptId;
        $this->changelogSummary = '';
        $this->changelogNotes = '';
        $this->changelogAiError = null;
    }

    public function cancelChangelogDraft(): void
    {
        $this->reset(['draftingForAttemptId', 'changelogSummary', 'changelogNotes', 'changelogAiError']);
    }

    /** AI-draft the summary/notes from the package's own raw changelog text. */
    public function aiDraftChangelog(MarketingCopywriter $writer): void
    {
        $this->changelogAiError = null;

        $attempt = PlatformUpdateAttempt::find($this->draftingForAttemptId);
        if ($attempt === null) {
            return;
        }

        if (! $writer->enabled()) {
            $this->changelogAiError = 'Add an Anthropic API key (Integrations) to draft this with Claude.';

            return;
        }
        if (trim((string) $attempt->changelog) === '') {
            $this->changelogAiError = 'This update package carries no raw changelog text to draft from — write the entry by hand.';

            return;
        }

        $draft = $writer->changelogDraft($attempt->to_version, (string) $attempt->changelog);
        if ($draft === null) {
            $this->changelogAiError = 'Claude couldn’t draft this — try again or write it by hand.';

            return;
        }

        $this->changelogSummary = $draft['summary'];
        $this->changelogNotes = $draft['notes'];
    }

    /** Publish the changelog entry (append-only — never an edit/delete action). */
    public function saveChangelogEntry(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $attempt = PlatformUpdateAttempt::find($this->draftingForAttemptId);
        if ($attempt === null) {
            $this->cancelChangelogDraft();

            return;
        }

        $this->validate([
            'changelogSummary' => 'required|string|max:500',
            'changelogNotes' => 'nullable|string|max:5000',
        ]);

        $entry = ChangelogEntry::create([
            'version' => $attempt->to_version,
            'released_at' => now()->toDateString(),
            'summary' => trim($this->changelogSummary),
            'notes' => trim($this->changelogNotes) ?: null,
            'created_by' => Auth::id(),
        ]);

        Auditor::log('changelog.entry_created', ChangelogEntry::class, $entry->id, ['version' => $entry->version]);

        $this->cancelChangelogDraft();
        $this->changelogStatus = "\"What's New\" entry for {$entry->version} published.";
    }

    public function getChangelogEntriesProperty()
    {
        return ChangelogEntry::latest('released_at')->latest('id')->limit(10)->get();
    }

    // --- Themes tab ---

    public function verifyTheme(ThemeInstaller $installer): void
    {
        $this->reset(['themePreview', 'themeVerifyError', 'themeStagedPath', 'themeStatus']);

        $this->validate([
            'themePackage' => 'required|file|max:1048576',
        ]);

        if (! str_ends_with(strtolower($this->themePackage->getClientOriginalName()), '.naaraupdate')) {
            $this->themeVerifyError = 'That is not a .naaraupdate file.';

            return;
        }

        $relative = $this->themePackage->storeAs('update-incoming', 'theme-'.Str::uuid().'.naaraupdate', 'local');
        $absolute = Storage::disk('local')->path($relative);

        try {
            $preview = $installer->preview($absolute);
        } catch (\Throwable $e) {
            @unlink($absolute);
            $this->themeVerifyError = $e->getMessage();
            Auditor::log('update.rejected', null, null, ['reason' => $e->getMessage(), 'stage' => 'admin-theme-upload']);

            return;
        }

        $this->themeStagedPath = $absolute;
        $this->themePreview = [
            'slug' => $preview->slug,
            'name' => $preview->name,
            'persona' => $preview->persona,
            'swatches' => $preview->colorSwatches,
            'icon_family' => $preview->iconFamily,
            'layout_variants' => $preview->layoutVariants,
            'font_warnings' => $preview->fontWarnings,
            'version' => $preview->version,
        ];
    }

    public function installTheme(ThemeInstaller $installer): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        if ($this->themeStagedPath === null || ! is_file($this->themeStagedPath)) {
            $this->themeVerifyError = 'No verified theme is staged — upload and verify one first.';

            return;
        }

        try {
            $row = $installer->install($this->themeStagedPath, Auth::id());
        } catch (\Throwable $e) {
            $this->themeVerifyError = $e->getMessage();

            return;
        } finally {
            @unlink($this->themeStagedPath);
        }

        $warnings = $this->themePreview['font_warnings'] ?? [];
        $this->reset(['themePackage', 'themePreview', 'themeStagedPath']);
        $this->themeStatus = "{$row->name} installed."
            .($warnings === [] ? '' : ' '.count($warnings).' font warning(s) — see the theme picker for details.');
    }

    /** Activate an installed theme platform-wide — same primitives ThemePicker::apply() uses. */
    public function activateTheme(string $slug): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $exists = ThemePresetModel::where('slug', $slug)->exists();
        if (! $exists) {
            $this->themeVerifyError = 'Unknown theme.';

            return;
        }

        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();
        Auditor::log('theme.applied', 'ThemePreset', null, ['slug' => $slug]);

        $this->themeStatus = 'Theme activated.';
    }

    /** Remove an installed, non-built-in theme and its stored assets. */
    public function removeTheme(string $slug): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $row = ThemePresetModel::where('slug', $slug)->first();
        if ($row === null) {
            return;
        }
        if ($row->is_built_in) {
            $this->themeVerifyError = 'Built-in themes cannot be removed.';

            return;
        }

        // Never leave the active-theme setting pointing at a deleted row.
        if (Setting::getValue(ThemePreset::SETTING_KEY) === $slug) {
            Setting::setValue(ThemePreset::SETTING_KEY, ThemePreset::DEFAULT_SLUG);
        }

        Storage::disk(MediaStorage::disk())->deleteDirectory("themes/{$slug}");
        $row->delete();

        ThemePreset::bust();
        Auditor::log('theme.removed', 'ThemePreset', null, ['slug' => $slug]);
        $this->themeStatus = 'Theme removed.';
    }

    public function getInstalledThemesProperty()
    {
        return ThemePreset::all();
    }

    public function getActiveThemeSlugProperty(): string
    {
        return ThemePreset::slug();
    }

    // --- Capability Radar (Tier 5 #14, master-only) ---

    public function toggleProposalEvidence(int $id): void
    {
        $this->expandedProposalId = $this->expandedProposalId === $id ? null : $id;
    }

    // --- API version-watch (blueprint L, master-only) ---

    public ?string $apiWatchNote = null;

    /** @return \Illuminate\Support\Collection<int, \App\Models\ProviderApiWatch> */
    public function getApiWatchesProperty()
    {
        return class_exists(\App\Models\ProviderApiWatch::class) ? \App\Models\ProviderApiWatch::orderBy('provider')->get() : collect();
    }

    public function acknowledgeApiWatch(string $provider): void
    {
        abort_unless(Auth::user()->hasRole('super_admin') && class_exists(\App\Services\ProviderRadar\ApiVersionWatch::class), 403);
        app(\App\Services\ProviderRadar\ApiVersionWatch::class)->acknowledge($provider);
        Auditor::log('api_watch.acknowledged', \App\Models\ProviderApiWatch::class, null, ['provider' => $provider]);
    }

    public function runApiWatchNow(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin') && class_exists(\App\Jobs\ApiVersionWatchJob::class), 403);
        \App\Jobs\ApiVersionWatchJob::dispatch();
        $this->apiWatchNote = 'Check queued. Refresh in a minute to see the results.';
    }

    /** Accept a proposal — v1 is a free-text admin note + accepted_at, per the blueprint (no full project-management system). */
    public function acceptProposal(int $id): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $proposal = CapabilityProposal::find($id);
        if ($proposal === null) {
            return;
        }

        $proposal->forceFill([
            'status' => CapabilityProposal::ACCEPTED,
            'reviewed_by' => Auth::id(),
            'accepted_at' => now(),
        ])->save();

        Auditor::log('capability_radar.accepted', CapabilityProposal::class, $proposal->id, ['provider' => $proposal->provider]);
    }

    /** Re-surface a proposal after N days — useful for "interesting but not now." */
    public function deferProposal(int $id): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $days = max(1, (int) $this->deferDays);
        $proposal = CapabilityProposal::find($id);
        if ($proposal === null) {
            return;
        }

        $proposal->forceFill([
            'status' => CapabilityProposal::DEFERRED,
            'reviewed_by' => Auth::id(),
            'deferred_until' => now()->addDays($days),
        ])->save();

        Auditor::log('capability_radar.deferred', CapabilityProposal::class, $proposal->id, ['days' => $days]);
    }

    public function startDismiss(int $id): void
    {
        $this->dismissingProposalId = $id;
        $this->dismissReasonInput = '';
    }

    public function cancelDismiss(): void
    {
        $this->reset(['dismissingProposalId', 'dismissReasonInput']);
    }

    /** Dismiss with an optional reason — worth capturing so patterns become visible over time. */
    public function dismissProposal(int $id): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $proposal = CapabilityProposal::find($id);
        if ($proposal === null) {
            return;
        }

        $proposal->forceFill([
            'status' => CapabilityProposal::DISMISSED,
            'reviewed_by' => Auth::id(),
            'dismissed_reason' => trim((string) $this->dismissReasonInput) ?: null,
        ])->save();

        Auditor::log('capability_radar.dismissed', CapabilityProposal::class, $proposal->id, ['reason' => $proposal->dismissed_reason]);
        $this->cancelDismiss();
    }

    /**
     * Newest-first: NEW/REVIEWING proposals, plus any DEFERRED one whose
     * window has passed (isDue()) — a deferred proposal re-surfaces here
     * exactly like a new one once its time is up.
     */
    public function getCapabilityProposalsProperty()
    {
        return CapabilityProposal::with('sourceSnapshot')
            ->whereIn('status', [CapabilityProposal::NEW, CapabilityProposal::REVIEWING, CapabilityProposal::DEFERRED])
            ->latest('created_at')
            ->get()
            ->filter(fn (CapabilityProposal $p) => $p->isDue())
            ->values();
    }

    public function getReviewedCapabilityProposalsProperty()
    {
        return CapabilityProposal::whereIn('status', [CapabilityProposal::ACCEPTED, CapabilityProposal::DISMISSED])
            ->latest('updated_at')
            ->limit(15)
            ->get();
    }

    public function render(MarketingCopywriter $writer)
    {
        return view('livewire.admin.updater', [
            'aiEnabled' => $writer->enabled(),
        ]);
    }
}

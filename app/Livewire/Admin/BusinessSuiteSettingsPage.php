<?php

namespace App\Livewire\Admin;

use App\Jobs\AlertAdminJob;
use App\Models\AuditLog;
use App\Models\CallFlow;
use App\Models\Setting;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Services\Business\SetupHealth;
use App\Services\Media\FfmpegManager;
use App\Support\Auditor;
use App\Support\HostingGuide;
use App\Support\Business\BusinessSettingsSchema;
use App\Support\FeatureEntitlements;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin > Business Suite (MASTER-ONLY): the control panel. Four tabs:
 *  - Settings: every switch, limit and default, rendered from BusinessSettingsSchema (default shown, per-field reset, audited, super admin only);
 *  - Setup and health: everything the Suite needs from the server, in plain words, with one-click fixes, the media converter (ffmpeg) installer and the cron/queue/VPS guides (SetupHealth);
 *  - Workspaces: read-only oversight of this install's own business workspaces;
 *  - Activity: the audit trail of Suite changes.
 * Off means hidden, not deleted: workspaces, seats and flows stay in the database and come back when it is switched on.
 */
#[Layout('components.layouts.admin')]
class BusinessSuiteSettingsPage extends Component
{
    use WithFileUploads;

    public const TABS = ['settings', 'readiness', 'workspaces', 'activity'];

    #[Url(as: 'tab')]
    public string $tab = 'settings';

    /** @var array<string, mixed> key => working value (dots in keys are encoded as "__") */
    public array $values = [];

    public bool $confirmed = false;

    public ?string $saved = null;

    /** Setup and health: ffmpeg settings, the uploaded binary, the live install state. */
    public string $ffmpegPath = '';

    public string $ffmpegMirror = '';

    public $ffmpegUpload = null;

    public bool $ffmpegInstalling = false;

    /** @var array{ok: bool, message: string}|null */
    public ?array $fixResult = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) && FeatureEntitlements::isMaster(), 403);
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'settings';
        }
        $this->load();
        $this->ffmpegPath = (string) Setting::getValue(FfmpegManager::SETTING_PATH, '');
        $this->ffmpegMirror = (string) Setting::getValue(FfmpegManager::SETTING_MIRROR, '');
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'settings';
    }

    public static function slot(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    private function canWrite(): bool
    {
        return (bool) Auth::user()?->hasRole('super_admin') && FeatureEntitlements::isMaster();
    }

    private function load(): void
    {
        foreach (BusinessSettingsSchema::flat() as $key => $field) {
            $this->values[self::slot($key)] = BusinessSettingsSchema::current($field);
        }
    }

    // ── Setup and health actions ───────────────────────────────────────────

    public function runFix(string $id): void
    {
        abort_unless($this->canWrite(), 403);
        $this->fixResult = app(SetupHealth::class)->fix($id);
        Auditor::log('business_suite.setup_fix', 'Setting', 0, ['fix' => $id, 'ok' => $this->fixResult['ok']]);
        $this->dispatch('nx-toast', type: $this->fixResult['ok'] ? 'success' : 'error', message: $this->fixResult['message']);
    }

    public function ffmpegStart(): void
    {
        abort_unless($this->canWrite(), 403);
        $s = app(FfmpegManager::class)->installStart();
        $this->ffmpegInstalling = ! in_array($s['phase'], ['done', 'failed'], true);
        Auditor::log('business_suite.ffmpeg_install_started', 'Setting', 0, []);
    }

    /** Polled (wire:poll) while an install runs: one slice of work per call, so no request outlives a shared host's time limit. */
    public function ffmpegTick(): void
    {
        abort_unless($this->canWrite(), 403);
        $s = app(FfmpegManager::class)->installStep();
        if (in_array($s['phase'], ['done', 'failed'], true)) {
            $this->ffmpegInstalling = false;
            if ($s['phase'] === 'done') {
                Auditor::log('business_suite.ffmpeg_installed', 'Setting', 0, ['version' => $s['version'] ?? null]);
                $this->dispatch('nx-toast', type: 'success', message: 'ffmpeg installed.');
            }
        }
    }

    public function ffmpegSaveSettings(): void
    {
        abort_unless($this->canWrite(), 403);
        $this->resetErrorBag();
        $path = trim($this->ffmpegPath);
        $mirror = trim($this->ffmpegMirror);
        if ($path !== '' && (! str_starts_with($path, '/') || str_contains($path, '..') || ! preg_match('#^/[\w./+-]+$#', $path))) {
            $this->addError('ffmpegPath', 'Type the full path to the program, for example /usr/bin/ffmpeg.');

            return;
        }
        if ($mirror !== '' && ! preg_match('#^https://[^\s]+$#i', $mirror)) {
            $this->addError('ffmpegMirror', 'The download link must start with https:// .');

            return;
        }
        Setting::setValue(FfmpegManager::SETTING_PATH, $path, 'business');
        Setting::setValue(FfmpegManager::SETTING_MIRROR, $mirror, 'business');
        app(FfmpegManager::class)->forget();
        Auditor::log('business_suite.ffmpeg_settings', 'Setting', 0, ['path' => $path, 'mirror' => $mirror]);
        $this->dispatch('nx-toast', type: 'success', message: 'Saved. Checking again below.');
    }

    public function ffmpegUploadNow(): void
    {
        abort_unless($this->canWrite(), 403);
        $this->resetErrorBag();
        $this->validate(['ffmpegUpload' => ['required', 'file', 'max:262144']], [], ['ffmpegUpload' => 'ffmpeg file']);
        try {
            $s = app(FfmpegManager::class)->installUploaded($this->ffmpegUpload);
            Auditor::log('business_suite.ffmpeg_uploaded', 'Setting', 0, ['version' => $s['version'] ?? null]);
            $this->dispatch('nx-toast', type: 'success', message: 'ffmpeg installed from your upload.');
        } catch (\InvalidArgumentException $e) {
            $this->addError('ffmpegUpload', $e->getMessage());
        }
        $this->ffmpegUpload = null;
    }

    public function ffmpegRemove(): void
    {
        abort_unless($this->canWrite(), 403);
        app(FfmpegManager::class)->uninstall();
        Auditor::log('business_suite.ffmpeg_removed', 'Setting', 0, []);
        $this->dispatch('nx-toast', type: 'success', message: 'The managed ffmpeg was removed.');
    }

    private function normalise(array $field, mixed $v): mixed
    {
        return match ($field['type']) {
            'bool' => (bool) $v,
            'int' => (int) $v,
            'float' => round((float) $v, 4),
            default => (string) $v,
        };
    }

    /** @return array<string, array{old: mixed, new: mixed, field: array}> */
    private function dirty(): array
    {
        $out = [];
        foreach (BusinessSettingsSchema::flat() as $key => $field) {
            if (! ($field['available'] ?? true)) {
                continue; // a module that has not shipped cannot be changed
            }
            $new = $this->normalise($field, $this->values[self::slot($key)] ?? null);
            $old = BusinessSettingsSchema::current($field);
            if ($new !== $old) {
                $out[$key] = ['old' => $old, 'new' => $new, 'field' => $field];
            }
        }

        return $out;
    }

    public function resetField(string $slot): void
    {
        $key = str_replace('__', '.', $slot);
        $field = BusinessSettingsSchema::field($key);
        abort_unless($field && ($field['available'] ?? true) && $this->canWrite(), 403);

        $old = BusinessSettingsSchema::current($field);
        Setting::where('key', $key)->first()?->delete(); // model delete, so the resolver cache is cleared
        $this->values[$slot] = BusinessSettingsSchema::current($field);
        Auditor::log('business_suite.setting_reset', 'Setting', 0, ['key' => $key, 'old' => $old, 'default' => $field['default']]);
        $this->dispatch('nx-toast', type: 'success', message: $field['label'].' reset to its default.');
    }

    public function save(): void
    {
        abort_unless($this->canWrite(), 403);
        $this->saved = null;
        $this->resetErrorBag();

        $dirty = $this->dirty();
        if ($dirty === []) {
            $this->saved = 'Nothing to save. Everything already matches.';

            return;
        }

        $failed = false;
        foreach ($dirty as $key => $d) {
            $v = Validator::make(['v' => $d['new']], ['v' => BusinessSettingsSchema::rules($d['field'])], [], ['v' => $d['field']['label']]);
            if ($v->fails()) {
                $this->addError('values.'.self::slot($key), $v->errors()->first('v'));
                $failed = true;
            }
        }
        if ($failed) {
            return;
        }

        $risky = array_filter($dirty, fn ($d) => $d['field']['danger'] ?? false);
        if ($risky !== [] && ! $this->confirmed) {
            $this->addError('confirmed', 'Please tick the box to confirm you understand these changes affect live customers.');

            return;
        }

        $changes = [];
        foreach ($dirty as $key => $d) {
            Setting::setValue($key, $d['new'], BusinessSettingsSchema::GROUP);
            $changes[$key] = ['from' => $d['old'], 'to' => $d['new']];
        }

        Auditor::log('business_suite.settings_changed', 'Setting', 0, ['by' => Auth::id(), 'changes' => $changes]);
        if ($risky !== []) {
            $summary = implode('; ', array_map(fn ($k, $c) => $k.': '.json_encode($c['from']).' -> '.json_encode($c['to']), array_keys($changes), $changes));
            AlertAdminJob::dispatch(code: 'business_suite_settings_changed', message: 'Business Suite settings changed by '.(Auth::user()->email ?? 'an admin').': '.$summary, context: ['by' => Auth::id(), 'count' => count($changes)]);
        }

        $this->confirmed = false;
        $this->load();
        $this->saved = count($changes).' setting'.(count($changes) === 1 ? '' : 's').' saved.';
        $this->dispatch('nx-toast', type: 'success', message: $this->saved);
    }

    public function render()
    {
        $data = [
            'canWrite' => $this->canWrite(),
            'dirtyKeys' => [],
            'needsConfirm' => false,
        ];

        if ($this->tab === 'settings') {
            $dirty = $this->dirty();
            $data['groups'] = BusinessSettingsSchema::groups();
            $data['dirtyKeys'] = array_keys($dirty);
            $data['needsConfirm'] = array_filter($dirty, fn ($d) => $d['field']['danger'] ?? false) !== [];
        }

        if ($this->tab === 'readiness') {
            $health = app(SetupHealth::class);
            $data['groups'] = $health->groups();
            $data['counts'] = $health->counts($data['groups']);
            $ff = app(FfmpegManager::class);
            $data['ff'] = $ff->info($this->ffmpegInstalling);
            $data['ffState'] = $ff->installState();
            $data['guide'] = HostingGuide::steps();
            $data['voiceGuide'] = SetupHealth::voiceGuide();
            $data['hostMode'] = HostingGuide::mode();
            $data['cronLine'] = HostingGuide::cronLine();
        }

        if ($this->tab === 'workspaces') {
            $data['workspaces'] = WalletGroup::query()
                ->where('kind', WalletGroup::KIND_BUSINESS)
                ->with('owner:id,name,email')
                ->withCount('members')
                ->latest('id')->limit(100)->get()
                ->map(fn (WalletGroup $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'owner' => $g->owner?->name ?: $g->owner?->email,
                    'seats' => $g->members_count,
                    'numbers' => VirtualNumber::where('wallet_group_id', $g->id)->count(),
                    'live_flows' => CallFlow::where('wallet_group_id', $g->id)->where('status', CallFlow::ACTIVE)->count(),
                    'verified' => (bool) $g->business_email_verified_at,
                    'created' => $g->created_at,
                ]);
        }

        if ($this->tab === 'activity') {
            $data['activity'] = AuditLog::query()
                ->where(fn ($q) => $q->where('action', 'like', 'business%')->orWhere('action', 'like', 'wallet_group.%'))
                ->latest('id')->limit(50)->get();
        }

        $data['workspaceCount'] = WalletGroup::query()->where('kind', WalletGroup::KIND_BUSINESS)->count();
        $data['liveFlowCount'] = CallFlow::query()->where('status', CallFlow::ACTIVE)->count();

        return view('livewire.admin.business-suite-settings', $data);
    }
}

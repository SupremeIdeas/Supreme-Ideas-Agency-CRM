<?php

namespace App\Livewire\Admin\N2N;

use App\Models\N2N\Report;
use App\Models\User;
use App\Services\N2N\Messaging\Moderation;
use App\Services\N2N\Messaging\MessagingException;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin > N2N > Reports (MASTER-ONLY): the review queue for member reports. Staff see the excerpt captured at report time (never the whole chat),
 * choose an action, and can lift a restriction. Every action is audited; nothing is automatic.
 */
class Reports extends Component
{
    #[Url]
    public string $tab = 'open';

    public ?string $message = null;

    /** @var array<int, string> staff notes keyed by report id */
    public array $notes = [];

    private function staff(): User
    {
        $u = Auth::user();
        abort_unless($u && Moderation::canModerate($u), 403);
        \App\Support\ComingSoon::abortIfOff('n2n');   // switched off: the friendly sheet on a page load, never a bare 403 behind a menu link
        abort_unless(N2NSettings::enabled(), 403);

        return $u;
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['open', 'closed', 'restricted', 'moderators'], true) && ($tab !== 'moderators' || Moderation::isAdmin(Auth::user())) ? $tab : 'open';
    }

    public function resolve(int $reportId, string $action, Moderation $mod): void
    {
        $staff = $this->staff();
        try {
            $mod->resolve(Report::query()->findOrFail($reportId), $staff, $action, $this->notes[$reportId] ?? null);
            $this->message = 'Report #'.$reportId.' closed ('.str_replace('_', ' ', $action).').';
            unset($this->notes[$reportId]);
        } catch (MessagingException $e) {
            $this->message = $e->getMessage();
        }
    }

    public function lift(int $userId, Moderation $mod): void
    {
        $staff = $this->staff();
        try {
            $mod->lift(User::query()->findOrFail($userId), $staff);
            $this->message = 'Restriction lifted.';
        } catch (MessagingException $e) {
            $this->message = $e->getMessage();
        }
    }

    public function setModerator(int $userId, bool $on, Moderation $mod): void
    {
        $staff = $this->staff();
        try {
            $mod->setModerator($staff, User::query()->findOrFail($userId), $on);
            $this->message = $on ? 'They can now work chat reports.' : 'Moderation access removed.';
        } catch (MessagingException $e) {
            $this->message = $e->getMessage();
        }
    }

    public function render()
    {
        $this->staff();
        $q = Report::query()->orderByDesc('id');
        $reports = $this->tab === 'closed' ? $q->whereIn('status', ['dismissed', 'actioned', 'reviewed'])->limit(100)->get() : ($this->tab === 'open' ? $q->where('status', 'open')->limit(100)->get() : collect());
        $ids = $reports->pluck('reporter_id')->merge($reports->pluck('reported_id'))->unique();
        $people = User::query()->whereIn('id', $ids)->get(['id', 'name', 'username', 'naara_id', 'n2n_restricted_until'])->keyBy('id');
        $moderators = $this->tab === 'moderators' && Moderation::isAdmin(Auth::user()) ? User::role('staff')->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin']))->orderBy('name')->limit(200)->get(['id', 'name', 'email']) : collect();
        $restricted = $this->tab === 'restricted' ? User::query()->where('n2n_restricted_until', '>', now())->orderBy('n2n_restricted_until')->limit(100)->get(['id', 'name', 'username', 'naara_id', 'n2n_restricted_until', 'n2n_restricted_reason']) : collect();

        return view('livewire.admin.n2n.reports', [
            'reports' => $reports, 'people' => $people, 'restricted' => $restricted, 'moderators' => $moderators, 'isAdmin' => Moderation::isAdmin(Auth::user()),
            'counts' => ['open' => Report::query()->where('status', 'open')->count(), 'restricted' => User::query()->where('n2n_restricted_until', '>', now())->count()],
            'attention' => $reports->mapWithKeys(fn ($r) => [$r->reported_id => Moderation::recentReporters($r->reported_id) >= Moderation::ATTENTION_REPORTERS]),
            'actions' => ['dismissed' => 'Dismiss', 'warned' => 'Warn (no restriction)', 'restricted_24h' => 'Restrict 24 hours', 'restricted_7d' => 'Restrict 7 days', 'restricted_permanent' => 'Restrict until lifted'],
        ]);
    }
}

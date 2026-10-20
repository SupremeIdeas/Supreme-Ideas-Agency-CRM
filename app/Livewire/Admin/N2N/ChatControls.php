<?php

namespace App\Livewire\Admin\N2N;

use App\Models\Setting;
use App\Services\N2N\Messaging\ChatGesturePrefs;
use App\Services\N2N\Messaging\MessageActions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Naara Chat > Chat controls (N2N, MASTER-ONLY): the platform defaults every member starts from, and anonymous gesture counters so the thresholds are tuned with data. */
#[Layout('components.layouts.admin')]
class ChatControls extends Component
{
    public bool $doubleTapReact = true;

    public string $doubleTapCode = 'love';

    public bool $swipeReply = true;

    public bool $listSwipe = true;

    public string $swipeStart = 'info';

    public bool $haptics = true;

    public int $longPressMs = 450;

    public int $swipeCommitPx = 56;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $p = ChatGesturePrefs::clean((array) Setting::getValue('n2n.gesture_defaults', []));
        $this->fill(['doubleTapReact' => $p['double_tap_react'], 'doubleTapCode' => $p['double_tap_code'], 'swipeReply' => $p['swipe_reply'], 'listSwipe' => $p['list_swipe'], 'swipeStart' => $p['swipe_start'], 'haptics' => $p['haptics'], 'longPressMs' => $p['long_press_ms'], 'swipeCommitPx' => $p['swipe_commit_px']]);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'doubleTapCode' => ['required', 'in:'.implode(',', array_keys(MessageActions::REACTIONS))],
            'swipeStart' => ['required', 'in:'.implode(',', ChatGesturePrefs::SWIPE_START)],
            'longPressMs' => ['required', 'integer', 'min:300', 'max:1000'],
            'swipeCommitPx' => ['required', 'integer', 'min:40', 'max:120'],
        ]);
        $clean = ChatGesturePrefs::clean(['double_tap_react' => $this->doubleTapReact, 'double_tap_code' => $this->doubleTapCode, 'swipe_reply' => $this->swipeReply, 'list_swipe' => $this->listSwipe, 'swipe_start' => $this->swipeStart, 'haptics' => $this->haptics, 'long_press_ms' => $this->longPressMs, 'swipe_commit_px' => $this->swipeCommitPx]);
        unset($clean['buttons_only']);   // a personal accessibility choice, never a platform default
        Setting::setValue('n2n.gesture_defaults', $clean, 'n2n');
        \App\Support\Auditor::log('n2n.gesture_defaults_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Saved. Members who have not changed their own settings now use these.');
    }

    public function render()
    {
        $since = now()->subDays(14)->toDateString();
        $rows = DB::table('n2n_gesture_stats')->where('day', '>=', $since)->selectRaw('gesture, outcome, device, sum(n) as total')->groupBy('gesture', 'outcome', 'device')->get();
        $by = [];
        foreach ($rows as $r) {
            $by[$r->gesture][$r->outcome] = ($by[$r->gesture][$r->outcome] ?? 0) + (int) $r->total;
            $by[$r->gesture]['dev_'.$r->device] = ($by[$r->gesture]['dev_'.$r->device] ?? 0) + (int) $r->total;
        }
        $stats = [];
        foreach (ChatGesturePrefs::GESTURES as $g) {
            $fired = (int) ($by[$g]['fired'] ?? 0);
            $cancel = (int) ($by[$g]['cancelled'] ?? 0);
            $stats[$g] = ['fired' => $fired, 'committed' => (int) ($by[$g]['committed'] ?? 0), 'cancelled' => $cancel, 'undone' => (int) ($by[$g]['undone'] ?? 0), 'fallback' => (int) ($by[$g]['fallback'] ?? 0), 'misfire_pct' => ((int) ($by[$g]['committed'] ?? 0)) > 0 ? round(((int) ($by[$g]['undone'] ?? 0)) / (int) $by[$g]['committed'] * 100) : null, 'cancel_pct' => $fired > 0 ? round($cancel / $fired * 100) : null];
        }

        return view('livewire.admin.n2n.chat-controls', ['reactions' => MessageActions::REACTIONS, 'stats' => $stats]);
    }
}

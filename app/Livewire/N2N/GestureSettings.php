<?php

namespace App\Livewire\N2N;

use App\Services\N2N\Messaging\ChatGesturePrefs;
use App\Services\N2N\Messaging\MessageActions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Account > Chat gestures and controls (N2N, MASTER-ONLY): switch each gesture, pick the double-tap reaction and the swipe-toward-start action, tune the timings, and practise on a sample bubble. */
#[Layout('components.layouts.n2n')]
class GestureSettings extends Component
{
    public bool $doubleTapReact = true;

    public string $doubleTapCode = 'love';

    public bool $swipeReply = true;

    public bool $listSwipe = true;

    public string $swipeStart = 'info';

    public bool $haptics = true;

    public int $longPressMs = 450;

    public int $swipeCommitPx = 56;

    public bool $buttonsOnly = false;

    public ?string $message = null;

    public function mount(): void
    {
        $p = ChatGesturePrefs::for(Auth::user());
        $this->fill([
            'doubleTapReact' => $p['double_tap_react'], 'doubleTapCode' => $p['double_tap_code'], 'swipeReply' => $p['swipe_reply'], 'swipeStart' => $p['swipe_start'], 'listSwipe' => $p['list_swipe'],
            'haptics' => $p['haptics'], 'longPressMs' => $p['long_press_ms'], 'swipeCommitPx' => $p['swipe_commit_px'], 'buttonsOnly' => $p['buttons_only'],
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'doubleTapCode' => ['required', 'in:'.implode(',', array_keys(MessageActions::REACTIONS))],
            'swipeStart' => ['required', 'in:'.implode(',', ChatGesturePrefs::SWIPE_START)],
            'longPressMs' => ['required', 'integer', 'min:300', 'max:1000'],
            'swipeCommitPx' => ['required', 'integer', 'min:40', 'max:120'],
        ]);
        ChatGesturePrefs::save(Auth::user(), [
            'double_tap_react' => $this->doubleTapReact, 'double_tap_code' => $this->doubleTapCode, 'swipe_reply' => $this->swipeReply, 'swipe_start' => $this->swipeStart, 'list_swipe' => $this->listSwipe,
            'haptics' => $this->haptics, 'long_press_ms' => $this->longPressMs, 'swipe_commit_px' => $this->swipeCommitPx, 'buttons_only' => $this->buttonsOnly,
        ]);
        $this->message = 'Saved. Your gestures use these settings from now on.';
        $this->dispatch('ng-config-changed');
    }

    public function resetToDefaults(): void
    {
        ChatGesturePrefs::reset(Auth::user());
        $this->mount();
        $this->message = 'Back to the platform defaults.';
        $this->dispatch('ng-config-changed');
    }

    public function render()
    {
        return view('livewire.n2n.gesture-settings', ['reactions' => MessageActions::REACTIONS, 'engine' => ChatGesturePrefs::forEngine(Auth::user())]);
    }
}

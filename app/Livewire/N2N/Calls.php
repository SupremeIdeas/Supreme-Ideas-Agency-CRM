<?php

namespace App\Livewire\N2N;

use App\Models\N2N\Call;
use App\Models\User;
use App\Models\N2N\GroupCall;
use App\Services\N2N\Calls\CallsAvailability;
use App\Services\N2N\Group\GroupAvailability;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Calls tab (N2N Batch 10, MASTER-ONLY): recents, missed calls and "Call back". It lists only the signed-in user's own calls, and never a call
 * that was suppressed for the caller's benefit (blocked / not allowed) as anything but an ordinary no-answer.
 */
#[Layout('components.layouts.n2n')]
class Calls extends Component
{
    public string $tab = 'all';

    public function render()
    {
        $me = Auth::id();
        $rows = Call::query()->where(fn ($q) => $q->where('caller_id', $me)->orWhere('callee_id', $me))
            ->whereNotIn('state', [Call::RINGING])->where(fn ($q) => $q->where('suppressed', false)->orWhere('caller_id', $me))
            ->latest('id')->limit(80)->get();
        $peers = User::query()->whereIn('id', $rows->map(fn ($c) => $c->peerOf($me))->unique())->get(['id', 'name', 'username', 'naara_id'])->keyBy('id');
        $list = [];
        foreach ($rows as $c) {
            $out = $c->caller_id === $me;
            $missed = ! $out && in_array($c->state, ['missed', 'declined', 'busy'], true) || ($out && $c->state === 'missed');
            if ($this->tab === 'missed' && ! (! $out && $c->state === 'missed')) {
                continue;
            }
            $p = $peers[$c->peerOf($me)] ?? null;
            $list[] = ['id' => $c->id, 'name' => $p?->name ?: 'Unknown', 'handle' => $p?->naara_id, 'out' => $out, 'missed' => ! $out && $c->state === 'missed', 'state' => $c->state,
                'seconds' => $c->billed_seconds ?: ($c->answered_at && $c->ended_at ? $c->answered_at->diffInSeconds($c->ended_at, true) : 0), 'at' => $c->created_at];
        }

        $group = GroupAvailability::enabled();
        $open = $group ? GroupCall::query()->where('state', GroupCall::OPEN)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $me)->whereNull('left_at')->whereNull('banned_at'))->latest('id')->limit(5)->get(['id', 'title', 'host_id']) : collect();

        return view('livewire.n2n.calls', ['list' => $list, 'status' => CallsAvailability::status(), 'group' => $group, 'openRooms' => $open, 'groupCapacity' => N2NSettings::groupMaxCapacity()]);
    }
}

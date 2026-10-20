<?php

namespace App\Livewire\N2N;

use App\Models\User;
use App\Services\N2N\Connections\Connections;
use App\Services\N2N\Profiles\ProfileService;
use App\Services\N2N\Profiles\ProfileVisibility;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A member's local profile (N2N Batch 8, MASTER-ONLY), reached by tapping an avatar in chat or from search. Found by username or Naara ID.
 * Every "you cannot see this" case (no such person, blocked, hidden, inactive) answers the same 404, so the page is not an oracle.
 */
#[Layout('components.layouts.n2n')]
class MemberProfile extends Component
{
    public int $subjectId = 0;

    public array $data = [];

    public function mount(string $handle): void
    {
        $subject = ProfileService::find($handle);
        $data = $subject ? ProfileVisibility::payload(Auth::user(), $subject) : null;
        abort_if($data === null, 404);
        $this->subjectId = $subject->id;
        $this->data = $data;
    }

    public ?string $notice = null;

    public function follow(Connections $graph): void
    {
        $target = User::query()->find($this->subjectId);
        $res = $target ? $graph->follow(Auth::user(), $target) : ['ok' => false, 'message' => 'You cannot follow this account.'];
        $this->notice = $res['ok'] ? (($res['connected'] ?? false) ? 'You are now connected.' : null) : $res['message'];
    }

    public function unfollow(Connections $graph): void
    {
        $target = User::query()->find($this->subjectId);
        if ($target) {
            $graph->unfollow(Auth::user(), $target);
        }
        $this->notice = null;
    }

    public function render(Connections $graph)
    {
        $me = Auth::id();
        $data = ProfileVisibility::payload(Auth::user(), User::query()->findOrFail($this->subjectId));   // re-evaluated every render: a block or unfollow takes effect at once
        abort_if($data === null, 404);

        return view('livewire.n2n.member-profile', [
            'subject' => User::query()->find($this->subjectId), 'isSelf' => $this->subjectId === $me, 'data' => $data,
            'iFollow' => $graph->follows($me, $this->subjectId), 'followsMe' => $graph->follows($this->subjectId, $me), 'connected' => $graph->connected($me, $this->subjectId),
            'counts' => $this->subjectId === $me ? $graph->counts($me) : null,
        ]);
    }
}

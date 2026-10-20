<?php

namespace App\Livewire\N2N;

use App\Models\N2N\Participant;
use App\Services\N2N\Messaging\Followups;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\N2N\Messaging\Planner as PlannerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Naara Planner (N2N, MASTER-ONLY): the member's upcoming events across every chat, plus a private follow-up list. Strictly the signed-in member's own
 * view; a follow-up is never visible to, or announced to, the person it is about.
 */
#[Layout('components.layouts.n2n')]
class Planner extends Component
{
    public string $tab = 'events';

    public string $title = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'followups' ? 'followups' : 'events';
    }

    /** $iso is the picked moment in UTC (the browser converts from the member's own clock); empty = no reminder time. */
    public function add(string $iso = ''): void
    {
        $this->reset('error', 'notice');
        try {
            $due = $iso !== '' ? Carbon::parse($iso)->utc() : null;
            app(Followups::class)->create(Auth::user(), $this->title, $due);
            $this->title = '';
            $this->notice = 'Follow-up saved. Only you can see it.';
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        } catch (\Throwable) {
            $this->error = 'Pick a reminder time from one minute to one year from now.';
        }
    }

    public function toggleDone(int $id): void
    {
        $this->run(fn () => app(Followups::class)->toggleDone(Auth::user(), $id));
    }

    public function toggleStar(int $id): void
    {
        $this->run(fn () => app(Followups::class)->toggleStar(Auth::user(), $id));
    }

    public function remove(int $id): void
    {
        $this->run(fn () => app(Followups::class)->delete(Auth::user(), $id));
    }

    public function snooze(int $id, string $iso): void
    {
        $this->run(fn () => app(Followups::class)->snooze(Auth::user(), $id, Carbon::parse($iso)->utc()));
    }

    private function run(callable $fn): void
    {
        $this->reset('error', 'notice');
        try {
            $fn();
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        } catch (\Throwable) {
            $this->error = 'That time could not be used.';
        }
    }

    public function render()
    {
        $me = Auth::user();
        $events = app(PlannerService::class)->agenda($me, null, 50);
        $followups = app(Followups::class)->list($me);
        // Only chats the member is still in can be linked; a row about a chat they left shows no link.
        $mine = Participant::query()->where('user_id', $me->id)->pluck('conversation_id')->flip();

        return view('livewire.n2n.planner', ['events' => $events, 'followups' => $followups, 'mine' => $mine]);
    }
}

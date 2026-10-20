<?php

namespace App\Livewire\N2N;

use App\Services\N2N\Messaging\MessagingException;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Updates\UpdatesService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Updates tab (N2N Batch 9, MASTER-ONLY): rings for connected Pro brands, a full-screen viewer, reactions and replies. Items are loaded
 * only after the server re-checks visibility, so the browser never holds the media of a brand it may not see.
 */
#[Layout('components.layouts.n2n')]
class Updates extends Component
{
    public ?int $open = null;

    public array $items = [];

    public string $reply = '';

    public string $source = 'auto';

    public ?string $notice = null;

    public ?string $error = null;

    public function view(int $brandId, string $source = 'auto'): void
    {
        $svc = app(UpdatesService::class);
        $this->reset('notice', 'error', 'reply');
        $this->source = in_array($source, ['auto', 'status', 'gallery'], true) ? $source : 'auto';
        $items = $svc->items(Auth::user(), $brandId, $this->source);
        if ($items === null || $items === []) {
            $this->open = null;
            $this->items = [];

            return;
        }
        $this->open = $brandId;
        $this->items = $items;
        $svc->markSeen(Auth::user(), $brandId, $items[count($items) - 1]['id']);
        foreach ($items as $it) {
            if ($it['source'] === 'status') {
                app(\App\Services\N2N\Updates\StatusService::class)->markViewed(Auth::user(), $it['id']);
            }
        }
    }

    public function close(): void
    {
        $this->reset('open', 'items', 'reply', 'notice', 'error');
    }

    public function react(int $itemId, string $emoji, UpdatesService $svc): void
    {
        if ($this->open !== null && $svc->react(Auth::user(), $this->open, $itemId, $emoji)) {
            $this->items = $svc->items(Auth::user(), $this->open, $this->source) ?? [];
        }
    }

    public function reactStatus(int $itemId, string $emoji, UpdatesService $svc): void
    {
        if ($this->open !== null && $svc->reactToStatus(Auth::user(), $this->open, $itemId, $emoji)) {
            $this->items = $svc->items(Auth::user(), $this->open, $this->source) ?? [];
        }
    }

    public function sendReply(int $itemId, UpdatesService $svc): void
    {
        $this->reset('notice', 'error');
        if ($this->open === null) {
            return;
        }
        try {
            $svc->reply(Auth::user(), $this->open, $itemId, $this->reply, 'upd-'.bin2hex(random_bytes(8)), request()->ip(), $this->source);
            $this->notice = 'Reply sent.';
            $this->reply = '';
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        } catch (InsufficientUsageCredit) {
            $this->error = 'You do not have enough credit to send this reply.';
        }
    }

    public function render(UpdatesService $svc)
    {
        $statusSvc = app(\App\Services\N2N\Updates\StatusService::class);

        return view('livewire.n2n.updates', ['rings' => $svc->rings(Auth::user()), 'highlights' => $svc->highlights(Auth::user()), 'reactions' => UpdatesService::REACTIONS, 'canPost' => $statusSvc->canPost(Auth::user())]);
    }
}

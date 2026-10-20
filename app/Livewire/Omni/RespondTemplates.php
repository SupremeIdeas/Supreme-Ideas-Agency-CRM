<?php

namespace App\Livewire\Omni;

use App\Models\ChannelConnection;
use App\Models\WalletGroup;
use App\Services\Inbox\ChannelSendException;
use App\Services\Omni\Meta\WhatsAppTemplates;
use App\Support\Business\WorkspaceAdminAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Naara Respond > WhatsApp templates (MASTER-ONLY). Owner and admins of the active workspace: see every template of a connected WhatsApp number with Meta's status
 * and quality rating, write a new one (checked against Meta's rules before it is sent), and delete one. A template is the only message WhatsApp allows outside the
 * 24-hour window, and Meta must approve it first, so the page says plainly what each status means.
 */
#[Layout('components.layouts.business')]
class RespondTemplates extends Component
{
    public ?int $connectionId = null;

    public string $wabaId = '';

    public string $name = '';

    public string $category = 'UTILITY';

    public string $language = 'en';

    public string $body = '';

    public string $footer = '';

    /** @var list<string> */
    public array $examples = ['', '', '', ''];

    /** @var list<string> */
    public array $buttons = ['', '', ''];

    public ?string $error = null;

    public ?string $ok = null;

    private function ws(): WalletGroup
    {
        $g = WorkspaceAdminAccess::groupFor(Auth::user());
        abort_unless($g !== null, 404);

        return $g;
    }

    /** @return \Illuminate\Support\Collection<int, ChannelConnection> */
    private function numbers()
    {
        return ChannelConnection::query()->where('wallet_group_id', $this->ws()->id)->where('channel', ChannelConnection::WHATSAPP)->orderBy('id')->get();
    }

    private function current(): ?ChannelConnection
    {
        $all = $this->numbers();

        return $all->firstWhere('id', $this->connectionId) ?? $all->first();
    }

    public function choose(int $id): void
    {
        $this->connectionId = $id;
        $this->error = $this->ok = null;
    }

    private function attempt(\Closure $run, string $success): bool
    {
        $this->error = $this->ok = null;
        try {
            $run();
        } catch (ChannelSendException $e) {
            $this->error = $e->getMessage();

            return false;
        }
        $this->ok = $success;

        return true;
    }

    public function saveWaba(WhatsAppTemplates $svc): void
    {
        $c = $this->current();
        abort_unless($c !== null, 404);
        if ($this->attempt(fn () => $svc->saveWabaId($c, $this->wabaId), 'Account saved. Your templates are listed below.')) {
            $this->reset('wabaId');
        }
    }

    public function refresh(WhatsAppTemplates $svc): void
    {
        $c = $this->current();
        abort_unless($c !== null, 404);
        $this->attempt(fn () => $svc->all($c, true), 'Updated from Meta.');
    }

    public function create(WhatsAppTemplates $svc): void
    {
        $c = $this->current();
        abort_unless($c !== null, 404);
        $ok = $this->attempt(fn () => $svc->create($c, $this->name, $this->category, $this->language, $this->body, $this->examples, $this->footer, $this->buttons), 'Sent to Meta for review. It shows as Pending until Meta approves it, usually within minutes to a day.');
        if ($ok) {
            $this->reset('name', 'body', 'footer');
            $this->examples = ['', '', '', ''];
            $this->buttons = ['', '', ''];
        }
    }

    public function remove(string $name, WhatsAppTemplates $svc): void
    {
        $c = $this->current();
        abort_unless($c !== null, 404);
        $this->attempt(fn () => $svc->delete($c, $name), 'Template deleted.');
    }

    public function render(WhatsAppTemplates $svc)
    {
        $c = $this->current();
        $templates = [];
        $needsWaba = false;
        if ($c) {
            if (WhatsAppTemplates::wabaId($c) === null) {
                $needsWaba = true;
            } else {
                try {
                    $templates = $svc->all($c);
                } catch (ChannelSendException $e) {
                    $this->error ??= $e->getMessage();
                }
            }
        }

        return view('livewire.omni.respond-templates', [
            'numbers' => $this->numbers()->map(fn (ChannelConnection $n) => ['id' => $n->id, 'label' => $n->label ?: 'WhatsApp'])->all(),
            'currentId' => $c?->id,
            'needsWaba' => $needsWaba,
            'templates' => $templates,
            'categories' => WhatsAppTemplates::CATEGORIES,
        ]);
    }
}

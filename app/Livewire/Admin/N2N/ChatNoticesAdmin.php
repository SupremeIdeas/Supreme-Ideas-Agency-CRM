<?php

namespace App\Livewire\Admin\N2N;

use App\Models\N2N\ChatNotice;
use App\Services\N2N\Messaging\ChatNotices;
use App\Support\Auditor;
use App\Support\FeatureEntitlements;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > N2N > Chat notices (MASTER-ONLY): write, schedule and switch off the notices shown at the top of Naara chat. */
#[Layout('components.layouts.admin')]
class ChatNoticesAdmin extends Component
{
    public ?int $editing = null;

    public string $title = '';

    public string $body = '';

    public string $tone = 'info';

    public bool $dismissible = true;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) && FeatureEntitlements::isMaster(), 403);
    }

    public function edit(int $id): void
    {
        $n = ChatNotice::query()->findOrFail($id);
        $this->editing = $n->id;
        $this->title = $n->title;
        $this->body = $n->body;
        $this->tone = $n->tone;
        $this->dismissible = $n->dismissible;
        $this->startsAt = $n->starts_at?->format('Y-m-d\TH:i');
        $this->endsAt = $n->ends_at?->format('Y-m-d\TH:i');
    }

    public function resetForm(): void
    {
        $this->reset(['editing', 'title', 'body', 'startsAt', 'endsAt']);
        $this->tone = 'info';
        $this->dismissible = true;
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:80', 'body' => 'required|string|max:280', 'tone' => 'required|in:info,warn',
            'startsAt' => 'nullable|date', 'endsAt' => 'nullable|date|after:startsAt',
        ]);
        $problems = app(ChatNotices::class)->problems($this->title, $this->body);
        if ($problems !== []) {
            $this->addError('body', $problems[0]);

            return;
        }
        $data = ['title' => trim($this->title), 'body' => trim($this->body), 'tone' => $this->tone, 'dismissible' => $this->dismissible,
            'starts_at' => $this->startsAt ?: null, 'ends_at' => $this->endsAt ?: null];
        $n = $this->editing ? tap(ChatNotice::query()->findOrFail($this->editing))->update($data) : ChatNotice::query()->create($data + ['active' => true, 'created_by' => Auth::id()]);
        Auditor::log('n2n.chat_notice.'.($this->editing ? 'updated' : 'created'), ChatNotice::class, $n->id);
        $this->resetForm();
        $this->saved = 'Notice saved.';
    }

    public function toggle(int $id): void
    {
        $n = ChatNotice::query()->findOrFail($id);
        $n->update(['active' => ! $n->active]);
        Auditor::log('n2n.chat_notice.toggled', ChatNotice::class, $n->id, ['active' => $n->active]);
    }

    public function delete(int $id): void
    {
        ChatNotice::query()->whereKey($id)->delete();
        Auditor::log('n2n.chat_notice.deleted', ChatNotice::class, $id);
    }

    public function render()
    {
        $svc = app(ChatNotices::class);
        $rows = ChatNotice::query()->orderByDesc('id')->get()->map(fn ($n) => [$n, $svc->stats($n->id)]);

        return view('livewire.admin.n2n.chat-notices', ['rows' => $rows, 'e2ee' => ChatNotices::e2eeEnabled()]);
    }
}

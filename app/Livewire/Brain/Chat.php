<?php

namespace App\Livewire\Brain;

use App\Jobs\Brain\BrainReplyJob;
use App\Models\Brain\BrainMessage;
use App\Models\Brain\BrainThread;
use App\Services\AI\ClaudeGateway;
use App\Support\Brain\BrainAccess;
use App\Support\Brain\BrainPrompts;
use App\Support\Brain\BrainSettings;
use App\Support\ComingSoon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * NaaraBrain chat (MASTER-ONLY). One sheet (header, empty state, messages and the exact Naara chat composer) that slides sideways to reveal the history
 * sidebar underneath. Every read and write goes through the signed-in person's own threads (BrainThread::ownedBy), never a bare id.
 */
#[Layout('components.layouts.brain')]
class Chat extends Component
{
    #[Url(as: 't', except: '')]
    public ?int $threadId = null;

    public string $q = '';

    public ?string $notice = null;

    public ?string $noticeUrl = null;

    /** The centre line and quick questions for THIS visit; they change each time NaaraBrain is opened or a new chat is started. */
    public string $tagline = '';

    /** @var list<string> */
    public array $chips = [];

    public bool $ideasOpen = false;

    public function mount(): void
    {
        ComingSoon::abortIfOff('brain');
        $this->refreshIdeas();
        if ($this->threadId !== null && $this->mine()->find($this->threadId) === null) {
            $this->threadId = null;
        }
    }

    private function refreshIdeas(): void
    {
        $this->tagline = BrainPrompts::tagline((string) session('brain.last_tagline', ''));
        session(['brain.last_tagline' => $this->tagline]);
        $this->chips = BrainPrompts::chips(4);
    }

    private function mine()
    {
        return BrainThread::query()->ownedBy((int) auth()->id());
    }

    private function current(): ?BrainThread
    {
        return $this->threadId === null ? null : $this->mine()->find($this->threadId);
    }

    public function newChat(): void
    {
        $this->threadId = null;
        $this->notice = null;
        $this->noticeUrl = null;
        $this->ideasOpen = false;
        $this->refreshIdeas();
    }

    public function toggleIdeas(): void
    {
        $this->ideasOpen = ! $this->ideasOpen;
    }

    /** Ask one of the suggested questions: closes the idea browser and sends it like typed text. */
    public function ask(string $text): bool
    {
        $this->ideasOpen = false;

        return $this->send($text);
    }

    private function deny(string $message): bool
    {
        $this->notice = $message;
        $this->noticeUrl = BrainAccess::upgradeUrl();

        return false;
    }

    public function open(int $id): void
    {
        $this->threadId = $this->mine()->whereKey($id)->exists() ? $id : null;
        $this->notice = null;
    }

    /** The composer's text, delivered by its cc:transmit event. */
    public function send(string $text): bool
    {
        $this->notice = null;
        $this->noticeUrl = null;
        $text = trim($text);
        if ($text === '') {
            return false;
        }
        if (mb_strlen($text) > BrainSettings::int('max_chars')) {
            $this->notice = __('brain.too_long', ['max' => BrainSettings::int('max_chars')]);

            return false;
        }
        if (! app(ClaudeGateway::class)->enabled()) {
            $this->notice = __('brain.not_connected');

            return false;
        }
        $user = auth()->user();
        // Access follows Naara Pro: free members only while the admin allows it, at the free ceiling; a plan raises it (and unlocks deeper models).
        if (! BrainAccess::allowed($user)) {
            return $this->deny(__('brain.pro_only'));
        }
        $limit = BrainAccess::dailyLimit($user);
        if ($limit !== null && BrainAccess::usedToday($user) >= $limit) {
            return $this->deny(BrainAccess::tier($user) === BrainAccess::TIER_FREE ? __('brain.daily_limit_free', ['n' => $limit]) : __('brain.daily_limit', ['n' => $limit]));
        }
        $uid = (int) $user->id;
        // One answer at a time per conversation: a second question while one is pending would double the cost for nothing.
        $thread = $this->current();
        if ($thread !== null && $thread->messages()->where('status', 'pending')->exists()) {
            $this->notice = __('brain.wait');

            return false;
        }
        if ($thread === null) {
            $thread = BrainThread::create(['user_id' => $uid, 'title' => mb_substr(preg_replace('/\s+/', ' ', $text), 0, 60), 'depth' => BrainAccess::effectiveDepth($user, BrainSettings::depth(null)), 'length' => 'normal']);
            $this->threadId = $thread->id;
        }
        BrainMessage::create(['thread_id' => $thread->id, 'user_id' => $uid, 'role' => 'user', 'body' => $text, 'status' => 'done']);
        $pending = BrainMessage::create(['thread_id' => $thread->id, 'user_id' => $uid, 'role' => 'assistant', 'body' => '', 'status' => 'pending']);
        $thread->forceFill(['last_message_at' => now()])->save();
        BrainReplyJob::dispatch($pending->id);

        return true;
    }

    public function setDepth(string $depth): void
    {
        $user = auth()->user();
        $want = BrainSettings::depth($depth);
        if (! in_array($want, BrainAccess::depths($user), true)) {
            $this->deny(__('brain.depth_locked'));

            return;
        }
        $this->current()?->update(['depth' => $want]);
    }

    public function setLength(string $length): void
    {
        $this->current()?->update(['length' => in_array($length, ['concise', 'normal', 'detailed'], true) ? $length : 'normal']);
    }

    public function togglePin(int $id): void
    {
        $t = $this->mine()->find($id);
        $t?->update(['pinned' => ! $t->pinned]);
    }

    public function archive(int $id): void
    {
        $this->mine()->whereKey($id)->update(['archived_at' => now()]);
        if ($this->threadId === $id) {
            $this->threadId = null;
        }
    }

    public function delete(int $id): void
    {
        $this->mine()->whereKey($id)->delete();
        if ($this->threadId === $id) {
            $this->threadId = null;
        }
    }

    /** History groups for the sidebar: Pinned, Today, Yesterday, Previous 7 days, then one group per month. */
    private function groups(): array
    {
        $q = trim($this->q);
        $threads = $this->mine()->whereNull('archived_at')
            ->when($q !== '', function ($b) use ($q) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';
                $b->where(fn ($w) => $w->where('title', 'like', $like)->orWhereHas('messages', fn ($m) => $m->where('status', 'done')->where('body', 'like', $like)));
            })
            ->orderByDesc('pinned')->orderByDesc('last_message_at')->orderByDesc('id')->limit(200)->get();

        $out = [];
        $now = now();
        foreach ($threads as $t) {
            $at = $t->last_message_at ?? $t->created_at;
            $label = match (true) {
                $t->pinned => __('brain.g_pinned'),
                $at->isSameDay($now) => __('brain.g_today'),
                $at->isSameDay($now->copy()->subDay()) => __('brain.g_yesterday'),
                $at->gte($now->copy()->subDays(7)->startOfDay()) => __('brain.g_week'),
                default => Carbon::parse($at)->locale(app()->getLocale())->translatedFormat('F Y'),
            };
            $out[$label][] = $t;
        }

        return $out;
    }

    /** @return array<string, mixed> what the sidebar shows about this member's access */
    private function access(): array
    {
        $u = auth()->user();

        return [
            'tier' => BrainAccess::tier($u), 'label' => BrainAccess::label($u), 'limit' => BrainAccess::dailyLimit($u), 'used' => BrainAccess::usedToday($u),
            'depths' => BrainAccess::depths($u), 'upgrade' => BrainAccess::tier($u) === BrainAccess::TIER_FREE ? BrainAccess::upgradeUrl() : null,
        ];
    }

    public function render()
    {
        $thread = $this->current();
        $messages = $thread ? $thread->messages()->get() : collect();

        return view('livewire.brain.chat', [
            'thread' => $thread,
            'messages' => $messages,
            'busy' => $messages->contains('status', 'pending'),
            'groups' => $this->groups(),
            'depths' => BrainSettings::depths(),
            'access' => $this->access(),
            'ideas' => $this->ideasOpen ? BrainPrompts::ideas() : [],
            'capabilities' => BrainPrompts::capabilities(),
        ]);
    }
}

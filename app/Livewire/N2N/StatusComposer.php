<?php

namespace App\Livewire\N2N;

use App\Models\N2N\StatusPost;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\N2N\Updates\StatusService;
use App\Support\N2N\N2NStatusSettings as S;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Add a status (Naara Chat Updates tab, MASTER-ONLY): Text with colour and font, Photo, Video, Voice. Pro members only; every rule is re-checked in StatusService. */
class StatusComposer extends Component
{
    use WithFileUploads;

    public const PALETTE = ['#0A6E6E', '#0D1B2A', '#D4A017', '#B8A91F', '#8BA32D', '#2E9E5B', '#1F8FB8', '#2F5BD9', '#6B4CE6', '#B33FB3', '#E0457B', '#E8663D', '#7A4B2A', '#475569'];

    public string $text = '';

    public string $bg = '#0A6E6E';

    public string $font = 'sans';

    public $file = null;

    public string $mode = 'text';

    public ?int $seconds = null;

    public string $caption = '';

    /** @var list<int> people tagged in the post being written (re-checked on the server) */
    public array $mentionIds = [];

    public ?string $error = null;

    public ?int $viewersFor = null;

    /** @var list<int> */
    public array $hidden = [];

    public bool $audienceOpen = false;

    public function mount(): void
    {
        abort_unless(app(StatusService::class)->canPost(Auth::user()), 404);
        $this->hidden = app(StatusService::class)->hiddenFrom(Auth::user());
    }

    public function postText(StatusService $svc): void
    {
        $this->error = null;
        try {
            $svc->postText(Auth::user(), $this->text, $this->bg, $this->font, array_map('intval', $this->mentionIds));
            $this->reset('text', 'mentionIds');
            $this->done();
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function postFile(StatusService $svc): void
    {
        $this->error = null;
        $this->validate(['file' => ['required', 'file', 'max:'.(int) ceil(S::maxBytes('video') / 1024)]]);
        try {
            $svc->postMedia(Auth::user(), $this->file, in_array($this->mode, ['image', 'video', 'voice'], true) ? $this->mode : null, ($this->caption !== '' ? $this->caption : null), $this->seconds, array_map('intval', $this->mentionIds));
            $this->done();
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        } finally {
            try {
                $this->file?->delete();   // the temporary upload never lingers on the server's disk
            } catch (\Throwable) {
            }
            $this->reset('file', 'seconds', 'caption', 'mentionIds');
        }
    }

    public function remove(int $id, StatusService $svc): void
    {
        $svc->delete(Auth::user(), $id);
        $this->dispatch('nx-toast', type: 'success', message: 'Status deleted.');
    }

    public function toggleViewers(int $id): void
    {
        $this->viewersFor = $this->viewersFor === $id ? null : $id;
    }

    public function saveAudience(StatusService $svc): void
    {
        $svc->setHiddenFrom(Auth::user(), array_map('intval', $this->hidden));
        $this->hidden = $svc->hiddenFrom(Auth::user());
        $this->audienceOpen = false;
        $this->dispatch('nx-toast', type: 'success', message: count($this->hidden) === 0 ? 'Everyone you are connected with can see your status.' : 'Saved. '.count($this->hidden).' member(s) will not see your status.');
    }

    private function done(): void
    {
        $this->dispatch('nx-toast', type: 'success', message: 'Status posted. It disappears after 24 hours.');
        $this->dispatch('status-posted');
    }

    public function render(StatusService $svc)
    {
        $limit = $svc->limitFor(Auth::user());

        return view('livewire.n2n.status-composer', [
            'mine' => StatusPost::query()->active()->where('user_id', Auth::id())->orderByDesc('id')->get(),
            'limit' => $limit, 'used' => $svc->usedToday(Auth::user()), 'kinds' => S::allowedKinds(),
            'viewers' => $this->viewersFor ? $svc->viewers(Auth::user(), $this->viewersFor) : [], 'audience' => $this->audienceOpen ? $svc->audience(Auth::user()) : [], 'views' => \App\Models\N2N\StatusView::query()->whereIn('status_id', \App\Models\N2N\StatusPost::query()->active()->where('user_id', Auth::id())->pluck('id'))->selectRaw('status_id, count(*) n')->groupBy('status_id')->pluck('n', 'status_id'), 'palette' => self::PALETTE, 'mentionsOn' => S::mentionsEnabled(), 'cameraOn' => S::cameraEnabled(),
            'mentionables' => S::mentionsEnabled() ? collect($svc->audience(Auth::user()))->except($svc->hiddenFrom(Auth::user()))->map(fn ($n, $id) => ['id' => (int) $id, 'name' => (string) $n])->values()->all() : [],
             'fonts' => StatusService::FONTS, 'maxChars' => S::TEXT_MAX,
        ]);
    }
}

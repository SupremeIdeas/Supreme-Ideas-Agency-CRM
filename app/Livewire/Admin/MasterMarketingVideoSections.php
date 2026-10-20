<?php

namespace App\Livewire\Admin;

use App\Models\MasterMarketingVideoSection;
use App\Support\Auditor;
use App\Support\MasterMarketing\VideoSlots;
use App\Support\MasterMarketing\VideoSource;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** MASTER-ONLY. Admin > Marketing video sections: add, hide, reorder and schedule videos in the named slots of master marketing pages. */
#[Layout('components.layouts.admin')]
class MasterMarketingVideoSections extends Component
{
    use WithFileUploads;

    public string $slot = 'home.intro';

    public string $title = '';

    public string $description = '';

    public string $source = 'url';

    public string $url = '';

    /** Set by the resumable uploader (chunked, resumes after a dropped connection) once the file is stored. */
    public string $fileUrl = '';

    public string $posterUrl = '';

    public int $uploaderKey = 0;

    /** Which way it was filmed: full screen opens the same way round. */
    public string $orientation = 'landscape';

    /** Self-hosted files: no download, no right-click, no picture-in-picture (the vault does the rest). */
    public bool $protect = true;

    /** For the slot choices "Blog post" and "User guide": which one. */
    public string $targetId = '';

    public string $label = '';

    public string $duration = '';

    public $poster = null;

    public bool $autoplay = false;

    public bool $loop = false;

    public ?string $starts_at = null;

    public ?string $ends_at = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
    }

    public function add(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $rules = [
            'slot' => ['required', 'in:'.implode(',', array_merge(array_keys(VideoSlots::SLOTS), ['@blog', '@guide']))],
            'orientation' => ['required', 'in:landscape,portrait'],
            'title' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:300'],
            'label' => ['nullable', 'string', 'max:40'],
            'duration' => ['nullable', 'regex:/^\d{1,2}(:\d{2}){1,2}$/'],
            'source' => ['required', 'in:url,upload'],
            'poster' => ['nullable', ...MediaStorage::uploadRules()],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
        $rules += $this->source === 'url'
            ? ['url' => ['required', 'string', 'max:500']]
            : ['fileUrl' => ['required', 'string', 'max:500']];
        $this->validate($rules);
        $slot = $this->slot;
        if ($slot === '@blog') {
            $slot = 'blog:'.(int) $this->targetId;
            if (! \App\Models\Post::whereKey((int) $this->targetId)->exists()) {
                $this->addError('targetId', 'Choose the blog post.');

                return;
            }
        } elseif ($slot === '@guide') {
            $slot = 'guide:'.$this->targetId;
            if (! in_array($this->targetId, \App\Support\UserGuides::AUDIENCES, true)) {
                $this->addError('targetId', 'Choose the guide.');

                return;
            }
        }

        if ($this->source === 'url') {
            $link = trim($this->url);
            if (VideoSource::parseLink($link) === null) {
                $this->addError('url', 'Paste a YouTube or Vimeo link, or a direct https link to an .mp4 / .webm file.');

                return;
            }
        } else {
            $link = trim($this->fileUrl);
        }

        $row = MasterMarketingVideoSection::create([
            'slot' => $slot, 'orientation' => $this->orientation, 'protected' => $this->protect, 'title' => trim($this->title), 'description' => trim($this->description) ?: null,
            'source_type' => $this->source, 'video_url' => $link,
            'poster_url' => $this->poster ? MediaStorage::storePublic($this->poster, 'marketing-video') : (trim($this->posterUrl) ?: null),
            'label' => trim($this->label) ?: null, 'duration_label' => trim($this->duration) ?: null,
            'autoplay' => $this->autoplay, 'loop' => $this->loop, 'is_visible' => true,
            'position' => (int) MasterMarketingVideoSection::where('slot', $this->slot)->max('position') + 1,
            'starts_at' => $this->starts_at ?: null, 'ends_at' => $this->ends_at ?: null, 'created_by' => Auth::id(),
        ]);
        // the wave for the progress bar: real loudness for an uploaded file, a steady decorative wave otherwise
        $row->update(['wave' => $this->source === 'upload' ? \App\Support\MasterMarketing\VideoWave::forRef($link, (string) $row->id) : \App\Support\MasterMarketing\VideoWave::synthetic($link)]);
        $this->reset(['title', 'description', 'url', 'fileUrl', 'posterUrl', 'poster', 'label', 'duration', 'orientation', 'autoplay', 'loop', 'starts_at', 'ends_at']);
        $this->uploaderKey++;
        Auditor::log('mm_video.added', null, null, ['slot' => $slot]);
        $this->dispatch('nx-toast', type: 'success', message: 'Video added.');
    }

    /** Move videos uploaded before the vault existed out of public storage. */
    public function lockExisting(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $n = \App\Support\MasterMarketing\VideoVault::lockExisting();
        VideoSlots::flush();
        Auditor::log('mm_video.locked_existing', null, null, ['moved' => $n]);
        $this->dispatch('nx-toast', type: 'success', message: $n ? $n.' video(s) moved into the private vault.' : 'Nothing to move: every uploaded video is already private.');
    }

    public function toggle(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $v = MasterMarketingVideoSection::find($id);
        $v?->update(['is_visible' => ! $v->is_visible]);   // the model's saved hook busts the page cache immediately
    }

    public function move(int $id, string $dir): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $v = MasterMarketingVideoSection::find($id);
        if (! $v) {
            return;
        }
        $rows = MasterMarketingVideoSection::where('slot', $v->slot)->orderBy('position')->orderBy('id')->get()->values();
        $i = $rows->search(fn ($r) => $r->id === $v->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j < 0 || $j >= $rows->count()) {
            return;
        }
        $order = $rows->pluck('id')->all();
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        foreach ($order as $pos => $rid) {
            MasterMarketingVideoSection::where('id', $rid)->update(['position' => $pos]);
        }
        VideoSlots::flush();
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        MasterMarketingVideoSection::find($id)?->delete();   // model delete so the page cache is busted
        Auditor::log('mm_video.deleted', MasterMarketingVideoSection::class, $id);
        $this->dispatch('nx-toast', type: 'success', message: 'Video removed.');
    }

    public function render()
    {
        return view('livewire.admin.master-marketing-video-sections', [
            'rows' => MasterMarketingVideoSection::orderBy('slot')->orderBy('position')->orderBy('id')->get()->groupBy('slot'),
            'slots' => VideoSlots::SLOTS,
            'posts' => \App\Models\Post::orderByDesc('id')->limit(200)->get(['id', 'title']),
            'guides' => \App\Support\UserGuides::AUDIENCES,
        ]);
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\Business\WorkspaceHero;
use App\Support\FeatureEntitlements;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Admin > Business Suite > Workspace page (MASTER-ONLY): the title, up to four rotating words, the description and the picture at the top of the workspace page. */
#[Layout('components.layouts.admin')]
class BusinessWorkspacePage extends Component
{
    use WithFileUploads;

    public string $title = '';

    /** @var array<int, string> */
    public array $words = ['', '', '', ''];

    public string $description = '';

    public $image = null;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) && FeatureEntitlements::isMaster(), 403);
        $this->load();
    }

    private function load(): void
    {
        $this->title = WorkspaceHero::title();
        $w = WorkspaceHero::words();
        $this->words = array_pad($w, WorkspaceHero::MAX_WORDS, '');
        $this->description = WorkspaceHero::description();
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:40', 'description' => 'required|string|max:320',
            'words' => 'array|max:'.WorkspaceHero::MAX_WORDS, 'words.*' => 'nullable|string|max:24',
        ]);
        $words = array_values(array_filter(array_map(fn ($w) => WorkspaceHero::clean((string) $w, 24), $this->words)));
        if ($words === []) {
            $this->addError('words.0', 'Keep at least one rotating word.');

            return;
        }
        $this->put('title', WorkspaceHero::clean($this->title, 40));
        $this->put('description', WorkspaceHero::clean($this->description, 320));
        for ($i = 1; $i <= WorkspaceHero::MAX_WORDS; $i++) {
            $this->put('word_'.$i, $words[$i - 1] ?? '');
        }
        Auditor::log('business.workspace_page.updated', Setting::class, null, ['by' => Auth::id()]);
        $this->load();
        $this->saved = 'Saved. The workspace page now shows your wording.';
    }

    public function uploadImage(): void
    {
        $this->validate(['image' => 'required|image|mimes:png,jpg,jpeg,webp|max:3072|dimensions:min_width=600']);
        $this->put('image', MediaStorage::storePublic($this->image, 'business'));
        $this->image = null;
        Auditor::log('business.workspace_page.image_changed', Setting::class, null, ['by' => Auth::id()]);
        $this->saved = 'Picture updated.';
    }

    public function resetImage(): void
    {
        $this->put('image', '');
        Auditor::log('business.workspace_page.image_reset', Setting::class, null, ['by' => Auth::id()]);
        $this->saved = 'Picture reset to the shipped illustration.';
    }

    public function resetAll(): void
    {
        foreach (['title' => WorkspaceHero::DEFAULT_TITLE, 'description' => WorkspaceHero::DEFAULT_DESCRIPTION] as $k => $v) {
            $this->put($k, $v);
        }
        foreach (WorkspaceHero::DEFAULT_WORDS as $i => $w) {
            $this->put('word_'.($i + 1), $w);
        }
        $this->load();
        Auditor::log('business.workspace_page.reset', Setting::class, null, ['by' => Auth::id()]);
        $this->saved = 'Wording reset to the defaults.';
    }

    private function put(string $key, string $value): void
    {
        Setting::setValue(WorkspaceHero::KEY.$key, $value, WorkspaceHero::GROUP, 'Business workspace page: '.$key);
    }

    public function render()
    {
        return view('livewire.admin.business-workspace-page', ['hero' => WorkspaceHero::image(), 'custom' => WorkspaceHero::customImage() !== null]);
    }
}

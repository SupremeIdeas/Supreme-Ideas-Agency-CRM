<?php

namespace App\Livewire\Admin;

use App\Models\GifCategory;
use App\Models\GifLibraryItem;
use App\Services\Gifs\GifLibrary as Library;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin > GIF library: the shelves of GIFs and stickers every chat composer offers. Create your own categories (and choose which chats show each one), add from files,
 * from any web address or by making a GIF from a video right here (the same GIF Studio members use in chat), then rename, move, hide or remove.
 * Files are checked to be real GIFs of a sane size; web addresses go through the SSRF guard (see Library). Changes are written to the audit log.
 */
#[Layout('components.layouts.admin')]
class GifLibrary extends Component
{
    use WithFileUploads;

    #[Url(as: 'cat')]
    public string $category = 'stickers';

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    public string $url = '';

    public string $name = '';

    public ?string $notice = null;

    /** The GIF made in the studio, uploaded by the browser (see <naara-gif-maker>). */
    public $made = null;

    // New category form
    public string $newName = '';

    /** @var list<string> */
    public array $newSurfaces = [];

    // Editing one category
    public ?int $editing = null;

    public string $editName = '';

    /** @var list<string> */
    public array $editSurfaces = [];

    public bool $editActive = true;

    public ?int $deleting = null;

    public string $moveTo = '';

    /** @var list<string> */
    public array $problems = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->newSurfaces = array_keys((array) config('gif_library.surfaces'));
        $this->category = $this->validCategory($this->category);
    }

    private function validCategory(string $c): string
    {
        $slugs = app(Library::class)->slugs();

        return in_array($c, $slugs, true) ? $c : ($slugs[0] ?? 'stickers');
    }

    private function label(string $slug): string
    {
        return GifCategory::where('slug', $slug)->first()?->label() ?? $slug;
    }

    public function setCategory(string $c): void
    {
        $this->category = $this->validCategory($c);
        $this->notice = null;
        $this->problems = [];
    }

    // ── categories ──────────────────────────────────────────────────────────

    public function createCategory(Library $lib): void
    {
        $this->notice = null;
        $this->problems = [];
        try {
            $c = $lib->createCategory($this->newName, $this->newSurfaces);
            Auditor::log('gif_library.category_created', 'GifCategory', $c->id, ['name' => $c->name, 'surfaces' => $c->surfaces]);
            $this->category = $c->slug;
            $this->notice = '“'.$c->name.'” created. Add stickers to it below; it shows in chat as soon as it has one.';
            $this->reset('newName');
            $this->newSurfaces = array_keys((array) config('gif_library.surfaces'));
        } catch (\InvalidArgumentException $e) {
            $this->problems[] = $e->getMessage();
        }
    }

    public function editCategory(int $id): void
    {
        $c = GifCategory::find($id);
        if (! $c) {
            return;
        }
        $this->editing = $c->id;
        $this->editName = $c->name;
        $this->editActive = $c->is_active;
        $this->editSurfaces = $c->surfaces ?: array_keys((array) config('gif_library.surfaces'));
        $this->deleting = null;
    }

    public function saveCategory(Library $lib): void
    {
        $this->problems = [];
        $c = $this->editing ? GifCategory::find($this->editing) : null;
        if (! $c) {
            return;
        }
        try {
            $lib->updateCategory($c, $this->editName, $this->editSurfaces, $this->editActive);
            Auditor::log('gif_library.category_updated', 'GifCategory', $c->id, ['name' => $c->name, 'surfaces' => $c->surfaces, 'active' => $c->is_active]);
            $this->notice = '“'.$c->name.'” saved.';
            $this->editing = null;
        } catch (\InvalidArgumentException $e) {
            $this->problems[] = $e->getMessage();
        }
    }

    public function moveCategory(int $id, string $dir, Library $lib): void
    {
        if ($c = GifCategory::find($id)) {
            $lib->moveCategory($c, $dir === 'up' ? -1 : 1);
        }
    }

    public function askDelete(int $id): void
    {
        $this->deleting = $id;
        $this->editing = null;
        $this->moveTo = '';
    }

    public function deleteCategory(Library $lib): void
    {
        $this->problems = [];
        $c = $this->deleting ? GifCategory::find($this->deleting) : null;
        if (! $c) {
            return;
        }
        try {
            $n = $lib->deleteCategory($c, $this->moveTo !== '' ? $this->moveTo : null);
            Auditor::log('gif_library.category_deleted', 'GifCategory', $c->id, ['name' => $c->name, 'items' => $n, 'moved_to' => $this->moveTo ?: null]);
            $this->notice = '“'.$c->name.'” deleted'.($n ? ($this->moveTo !== '' ? ' and its '.$n.' GIF(s) moved.' : ' with its '.$n.' GIF(s).') : '.');
            $this->deleting = null;
            $this->category = $this->validCategory($this->category);
        } catch (\InvalidArgumentException $e) {
            $this->problems[] = $e->getMessage();
        }
    }

    // ── made in the studio ──────────────────────────────────────────────────

    public function saveMade(string $name, Library $lib): void
    {
        $this->notice = null;
        $this->problems = [];
        $this->validate(['made' => ['required', 'file', 'max:3072']], ['made.max' => 'That GIF is over 3 MB. Lower the width or frame rate in the studio and build it again.']);
        try {
            $item = $lib->addFromUpload($this->made, $this->category, trim($name) !== '' ? $name : 'Made in the studio');
            Auditor::log('gif_library.added', 'GifLibraryItem', $item->id, ['category' => $this->category, 'source' => 'studio']);
            $this->notice = '“'.$item->name.'” saved to '.$this->label($this->category).'. It is live in chat now.';
        } catch (\InvalidArgumentException $e) {
            $this->problems[] = $e->getMessage();
        }
        $this->reset('made');
    }

    public function addFiles(Library $lib): void
    {
        $this->notice = null;
        $this->problems = [];
        $this->validate(['files.*' => ['file', 'max:3072']], ['files.*.max' => 'Each GIF must be 3 MB or smaller.']);
        $n = 0;
        foreach ($this->files as $f) {
            try {
                $item = $lib->addFromUpload($f, $this->category, count($this->files) === 1 ? $this->name : null);
                Auditor::log('gif_library.added', 'GifLibraryItem', $item->id, ['category' => $this->category, 'source' => 'upload']);
                $n++;
            } catch (\InvalidArgumentException $e) {
                $this->problems[] = $e->getMessage();
            }
        }
        $this->reset('files', 'name');
        $this->notice = $n ? $n.' GIF'.($n === 1 ? '' : 's').' added to '.$this->label($this->category).'.' : null;
    }

    public function addUrl(Library $lib): void
    {
        $this->notice = null;
        $this->problems = [];
        try {
            $item = $lib->addFromUrl($this->url, $this->category, $this->name ?: null);
            Auditor::log('gif_library.added', 'GifLibraryItem', $item->id, ['category' => $this->category, 'source' => 'url', 'url' => $item->source_url]);
            $this->notice = '“'.$item->name.'” added to '.$this->label($this->category).'.';
            $this->reset('url', 'name');
        } catch (\InvalidArgumentException $e) {
            $this->problems[] = $e->getMessage();
        }
    }

    public function rename(int $id, string $name): void
    {
        $name = trim(mb_substr($name, 0, 120));
        if ($name !== '') {
            GifLibraryItem::whereKey($id)->update(['name' => $name]);
        }
    }

    public function toggle(int $id): void
    {
        $i = GifLibraryItem::find($id);
        $i?->forceFill(['is_active' => ! $i->is_active])->save();
    }

    public function move(int $id, string $to): void
    {
        $i = GifLibraryItem::find($id);
        if ($i && in_array($to, app(Library::class)->slugs(), true)) {
            $i->forceFill(['category' => $to])->save();
        }
    }

    public function remove(int $id): void
    {
        $i = GifLibraryItem::find($id);
        if ($i) {
            Auditor::log('gif_library.removed', 'GifLibraryItem', $i->id, ['category' => $i->category, 'name' => $i->name]);
            $i->delete();
        }
    }

    public function resync(Library $lib): void
    {
        $this->notice = $lib->syncSeeds().' shipped GIF(s) registered.';
    }

    public function render(Library $lib)
    {
        return view('livewire.admin.gif-library', [
            'categories' => $lib->all(),
            'surfaces' => (array) config('gif_library.surfaces'),
            'maxBytes' => (int) config('gif_library.max_bytes'),
            'makerLabels' => array_merge((array) __('composer'), ['saveGif' => 'Save to library', 'nameGif' => 'Name it, e.g. goal celebration']),
            'counts' => GifLibraryItem::query()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category'),
            'items' => GifLibraryItem::query()->where('category', $this->category)->orderBy('sort')->orderBy('id')->limit(400)->get(),
            'searchOn' => (string) config('services.giphy.api_key') !== '' || (string) config('services.tenor.api_key') !== '',
        ]);
    }
}

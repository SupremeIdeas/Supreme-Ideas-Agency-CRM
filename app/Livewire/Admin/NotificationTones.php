<?php

namespace App\Livewire\Admin;

use App\Models\NotificationTone;
use App\Support\Auditor;
use App\Support\NotificationTones as Tones;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin -> Sounds & ringtones. The tone library behind every member's sound settings: upload any number of tones per category,
 * mark ONE default per (category, slot), rename, preview and delete. Members pick from exactly what is here.
 * super_admin/admin only, re-authorized on every request.
 */
#[Layout('components.layouts.admin')]
class NotificationTones extends Component
{
    use WithFileUploads;

    public string $category = 'message';

    public string $slot = 'general';

    public string $label = '';

    public string $credit = '';

    public $file = null;

    public ?string $error = null;

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function updatedCategory(): void
    {
        $this->slot = array_key_first(Tones::CATEGORIES[$this->category]['slots'] ?? ['default' => 1]) ?? 'default';
    }

    public function upload(): void
    {
        $this->error = null;
        $this->saved = null;
        $this->validate(['file' => ['required', 'file', 'max:'.Tones::maxKb($this->category)], 'label' => ['required', 'string', 'max:80'], 'credit' => ['nullable', 'string', 'max:160']]);
        try {
            $tone = Tones::upload($this->file, $this->category, $this->slot, $this->label, Auth::id(), false, $this->credit ?: null);
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        Auditor::log('tone.uploaded', null, null, ['id' => $tone->id, 'category' => $tone->category, 'slot' => $tone->slot]);
        $this->reset('file', 'label', 'credit');
        $this->saved = 'Added "'.$tone->label.'".';
    }

    public function makeDefault(int $id): void
    {
        $tone = NotificationTone::findOrFail($id);
        Tones::setDefault($tone);
        Auditor::log('tone.default_set', null, null, ['id' => $tone->id, 'category' => $tone->category, 'slot' => $tone->slot]);
        $this->saved = '"'.$tone->label.'" is now the default.';
    }

    public function rename(int $id, string $label): void
    {
        Tones::rename(NotificationTone::findOrFail($id), $label);
    }

    public function remove(int $id): void
    {
        $tone = NotificationTone::findOrFail($id);
        $label = $tone->label;
        Tones::delete($tone);
        Auditor::log('tone.deleted', null, null, ['id' => $id]);
        $this->saved = 'Removed "'.$label.'".';
    }

    public function render()
    {
        $tones = NotificationTone::orderBy('category')->orderBy('slot')->orderByDesc('is_default')->orderBy('label')->get()->groupBy(fn ($t) => $t->category.'.'.$t->slot);

        return view('livewire.admin.notification-tones', ['categories' => Tones::CATEGORIES, 'tones' => $tones, 'maxKb' => Tones::maxKb($this->category)]);
    }
}

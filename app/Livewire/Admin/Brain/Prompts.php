<?php

namespace App\Livewire\Admin\Brain;

use App\Models\Brain\BrainPrompt;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Admin > NaaraBrain > Ideas library (MASTER-ONLY): the rotating centre lines, the quick questions and the capability list members see. Add, edit, switch off or
 * delete a line; mark an idea "works today" or "Soon". Seeded from the skills catalogue; re-seeding never overwrites what is edited here.
 */
class Prompts extends Component
{
    public string $kind = BrainPrompt::TAGLINE;

    public string $pack = '';

    public string $newText = '';

    public string $newDetail = '';

    public string $newPack = 'general';

    public bool $newLive = true;

    public ?int $editId = null;

    public string $editText = '';

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function setKind(string $k): void
    {
        $this->kind = in_array($k, [BrainPrompt::TAGLINE, BrainPrompt::SUGGESTION, BrainPrompt::CAPABILITY], true) ? $k : BrainPrompt::TAGLINE;
        $this->pack = '';
        $this->editId = null;
    }

    public function toggle(int $id, string $field): void
    {
        if (! in_array($field, ['active', 'live'], true)) {
            return;
        }
        $p = BrainPrompt::query()->find($id);
        $p?->update([$field => ! $p->{$field}]);
    }

    public function startEdit(int $id): void
    {
        $this->editId = $id;
        $this->editText = (string) BrainPrompt::query()->whereKey($id)->value('text');
    }

    public function saveEdit(): void
    {
        $this->validate(['editText' => 'required|string|max:240']);
        BrainPrompt::query()->whereKey($this->editId)->update(['text' => trim($this->editText)]);
        $this->editId = null;
    }

    public function delete(int $id): void
    {
        BrainPrompt::query()->whereKey($id)->delete();
        Auditor::log('brain.prompt_deleted', 'BrainPrompt', $id, []);
    }

    public function add(): void
    {
        $this->validate([
            'newText' => 'required|string|max:240', 'newDetail' => 'nullable|string|max:280',
            'newPack' => 'required|in:general,'.implode(',', array_keys(BrainPrompt::PACKS)),
        ]);
        $text = trim($this->newText);
        BrainPrompt::query()->firstOrCreate(
            ['key' => $this->kind.':'.substr(md5($this->kind.$text), 0, 12)],
            ['kind' => $this->kind, 'pack' => $this->kind === BrainPrompt::TAGLINE ? 'general' : $this->newPack, 'text' => $text, 'detail' => $this->kind === BrainPrompt::CAPABILITY ? (trim($this->newDetail) ?: null) : null,
                'live' => $this->kind === BrainPrompt::TAGLINE ? true : $this->newLive, 'active' => true, 'sort' => ((int) BrainPrompt::query()->where('kind', $this->kind)->max('sort')) + 1],
        );
        Auditor::log('brain.prompt_added', 'BrainPrompt', 0, ['kind' => $this->kind]);
        $this->reset(['newText', 'newDetail']);
    }

    public function render()
    {
        $q = BrainPrompt::query()->where('kind', $this->kind)->when($this->pack !== '', fn ($b) => $b->where('pack', $this->pack))->orderBy('pack')->orderBy('sort');

        return view('livewire.admin.brain.prompts', [
            'rows' => $q->limit(300)->get(),
            'counts' => BrainPrompt::query()->selectRaw('kind, count(*) as n, sum(case when active then 1 else 0 end) as on_n')->groupBy('kind')->get()->keyBy('kind'),
            'packs' => BrainPrompt::PACKS,
        ]);
    }
}

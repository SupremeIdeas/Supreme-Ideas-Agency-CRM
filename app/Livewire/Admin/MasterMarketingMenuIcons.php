<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\IconOverrides;
use App\Support\MasterMarketing\MenuIcons;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * MASTER-ONLY. Admin > Menu icons: one row per menu slot (customer More sheet, marketing mega menu, admin menus) showing the icon in use, whether it is the
 * bundled default or an admin override, with upload / remove. Writes through IconOverrides under the slot's own key (dots turned into dashes), which
 * MenuIcons::resolve() already reads first, so there is no second storage mechanism.
 */
#[Layout('components.layouts.admin')]
class MasterMarketingMenuIcons extends Component
{
    use WithFileUploads;

    public const SURFACES = [
        'app' => 'Customer menu',
        'marketing' => 'Marketing menu',
        'admin' => 'Admin menu',
    ];

    #[Url(as: 'set')]
    public string $surface = 'app';

    #[Url(as: 'q')]
    public string $search = '';

    public string $target = '';

    public $upload = null;

    /** The icon pack choice: 'follow-theme' or a pack slug (see IconPacks). */
    public string $pack = 'follow-theme';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->pack = \App\Support\IconPacks\IconPacks::choice();
    }

    public function savePack(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['pack' => 'required|string']);
        \App\Support\IconPacks\IconPacks::setChoice($this->pack);
        Auditor::log('icon_pack.set', null, null, ['pack' => $this->pack]);
        $this->dispatch('nx-toast', type: 'success', message: 'Icon pack saved. Every menu now uses it.');
    }

    public function updatedSurface(): void
    {
        if (! isset(self::SURFACES[$this->surface])) {
            $this->surface = 'app';
        }
        $this->reset('target', 'upload');
    }

    public function choose(string $slot): void
    {
        $this->target = $this->known($slot) ? $slot : '';
        $this->reset('upload');
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'upload' => 'required|file|mimes:webp,svg,png|max:512',
            'target' => 'required|string',
        ]);
        abort_unless($this->known($this->target), 422);

        $url = MediaStorage::storePublic($this->upload, 'menu-icons');
        IconOverrides::set($this->key($this->target), $url);
        Auditor::log('menu_icons.updated', null, null, ['slot' => $this->target]);

        $this->reset('upload', 'target');
        $this->dispatch('nx-toast', type: 'success', message: 'Menu icon saved.');
    }

    public function remove(string $slot): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless($this->known($slot), 422);

        IconOverrides::remove($this->key($slot));
        Auditor::log('menu_icons.removed', null, null, ['slot' => $slot]);
        $this->dispatch('nx-toast', type: 'success', message: 'Back to the built-in icon.');
    }

    /** @return array<string, array<int, array{slot:string,label:string,custom:bool,has:bool}>> */
    private function slots(): array
    {
        $sets = [
            'app' => array_map(fn ($k) => 'menu.app.more.'.$k, array_values(MenuIcons::MORE)),
            'marketing' => array_map(fn ($k) => 'menu.marketing.mega.'.$k, MenuIcons::MARKETING),
            'admin' => array_map(fn ($k) => 'menu.admin.'.$k, array_values(MenuIcons::ADMIN)),
        ];

        return array_map(fn ($list) => array_values(array_unique($list)), $sets);
    }

    private function known(string $slot): bool
    {
        return in_array($slot, $this->slots()[$this->surface] ?? [], true);
    }

    private function key(string $slot): string
    {
        return str_replace('.', '-', $slot);
    }

    public function render()
    {
        $needle = Str::lower(trim($this->search));
        $rows = collect($this->slots()[$this->surface] ?? [])
            ->map(function ($slot) {
                $r = MenuIcons::resolve($slot);

                return [
                    'slot' => $slot,
                    'label' => Str::headline(Str::afterLast($slot, '.')),
                    'custom' => (bool) ($r['custom'] ?? false),
                    'has' => $r !== null,
                ];
            })
            ->when($needle !== '', fn ($c) => $c->filter(fn ($r) => str_contains(Str::lower($r['label'].' '.$r['slot']), $needle)))
            ->values();

        return view('livewire.admin.master-marketing-menu-icons', [
            'rows' => $rows,
            'customCount' => $rows->where('custom', true)->count(),
        ]);
    }
}

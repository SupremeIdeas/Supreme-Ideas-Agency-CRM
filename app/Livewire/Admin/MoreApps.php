<?php

namespace App\Livewire\Admin;

use App\Models\MoreApp;
use App\Support\Auditor;
use App\Support\FeatureEntitlements;
use App\Support\MediaStorage;
use App\Support\MoreApps as MoreAppsCache;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → More Apps (master-only). Manage the shortcuts shown in the mobile
 * "More" sheet carousel — icon, name, and link — for cross-promoting
 * companion Supreme Ideas apps. Mirrors the Banners admin page's upload +
 * list pattern.
 */
#[Layout('components.layouts.admin')]
class MoreApps extends Component
{
    use WithFileUploads;

    public string $name = '';

    public $icon = null;

    public string $link_url = '';

    public $sort_order = 0;

    public ?string $saved = null;

    /** @var array<int, mixed> app id => replacement logo upload */
    public array $logos = [];

    /** @var array<int, string> app id => edited name */
    public array $names = [];

    public function mount(): void
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function save(): void
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'name' => 'required|string|max:60',
            'icon' => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:1024',
            'link_url' => 'required|string|max:500',
            'sort_order' => 'required|integer|min:0|max:999',
        ], [
            'icon.mimes' => 'App icon must be JPG, PNG, WebP, or SVG.',
        ]);

        // A link must be a same-app path (/...) or a full http(s) URL —
        // nothing script-y can ever end up in an href (same guard as Banners).
        $link = trim($this->link_url);
        if (! preg_match('#^(/|https?://)#i', $link)) {
            $this->addError('link_url', 'Use a full https:// URL or an in-app path starting with /.');

            return;
        }

        $app = MoreApp::create([
            'name' => trim($this->name),
            'icon_url' => MediaStorage::storePublic($this->icon, 'more-apps'),
            'link_url' => $link,
            'sort_order' => (int) $this->sort_order,
            'is_active' => true,
        ]);

        Auditor::log('more_app.created', MoreApp::class, $app->id, ['id' => $app->id, 'name' => $app->name]);
        $this->reset('name', 'icon', 'link_url');
        $this->sort_order = 0;
        $this->saved = "\"{$app->name}\" added to the More Apps carousel.";
        $this->dispatch('nx-toast', type: 'success', message: 'App added.');
    }

    public function toggle(int $id): void
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $app = MoreApp::findOrFail($id);
        $app->update(['is_active' => ! $app->is_active]);
        Auditor::log('more_app.toggled', MoreApp::class, $app->id, ['id' => $id, 'active' => $app->is_active]);
    }

    public function rename(int $id): void
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $name = trim((string) ($this->names[$id] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) {
            $this->addError("names.$id", 'Use 1 to 60 characters.');

            return;
        }
        MoreApp::findOrFail($id)->update(['name' => $name]);
        MoreAppsCache::flush();
        $this->dispatch('nx-toast', type: 'success', message: 'Name saved.');
    }

    public function replaceLogo(int $id): void
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(["logos.$id" => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:1024']);
        MoreApp::findOrFail($id)->update(['icon_url' => MediaStorage::storePublic($this->logos[$id], 'more-apps')]);
        unset($this->logos[$id]);
        MoreAppsCache::flush();
        Auditor::log('more_app.logo_replaced', MoreApp::class, $id);
        $this->dispatch('nx-toast', type: 'success', message: 'Logo replaced.');
    }

    public function move(int $id, string $dir): void
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $rows = MoreApp::orderBy('sort_order')->orderBy('id')->get()->values();
        $i = $rows->search(fn ($r) => $r->id === $id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= $rows->count()) {
            return;
        }
        $order = $rows->pluck('id')->all();
        [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        foreach ($order as $pos => $rid) {
            MoreApp::where('id', $rid)->update(['sort_order' => ($pos + 1) * 10]);
        }
        MoreAppsCache::flush();
    }

    public function delete(int $id): void
    {
        abort_unless(FeatureEntitlements::isMaster(), 404);
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $app = MoreApp::findOrFail($id);
        if ($app->is_system) {
            $this->dispatch('nx-toast', type: 'error', message: 'Built-in apps cannot be deleted. Pause it instead.');

            return;
        }
        Auditor::log('more_app.deleted', MoreApp::class, $app->id, ['id' => $id]);
        $app->delete();
    }

    public function render()
    {
        MoreAppsCache::flush(); // admin always sees fresh state
        if (class_exists(\App\Support\Business\SystemMoreApps::class)) {
            \App\Support\Business\SystemMoreApps::ensure();   // self-heal: a built-in app that went missing comes back
        }

        return view('livewire.admin.more-apps', [
            'apps' => MoreApp::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}

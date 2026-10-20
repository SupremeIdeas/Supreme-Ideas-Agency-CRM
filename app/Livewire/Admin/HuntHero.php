<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\HuntHeroBackground;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → The Hunt hero (owner request). Manages the SAME visual hero system
 * as the customer dashboard home (title/description/light+dark image/on-off/
 * size), under its own `hunt.hero.*` setting namespace so it can be
 * re-themed independently of the dashboard hero. Mirrors Admin\Branding's
 * "Dashboard home hero" section field-for-field.
 */
#[Layout('components.layouts.admin')]
class HuntHero extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $title_size = HuntHeroBackground::DEFAULT_TITLE_SIZE;

    public string $description = '';

    public bool $enabled = true;

    public $hero_light = null;

    public $hero_dark = null;

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $c = HuntHeroBackground::current();
        $this->title = $c['title'];
        $this->title_size = $c['title_size'];
        $this->description = $c['description'];
        $this->enabled = $c['enabled'];
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'nullable|string|max:40',
            'title_size' => 'required|in:'.implode(',', array_keys(HuntHeroBackground::TITLE_SIZES)),
            'description' => 'nullable|string|max:120',
            'hero_light' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'hero_dark' => 'nullable|mimes:webp,jpg,jpeg|max:600',
        ], [
            'title.max' => 'Keep the hero title short (40 characters) so it still fits the layout.',
            'description.max' => 'Keep the description to one short line (120 characters).',
            'hero_light.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_dark.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_light.max' => 'Keep the hero image under 600 KB for fast loading.',
            'hero_dark.max' => 'Keep the hero image under 600 KB for fast loading.',
        ]);

        Setting::setValue(HuntHeroBackground::TITLE_KEY, trim($this->title), 'rewards');
        Setting::setValue(HuntHeroBackground::TITLE_SIZE_KEY, $this->title_size, 'rewards');
        Setting::setValue(HuntHeroBackground::DESC_KEY, trim($this->description), 'rewards');
        Setting::setValue(HuntHeroBackground::ENABLED_KEY, $this->enabled, 'rewards');

        foreach (['hero_light' => HuntHeroBackground::LIGHT_KEY, 'hero_dark' => HuntHeroBackground::DARK_KEY] as $field => $key) {
            if ($this->{$field}) {
                Setting::setValue($key, MediaStorage::storePublic($this->{$field}, 'hunt-hero'), 'rewards');
                $this->{$field} = null;
            }
        }

        HuntHeroBackground::flush();
        $this->title = HuntHeroBackground::title();
        $this->description = HuntHeroBackground::description();
        Auditor::log('hunt.hero_updated');
        $this->saved = 'The Hunt hero saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Hunt hero saved — live now.');
    }

    /** Remove the hero images — the Hunt page falls back to text-only. */
    public function removeImages(): void
    {
        foreach ([HuntHeroBackground::LIGHT_KEY, HuntHeroBackground::DARK_KEY] as $key) {
            Setting::where('key', $key)->get()->each->delete();
        }
        HuntHeroBackground::flush();
        Auditor::log('hunt.hero_images_removed');
        $this->saved = 'Hero images removed — the Hunt page uses text only.';
        $this->dispatch('nx-toast', type: 'success', message: 'Hero images removed.');
    }

    public function render()
    {
        return view('livewire.admin.hunt-hero', [
            'currentLight' => HuntHeroBackground::light(),
            'currentDark' => HuntHeroBackground::dark(),
            'isSet' => filled(HuntHeroBackground::current()['uploaded_light']) || filled(HuntHeroBackground::current()['uploaded_dark']),
        ]);
    }
}

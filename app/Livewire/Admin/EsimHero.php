<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\EsimHeroContent;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → eSIM hero (esim_upgrade Part 2). Manage the storefront hero: title,
 * description, and up to EsimHeroContent::MAX_IMAGES ordered images for the
 * interchanging-reveal hero. New uploads append; clearing all falls back to
 * the shipped seed images. Generic upload/reorder so images can be swapped
 * later without a deploy.
 *
 * Re-authorized on every request via booted().
 */
#[Layout('components.layouts.admin')]
class EsimHero extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    // The catalogue section heading + subheading below the hero (admin-editable).
    public string $section_title = '';

    public string $section_subtitle = '';

    /** @var list<string> current image URLs (0–MAX_IMAGES). */
    public array $images = [];

    /**
     * New uploads picked in this request — a single multi-select file input
     * rather than a fixed number of named slots, so the upload count scales
     * with MAX_IMAGES instead of being capped at whatever slot count was
     * hardcoded here.
     *
     * @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile>
     */
    public array $newImages = [];

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $c = EsimHeroContent::current();
        $this->title = $c['title'];
        $this->description = $c['description'];
        $this->section_title = $c['section_title'];
        $this->section_subtitle = $c['section_subtitle'];
        $this->images = $c['images'];
    }

    public function removeImage(int $i): void
    {
        unset($this->images[$i]);
        $this->images = array_values($this->images);
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:120',
            'description' => 'required|string|max:250',
            'section_title' => 'required|string|max:60',
            'section_subtitle' => 'required|string|max:160',
            'newImages' => 'array',
            'newImages.*' => 'mimes:webp,jpg,jpeg|max:600',
        ]);

        // Fold any new uploads into the ordered image list.
        $images = $this->images;
        foreach ($this->newImages as $file) {
            $images[] = MediaStorage::storePublic($file, 'esim-hero');
        }
        $this->newImages = [];
        $images = array_slice(array_values(array_unique($images)), 0, EsimHeroContent::MAX_IMAGES);

        Setting::setValue(EsimHeroContent::TITLE_KEY, trim($this->title), 'esim');
        Setting::setValue(EsimHeroContent::DESC_KEY, trim($this->description), 'esim');
        Setting::setValue(EsimHeroContent::SECTION_TITLE_KEY, trim($this->section_title), 'esim');
        Setting::setValue(EsimHeroContent::SECTION_SUBTITLE_KEY, trim($this->section_subtitle), 'esim');
        Setting::setValue(EsimHeroContent::IMAGES_KEY, $images, 'esim');
        EsimHeroContent::flush();

        $this->images = $images;
        Auditor::log('esim.hero_updated', null, null, ['count' => count($images)]);
        $this->saved = 'eSIM hero saved.';
    }

    public function render()
    {
        return view('livewire.admin.esim-hero', ['maxImages' => EsimHeroContent::MAX_IMAGES]);
    }
}

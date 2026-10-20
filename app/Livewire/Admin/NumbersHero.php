<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\NumbersHeroContent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Numbers hero (Numbers V6 §0). Manage the Numbers landing hero:
 * title, description, and up to NumbersHeroContent::MAX_IMAGES ordered images
 * for the interchanging-reveal hero. New uploads append; clearing all falls
 * back to the shipped seed images. Mirrors the eSIM hero admin.
 * Re-authorized on every request via booted().
 */
#[Layout('components.layouts.admin')]
class NumbersHero extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    /** @var list<string> current image URLs (0–MAX_IMAGES). */
    public array $images = [];

    /**
     * New uploads picked in this request — a single multi-select file input
     * rather than a fixed number of named slots (see EsimHero for the same
     * fix and its rationale).
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
        $c = NumbersHeroContent::current();
        $this->title = $c['title'];
        $this->description = $c['description'];
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
            'newImages' => 'array',
            'newImages.*' => 'mimes:webp,jpg,jpeg|max:600',
        ]);

        $images = $this->images;
        foreach ($this->newImages as $file) {
            $images[] = MediaStorage::storePublic($file, 'numbers-hero');
        }
        $this->newImages = [];
        $images = array_slice(array_values(array_unique($images)), 0, NumbersHeroContent::MAX_IMAGES);

        Setting::setValue(NumbersHeroContent::TITLE_KEY, trim($this->title), 'numbers');
        Setting::setValue(NumbersHeroContent::DESC_KEY, trim($this->description), 'numbers');
        Setting::setValue(NumbersHeroContent::IMAGES_KEY, $images, 'numbers');
        NumbersHeroContent::flush();

        $this->images = $images;
        Auditor::log('numbers.hero_updated', null, null, ['count' => count($images)]);
        $this->saved = 'Numbers hero saved.';
    }

    public function render()
    {
        return view('livewire.admin.numbers-hero', ['maxImages' => NumbersHeroContent::MAX_IMAGES]);
    }
}

<?php

namespace App\Livewire;

use App\Support\Business\BusinessGuideLibrary;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Business Suite > Guides (MASTER-ONLY): the guide directory for people on a Naara Pro business workspace. An index grouped by track (get started, connect channels,
 * use it every day, grow with it, get unstuck) and one page per guide with a contents list and previous / next. Content lives in BusinessGuideLibrary.
 */
#[Layout('components.layouts.business')]
class BusinessGuides extends Component
{
    public ?string $slug = null;

    public function mount(?string $slug = null): void
    {
        $this->slug = $slug;
        abort_if($slug !== null && BusinessGuideLibrary::find($slug) === null, 404);
    }

    public function render()
    {
        $all = BusinessGuideLibrary::all();
        $guide = $this->slug ? $all[$this->slug] : null;
        $keys = array_keys($all);
        $i = $this->slug ? array_search($this->slug, $keys, true) : false;

        return view('livewire.business-guides', [
            'all' => $all,
            'tracks' => BusinessGuideLibrary::TRACKS,
            'guide' => $guide,
            'prev' => $i !== false && $i > 0 ? ['slug' => $keys[$i - 1]] + $all[$keys[$i - 1]] : null,
            'next' => $i !== false && isset($keys[$i + 1]) ? ['slug' => $keys[$i + 1]] + $all[$keys[$i + 1]] : null,
            'toc' => $guide ? collect($guide['blocks'])->filter(fn ($b) => isset($b['h']))->pluck('h')->values()->all() : [],
            'routeHas' => fn (string $r) => Route::has($r),
        ]);
    }
}

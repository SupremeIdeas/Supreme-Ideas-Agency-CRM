<?php

namespace App\Livewire\Handbooks;

use App\Models\Handbook;
use App\Models\HandbookCategory;
use App\Models\HandbookMood;
use App\Support\Handbooks\HandbookSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** The full Travel Handbooks directory (MASTER-ONLY, /handbooks/all): search, country / topic / style / price filters, sorting and paging over every published title. Public. */
#[Layout('components.layouts.marketing')]
class Store extends Component
{
    use WithPagination;

    public const PER_PAGE = 24;

    public const SORTS = ['popular' => 'Most popular', 'new' => 'Newest', 'title' => 'Title A to Z', 'price_low' => 'Price: low to high', 'price_high' => 'Price: high to low'];

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'country', except: '')]
    public string $country = '';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    #[Url(as: 'mood', except: '')]
    public string $mood = '';

    #[Url(as: 'price', except: '')]
    public string $price = '';

    #[Url(as: 'sort', except: 'popular')]
    public string $sort = 'popular';

    public function updated($name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function clear(): void
    {
        $this->reset('q', 'country', 'category', 'mood', 'price');
        $this->resetPage();
    }

    public function render()
    {
        if (! HandbookSettings::get('store_enabled')) {
            return view('livewire.handbooks.store-closed');
        }
        $q = Handbook::query()->published()->with(['categories'])->withCount('entitlements');
        if (trim($this->q) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->q)).'%';
            $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('subtitle', 'like', $like)->orWhere('description', 'like', $like)->orWhere('country_code', 'like', $like));
        }
        if (preg_match('/^[A-Za-z]{2}$/', $this->country) === 1) {
            $q->where('country_code', strtoupper($this->country));
        }
        if ($this->category !== '') {
            $q->whereHas('categories', fn ($c) => $c->where('slug', $this->category));
        }
        if ($this->mood !== '') {
            $q->whereHas('moods', fn ($c) => $c->where('slug', $this->mood));
        }
        if (in_array($this->price, ['5', '10', '15'], true)) {
            $q->where('list_price_usd', '<=', (float) $this->price);
        }
        match ($this->sort) {
            'new' => $q->orderByDesc('published_at'),
            'title' => $q->orderBy('title'),
            'price_low' => $q->orderBy('list_price_usd')->orderBy('title'),
            'price_high' => $q->orderByDesc('list_price_usd')->orderBy('title'),
            default => $q->orderByDesc('is_featured')->orderByDesc('entitlements_count')->orderBy('sort_order')->orderBy('title'),
        };
        $published = fn () => Handbook::query()->published();
        $filtered = trim($this->q) !== '' || $this->country !== '' || $this->category !== '' || $this->mood !== '' || $this->price !== '';

        return view('livewire.handbooks.store', [
            'books' => $q->paginate(self::PER_PAGE), 'filtered' => $filtered, 'total' => $published()->count(),
            'countries' => $published()->whereNotNull('country_code')->selectRaw('country_code, count(*) as n')->groupBy('country_code')->orderBy('country_code')->get(),
            'categories' => HandbookCategory::query()->whereHas('handbooks', fn ($h) => $h->where('status', Handbook::PUBLISHED))->orderBy('name')->get(),
            'moods' => HandbookMood::query()->whereHas('handbooks', fn ($h) => $h->where('status', Handbook::PUBLISHED))->orderBy('name')->get(),
            'sorts' => self::SORTS, 'disclaimer' => (string) config('handbooks.disclaimer'),
        ]);
    }
}

{{-- Travel Handbooks directory (MASTER-ONLY). Public. Marketing shell, bookstore layout: filters rail on desktop, drawer on phones, shelves of covers, paging. --}}
<div x-data="{ filters: false }" @keydown.escape.window="filters = false">
    <section class="relative isolate overflow-hidden px-4 pb-10 pt-10 sm:pt-14 nx-ambient">
        <div class="mx-auto max-w-6xl">
            <nav class="text-sm text-slate-500 dark:text-slate-400" aria-label="Breadcrumb"><a href="{{ route('handbooks.store') }}" wire:navigate class="hover:underline">Travel Handbooks</a> <span aria-hidden="true">/</span> <span class="text-slate-800 dark:text-slate-200">All handbooks</span></nav>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">All travel handbooks</h1>
            <p class="mt-2 max-w-2xl text-slate-600 dark:text-slate-300">{{ $total }} {{ \Illuminate\Support\Str::plural('guide', $total) }} to living, studying and working abroad. Read them on your phone, offline.</p>
            <form class="mt-6 flex flex-col gap-3 sm:flex-row" role="search" wire:submit.prevent>
                <label class="sr-only" for="hb-q">Search handbooks</label>
                <div class="relative flex-1"><x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" /><input id="hb-q" type="search" wire:model.live.debounce.300ms="q" placeholder="Search by country, topic or title" class="w-full rounded-2xl border border-slate-200 bg-white py-3.5 pl-12 pr-4 text-base text-slate-900 shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30 dark:border-white/10 dark:bg-[#16233d] dark:text-white"></div>
                <div class="flex gap-3">
                    <label class="sr-only" for="hb-sort">Sort by</label>
                    <select id="hb-sort" wire:model.live="sort" class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-base text-slate-900 shadow-sm dark:border-white/10 dark:bg-[#16233d] dark:text-white sm:flex-none">@foreach ($sorts as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                    <button type="button" @click="filters = true" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3.5 font-semibold text-slate-800 shadow-sm lg:hidden dark:border-white/10 dark:bg-[#16233d] dark:text-white"><x-icon name="list" class="h-5 w-5" />Filters</button>
                </div>
            </form>
        </div>
    </section>

    <section class="px-4 pb-20">
        <div class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[16rem_1fr]">
            {{-- Filters: a rail on desktop, a bottom sheet on phones --}}
            <div x-show="filters" x-cloak class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="filters = false" aria-hidden="true"></div>
            <aside :class="filters ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'" class="fixed inset-x-0 bottom-0 z-50 max-h-[85vh] overflow-y-auto rounded-t-[28px] bg-white p-6 shadow-2xl transition-transform duration-300 lg:static lg:z-auto lg:max-h-none lg:translate-y-0 lg:rounded-none lg:bg-transparent lg:p-0 lg:shadow-none dark:bg-[#0F2033] lg:dark:bg-transparent" aria-label="Filters" data-filters>
                <div class="flex items-center justify-between lg:hidden"><h2 class="text-lg font-bold text-slate-900 dark:text-white">Filters</h2><button type="button" @click="filters = false" class="rounded-full p-2 text-slate-500" aria-label="Close filters"><x-icon name="x" class="h-5 w-5" /></button></div>
                @if ($filtered)<button type="button" wire:click="clear" class="mb-4 mt-3 text-sm font-semibold text-primary underline dark:text-accent lg:mt-0">Clear all filters</button>@endif
                <div class="space-y-7 text-slate-800 dark:text-slate-200">
                    <fieldset><legend class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Country</legend>
                        <div class="max-h-60 space-y-1 overflow-y-auto pr-1">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-white/5"><input type="radio" wire:model.live="country" value="" class="accent-[#0A6E6E]"> All countries</label>
                            @foreach ($countries as $c)<label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-white/5"><input type="radio" wire:model.live="country" value="{{ $c->country_code }}" class="accent-[#0A6E6E]"><x-country-flag :country="$c->country_code" class="h-3.5 w-5 rounded-sm" />{{ \App\Support\CountryNames::name($c->country_code) }}<span class="ml-auto text-xs text-slate-400">{{ $c->n }}</span></label>@endforeach
                        </div>
                    </fieldset>
                    @if ($categories->isNotEmpty())
                    <fieldset><legend class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Topic</legend>
                        <div class="flex flex-wrap gap-2"><button type="button" wire:click="$set('category', '')" class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $category === '' ? 'border-primary bg-primary text-white' : 'border-slate-200 dark:border-white/15' }}">All</button>@foreach ($categories as $c)<button type="button" wire:click="$set('category', '{{ $c->slug }}')" class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $category === $c->slug ? 'border-primary bg-primary text-white' : 'border-slate-200 hover:border-primary/50 dark:border-white/15' }}">{{ $c->name }}</button>@endforeach</div>
                    </fieldset>
                    @endif
                    @if ($moods->isNotEmpty())
                    <fieldset><legend class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Style</legend>
                        <div class="flex flex-wrap gap-2"><button type="button" wire:click="$set('mood', '')" class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $mood === '' ? 'border-primary bg-primary text-white' : 'border-slate-200 dark:border-white/15' }}">Any</button>@foreach ($moods as $m)<button type="button" wire:click="$set('mood', '{{ $m->slug }}')" class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $mood === $m->slug ? 'border-primary bg-primary text-white' : 'border-slate-200 hover:border-primary/50 dark:border-white/15' }}">{{ $m->name }}</button>@endforeach</div>
                    </fieldset>
                    @endif
                    <fieldset><legend class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Price</legend>
                        <div class="flex flex-wrap gap-2">@foreach (['' => 'Any', '5' => 'Up to $5', '10' => 'Up to $10', '15' => 'Up to $15'] as $v => $label)<button type="button" wire:click="$set('price', '{{ $v }}')" class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $price === (string) $v ? 'border-primary bg-primary text-white' : 'border-slate-200 hover:border-primary/50 dark:border-white/15' }}">{{ $label }}</button>@endforeach</div>
                    </fieldset>
                </div>
                <button type="button" @click="filters = false" class="nx-btn nx-btn--primary mt-8 w-full !py-3 lg:hidden">Show {{ $books->total() }} {{ \Illuminate\Support\Str::plural('result', $books->total()) }}</button>
            </aside>

            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600 dark:text-slate-300" aria-live="polite"><span data-result-count>{{ $books->total() }} {{ \Illuminate\Support\Str::plural('handbook', $books->total()) }}@if ($filtered) {{ 'match' }} @endif</span>
                    <span class="flex flex-wrap gap-2">@foreach (array_filter(['q' => $q ? '“'.$q.'”' : null, 'country' => $country ? (\App\Support\CountryNames::name($country)) : null, 'category' => $category ? ($categories->firstWhere('slug', $category)?->name) : null, 'mood' => $mood ? ($moods->firstWhere('slug', $mood)?->name) : null, 'price' => $price ? 'Up to $'.$price : null]) as $k => $label)<button type="button" wire:click="$set('{{ $k }}', '')" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:bg-accent/15 dark:text-accent">{{ $label }} <x-icon name="x" class="h-3 w-3" /></button>@endforeach</span>
                </div>

                @if ($books->isEmpty())
                    <div class="mt-8 rounded-3xl border border-dashed border-slate-300 p-10 text-center dark:border-white/20">
                        <img src="{{ asset('images/illustrations/es-book.svg') }}" alt="" width="240" height="180" loading="lazy" class="mx-auto h-36 w-auto" data-empty-art>
                        <h2 class="mt-3 text-lg font-bold text-slate-900 dark:text-white">{{ $filtered ? 'Nothing matches that yet' : 'The first handbooks are on their way' }}</h2>
                        <p class="mt-1 text-slate-600 dark:text-slate-300">{{ $filtered ? 'Try a different search or clear the filters.' : 'Check back soon.' }}</p>
                        @if ($filtered)<button type="button" wire:click="clear" class="nx-btn nx-btn--primary mt-5 !px-6 !py-2.5">Clear filters</button>@endif
                    </div>
                @else
                    <div class="mt-6 grid grid-cols-2 gap-x-5 gap-y-9 text-slate-900 sm:grid-cols-3 xl:grid-cols-4 dark:text-white" data-handbook-grid wire:loading.class="opacity-60">
                        @foreach ($books as $b)@include('handbooks._shelf-book', ['b' => $b])@endforeach
                    </div>
                    <div class="mt-10">{{ $books->links() }}</div>
                @endif
                <p class="mt-10 text-xs text-slate-500 dark:text-slate-400">{{ $disclaimer }}</p>
            </div>
        </div>
    </section>
</div>

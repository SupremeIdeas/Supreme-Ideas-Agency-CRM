{{-- Developer documentation (MASTER-ONLY): grouped navigation on the left, the reading column in the middle, brand-coloured code on the right.
     Content is App\Support\MasterMarketing\DeveloperDocs; the page, its sidebar and its previous/next links all derive from that one list. --}}
@php
    use App\Support\MasterMarketing\DeveloperDocs as Docs;
    $sub = fn (string $s) => str_replace('YOUR-DOMAIN', $host, $s);
    $isRef = ! empty($page['method']);
@endphp
<x-layouts.marketing :title="$page['title'].' | Naara Developers'" :description="$page['lead']">
    @include('marketing.master-marketing._developers-style')

    <div class="mx-auto max-w-[1440px] px-4 pb-24 pt-8 sm:pt-10" x-data="{ q: '', open: false }">
        <div class="lg:grid lg:grid-cols-[250px_minmax(0,1fr)] lg:gap-8 xl:grid-cols-[250px_minmax(0,1fr)_minmax(380px,470px)]">

            {{-- Left: grouped navigation with a quick filter --}}
            <aside class="lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:self-start lg:overflow-y-auto lg:pr-1" aria-label="Documentation">
                <button type="button" x-on:click="open = ! open" :aria-expanded="open" class="mb-3 flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 lg:hidden dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                    <span class="inline-flex items-center gap-2"><x-icon name="menu" class="h-4 w-4" /> Documentation menu</span><span class="text-xs font-medium text-slate-600 dark:text-slate-300">{{ $page['title'] }}</span>
                </button>
                <div class="lg:block" :class="open ? 'block' : 'hidden'">
                    <a href="{{ route('developers') }}" wire:navigate class="mb-3 hidden items-center gap-2 text-sm font-semibold text-primary lg:inline-flex dark:text-accent"><x-icon name="arrow-left" class="h-4 w-4" /> Developers home</a>
                    <label class="relative mb-4 block">
                        <span class="sr-only">Filter the documentation</span>
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500 dark:text-slate-400" />
                        <input type="search" x-model="q" placeholder="Filter pages" class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-500 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-slate-400">
                    </label>
                    <nav class="space-y-5 pb-6">
                        @foreach ($groups as $g)
                            <div x-show="! q || {{ Js::from(collect($g['pages'])->pluck('title')->implode(' ')) }}.toLowerCase().includes(q.toLowerCase())">
                                <p class="mb-1.5 flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-600 dark:text-slate-300"><x-icon :name="$g['icon']" class="h-3.5 w-3.5 text-primary dark:text-accent" />{{ $g['title'] }}</p>
                                <ul class="border-l border-slate-200 dark:border-white/10">
                                    @foreach ($g['pages'] as $p)
                                        @php $on = $p['slug'] === $page['slug']; @endphp
                                        <li x-show="! q || {{ Js::from(strtolower($p['title'])) }}.includes(q.toLowerCase())">
                                            <a href="{{ route('developers.docs', $p['slug']) }}" wire:navigate @if ($on) aria-current="page" @endif
                                               class="-ml-px flex items-center gap-2 border-l-2 py-1.5 pl-3 text-sm transition {{ $on ? 'border-accent font-semibold text-slate-900 dark:text-white' : 'border-transparent text-slate-700 hover:border-primary hover:text-primary dark:text-slate-300 dark:hover:text-accent' }}">
                                                @if (! empty($p['method']))<span class="rounded px-1.5 py-px text-[10px] font-bold {{ $p['method'] === 'GET' ? 'bg-primary/15 text-primary-dark dark:bg-primary/25 dark:text-[#7FE0D8]' : 'bg-accent/20 text-[#6B4D00] dark:bg-accent/20 dark:text-accent' }}">{{ $p['method'] }}</span>@endif
                                                {{ $p['title'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </nav>
                </div>
            </aside>

            {{-- Middle: the reading column --}}
            <article class="min-w-0">
                <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                    <a href="{{ route('developers') }}" wire:navigate class="hover:text-primary dark:hover:text-accent">Developers</a><x-icon name="chevron-right" class="h-3 w-3" />
                    <span>{{ $page['group'] }}</span><x-icon name="chevron-right" class="h-3 w-3" /><span class="text-slate-900 dark:text-white">{{ $page['title'] }}</span>
                </nav>
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ $page['title'] }}</h1>
                <p class="mt-3 max-w-2xl text-lg leading-relaxed text-slate-700 dark:text-slate-300">{{ $page['lead'] }}</p>

                @if ($isRef)
                    <div class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-white/5" data-dv-endpoint>
                        <span class="rounded-lg px-2.5 py-1 text-xs font-bold {{ $page['method'] === 'GET' ? 'bg-primary text-white' : 'bg-accent text-[#0D1B2A]' }}">{{ $page['method'] }}</span>
                        <code class="font-mono text-sm font-semibold text-slate-900 dark:text-white">/api/v1{{ $page['path'] }}</code>
                        <span class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-white/10 dark:text-slate-200"><x-icon name="key" class="h-3.5 w-3.5" /> Scope: <code class="dv-inline">{{ $page['scope'] }}</code></span>
                    </div>
                @endif

                <div class="dv-prose mt-7 space-y-5">
                    @foreach ($page['body'] as $b)
                        @switch($b[0])
                            @case('p')<p>{!! Docs::inline($sub($b[1])) !!}</p>@break
                            @case('h')<h2 class="!mt-9 text-xl font-bold text-slate-900 dark:text-white">{{ $b[1] }}</h2>@break
                            @case('list')
                                <ul class="space-y-2">
                                    @foreach ($b[1] as $li)
                                        <li class="flex gap-3"><x-icon name="check" class="mt-1.5 h-4 w-4 shrink-0 text-primary dark:text-accent" /><span>{!! Docs::inline($sub($li)) !!}</span></li>
                                    @endforeach
                                </ul>
                            @break
                            @case('steps')
                                <ol class="space-y-4">
                                    @foreach ($b[1] as $i => [$t, $d])
                                        <li class="flex gap-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{{ $i + 1 }}</span>
                                            <div><p class="font-semibold text-slate-900 dark:text-white">{{ $t }}</p><p class="mt-1 text-[15px] leading-relaxed">{!! Docs::inline($sub($d)) !!}</p></div>
                                        </li>
                                    @endforeach
                                </ol>
                            @break
                            @case('table')
                                <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10">
                                    <table class="w-full text-left text-sm">
                                        <thead class="bg-slate-100 text-xs uppercase tracking-wider text-slate-700 dark:bg-white/10 dark:text-slate-200"><tr>@foreach ($b[1] as $th)<th class="px-4 py-2.5 font-bold">{{ $th }}</th>@endforeach</tr></thead>
                                        <tbody>
                                            @foreach ($b[2] as $row)
                                                <tr class="border-t border-slate-200 align-top dark:border-white/10">@foreach ($row as $cell)<td class="px-4 py-2.5">{!! Docs::inline($sub($cell)) !!}</td>@endforeach</tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @break
                            @case('callout')
                                @php
                                    $tone = ['info' => ['border-primary/40 bg-primary/5 dark:bg-primary/10', 'text-primary dark:text-[#7FE0D8]', 'info'], 'warn' => ['border-accent/60 bg-accent/10', 'text-[#6B4D00] dark:text-accent', 'lock'], 'ok' => ['border-emerald-500/40 bg-emerald-500/5 dark:bg-emerald-500/10', 'text-emerald-700 dark:text-emerald-300', 'shield']][$b[1]];
                                @endphp
                                <div class="flex gap-3 rounded-2xl border p-4 {{ $tone[0] }}" role="note">
                                    <x-icon :name="$tone[2]" class="mt-0.5 h-5 w-5 shrink-0 {{ $tone[1] }}" />
                                    <div><p class="font-semibold {{ $tone[1] }}">{{ $b[2] }}</p><p class="mt-1 text-[15px] leading-relaxed">{!! Docs::inline($sub($b[3])) !!}</p></div>
                                </div>
                            @break
                            @case('diagram')
                                @include('marketing.master-marketing._developers-lifecycle')
                            @break
                        @endswitch
                    @endforeach
                </div>

                {{-- On screens narrower than the three-column layout the samples follow the text --}}
                @if (! empty($page['samples']))
                    <div class="mt-9 space-y-5 xl:hidden">
                        @foreach ($page['samples'] as $s)<x-master-marketing.developer-code :sample="$s" :host="$host" />@endforeach
                    </div>
                @endif

                <nav class="mt-12 grid gap-3 border-t border-slate-200 pt-6 sm:grid-cols-2 dark:border-white/10 [&>*]:min-w-0" aria-label="Previous and next">
                    @if ($nav['prev'])
                        <a href="{{ route('developers.docs', $nav['prev']['slug']) }}" wire:navigate class="group rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-primary dark:border-white/10 dark:bg-white/5 dark:hover:border-accent">
                            <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300"><x-icon name="arrow-left" class="h-3.5 w-3.5" /> Previous</span>
                            <span class="mt-1 block font-semibold text-slate-900 group-hover:text-primary dark:text-white dark:group-hover:text-accent">{{ $nav['prev']['title'] }}</span>
                        </a>
                    @else<span></span>@endif
                    @if ($nav['next'])
                        <a href="{{ route('developers.docs', $nav['next']['slug']) }}" wire:navigate class="group rounded-2xl border border-slate-200 bg-white p-4 text-right transition hover:border-primary dark:border-white/10 dark:bg-white/5 dark:hover:border-accent">
                            <span class="flex items-center justify-end gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">Next <x-icon name="arrow-right" class="h-3.5 w-3.5" /></span>
                            <span class="mt-1 block font-semibold text-slate-900 group-hover:text-primary dark:text-white dark:group-hover:text-accent">{{ $nav['next']['title'] }}</span>
                        </a>
                    @endif
                </nav>
            </article>

            {{-- Right: code, sticky beside the text on wide screens --}}
            <aside class="hidden xl:block xl:sticky xl:top-24 xl:max-h-[calc(100vh-7rem)] xl:self-start xl:space-y-5 xl:overflow-y-auto" aria-label="Code samples">
                @forelse ($page['samples'] as $s)
                    <x-master-marketing.developer-code :sample="$s" :host="$host" />
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                        <p class="font-semibold text-slate-900 dark:text-white">Ready to build?</p>
                        <p class="mt-1">Start with the quick start, then place a test order with a small balance.</p>
                        <a href="{{ route('developers.docs', 'quick-start') }}" wire:navigate class="nx-btn nx-btn--primary mt-3 !px-5">Open the quick start</a>
                    </div>
                @endforelse
            </aside>
        </div>
    </div>
</x-layouts.marketing>

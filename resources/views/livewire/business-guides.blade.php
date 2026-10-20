{{-- nx:converted (skin canvas + tokens) --}}
@php
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5';
    $tag = 'inline-flex items-center rounded-full border border-[rgb(var(--nx-line-strong))] px-2.5 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-text-2))]';
    $callout = fn (string $tone) => 'my-4 flex gap-3 rounded-xl border p-3.5 text-sm text-[rgb(var(--nx-text))]" style="border-color: rgb(var(--nx-'.$tone.')); background: rgb(var(--nx-'.$tone.') / .1)';
@endphp
<div>
<x-nx.page class="px-0">
<div class="mx-auto max-w-5xl pb-28">
@if (! $guide)
    <header class="mb-6 flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="book-open" class="h-5 w-5" /></span>
        <div class="min-w-0">
            <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]">Business Suite guides</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">Everything you need to connect WhatsApp, Instagram and Telegram, run calls and your team, and keep going as you grow. Start with the first guide and work down.</p>
        </div>
    </header>

    <div x-data="{ q: '' }">
        <label class="relative mb-6 block">
            <span class="sr-only">Search the guides</span>
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[rgb(var(--nx-text-2))]" />
            <input type="search" x-model="q" placeholder="Search the guides (try: whatsapp, token, webhook)" class="w-full rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] py-2.5 ps-10 pe-4 text-sm text-[rgb(var(--nx-text))]">
        </label>

        @foreach ($tracks as $tid => $tname)
            @php $inTrack = array_filter($all, fn ($g) => $g['track'] === $tid); @endphp
            @if ($inTrack)
                <section class="mb-8" aria-labelledby="trk-{{ $tid }}" x-show="!q || [...$el.querySelectorAll('[data-g]')].some(e => e.dataset.g.includes(q.toLowerCase()))">
                    <h2 id="trk-{{ $tid }}" class="mb-3 text-xs font-bold uppercase tracking-wider text-[rgb(var(--nx-text-2))]">{{ $tname }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($inTrack as $slug => $g)
                            <a href="{{ route('business.guides', ['slug' => $slug]) }}" wire:navigate wire:key="g-{{ $slug }}" data-g="{{ \Illuminate\Support\Str::lower($g['title'].' '.$g['summary'].' '.$slug) }}"
                                x-show="!q || $el.dataset.g.includes(q.toLowerCase())"
                                class="{{ $card }} group flex gap-3 transition hover:border-[rgb(var(--nx-teal))]">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[rgb(var(--nx-teal)/0.12)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="{{ $g['icon'] }}" class="h-5 w-5" /></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-[rgb(var(--nx-text))]">{{ $g['title'] }}</span>
                                    <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">{{ $g['summary'] }}</span>
                                    <span class="mt-2 flex flex-wrap gap-1.5"><span class="{{ $tag }}">{{ $g['time'] }}</span>@if ($g['audience'] === 'admin')<span class="{{ $tag }}">Owners and admins</span>@endif</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
@else
    <nav class="mb-4 flex items-center gap-1.5 text-xs text-[rgb(var(--nx-text-2))]" aria-label="Breadcrumb">
        <a href="{{ route('business.guides') }}" wire:navigate class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Guides</a>
        <x-icon name="chevron-right" class="h-3 w-3" /> <span>{{ $tracks[$guide['track']] }}</span>
    </nav>
    <div class="grid gap-6 lg:grid-cols-[1fr_220px]">
        <article class="min-w-0">
            <header class="mb-5">
                <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]">{{ $guide['title'] }}</h1>
                <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">{{ $guide['summary'] }}</p>
                <p class="mt-2 flex flex-wrap gap-1.5"><span class="{{ $tag }}">{{ $guide['time'] }}</span>@if ($guide['audience'] === 'admin')<span class="{{ $tag }}">Owners and admins</span>@endif</p>
            </header>

            <div class="{{ $card }} text-[15px] leading-relaxed text-[rgb(var(--nx-text))]">
                @foreach ($guide['blocks'] as $b)
                    @if (isset($b['h']))
                        <h2 id="h-{{ \Illuminate\Support\Str::slug($b['h']) }}" class="mb-2 mt-6 scroll-mt-24 text-lg font-bold first:mt-0">{{ $b['h'] }}</h2>
                    @elseif (isset($b['p']))
                        <p class="my-3 text-[rgb(var(--nx-text))]">{{ $b['p'] }}</p>
                    @elseif (isset($b['list']))
                        <ul class="my-3 list-disc space-y-1.5 ps-5">@foreach ($b['list'] as $li)<li>{{ $li }}</li>@endforeach</ul>
                    @elseif (isset($b['steps']))
                        <ol class="my-4 space-y-4">
                            @foreach ($b['steps'] as $n => $st)
                                <li class="flex gap-3">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b))] text-xs font-bold text-white" aria-hidden="true">{{ $n + 1 }}</span>
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ $st[0] }}</p>
                                        <p class="mt-0.5 text-[rgb(var(--nx-text-2))]">{{ $st[1] }}</p>
                                        @if (! empty($st[2]))<pre class="mt-2 overflow-x-auto rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3 text-xs">{{ $st[2] }}</pre>@endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @elseif (isset($b['note']))
                        <div class="{!! $callout('teal') !!}" role="note"><x-icon name="lightbulb" class="mt-0.5 h-4 w-4 shrink-0 text-[rgb(var(--nx-teal-ink))]" /><p><b>Good to know. </b>{{ $b['note'] }}</p></div>
                    @elseif (isset($b['warn']))
                        <div class="{!! $callout('warn') !!}" role="note"><x-icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-[rgb(var(--nx-warn))]" /><p><b>Careful. </b>{{ $b['warn'] }}</p></div>
                    @elseif (isset($b['table']))
                        <div class="my-4 overflow-x-auto rounded-xl border border-[rgb(var(--nx-line))]">
                            <table class="w-full min-w-[480px] border-collapse text-left text-sm">
                                <thead><tr class="bg-[rgb(var(--nx-surface-2))]">@foreach ($b['table'][0] as $th)<th scope="col" class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $th }}</th>@endforeach</tr></thead>
                                <tbody>@foreach ($b['table'][1] as $row)<tr class="border-t border-[rgb(var(--nx-line))] align-top">@foreach ($row as $ci => $cell)<td class="px-3 py-2 {{ $ci === 0 ? 'font-semibold' : 'text-[rgb(var(--nx-text-2))]' }}">{{ $cell }}</td>@endforeach</tr>@endforeach</tbody>
                            </table>
                        </div>
                    @elseif (isset($b['check']))
                        <ul class="my-3 space-y-2" x-data="{ done: {} }">
                            @foreach ($b['check'] as $ci => $item)
                                <li><label class="flex cursor-pointer items-start gap-2.5"><input type="checkbox" x-model="done[{{ $ci }}]" class="mt-1 h-4 w-4 rounded"><span :class="done[{{ $ci }}] ? 'line-through opacity-70' : ''">{{ $item }}</span></label></li>
                            @endforeach
                        </ul>
                    @elseif (isset($b['cta']) && $routeHas($b['cta'][1]))
                        <a href="{{ route($b['cta'][1]) }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--sm mt-4 inline-flex items-center gap-2">{{ $b['cta'][0] }} <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    @endif
                @endforeach
            </div>

            <nav class="mt-5 grid gap-3 sm:grid-cols-2" aria-label="More guides">
                @if ($prev)<a href="{{ route('business.guides', ['slug' => $prev['slug']]) }}" wire:navigate class="{{ $card }} block hover:border-[rgb(var(--nx-teal))]"><span class="flex items-center gap-1 text-xs text-[rgb(var(--nx-text-2))]"><x-icon name="arrow-left" class="h-3 w-3" /> Previous</span><span class="mt-1 block text-sm font-bold text-[rgb(var(--nx-text))]">{{ $prev['title'] }}</span></a>@else<span></span>@endif
                @if ($next)<a href="{{ route('business.guides', ['slug' => $next['slug']]) }}" wire:navigate class="{{ $card }} block text-end hover:border-[rgb(var(--nx-teal))]"><span class="flex items-center justify-end gap-1 text-xs text-[rgb(var(--nx-text-2))]">Next <x-icon name="arrow-right" class="h-3 w-3" /></span><span class="mt-1 block text-sm font-bold text-[rgb(var(--nx-text))]">{{ $next['title'] }}</span></a>@endif
            </nav>
        </article>

        @if ($toc)
            <aside class="hidden lg:block" aria-label="On this page">
                <div class="sticky top-24 {{ $card }} !p-4">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wider text-[rgb(var(--nx-text-2))]">On this page</p>
                    <ul class="space-y-1.5 text-sm">@foreach ($toc as $t)<li><a href="#h-{{ \Illuminate\Support\Str::slug($t) }}" class="text-[rgb(var(--nx-text))] hover:text-[rgb(var(--nx-teal-ink))] hover:underline">{{ $t }}</a></li>@endforeach</ul>
                </div>
            </aside>
        @endif
    </div>
@endif
</div>
</x-nx.page>
</div>

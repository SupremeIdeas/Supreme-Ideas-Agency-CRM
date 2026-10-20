{{-- Public brand links (Brand-Growth Phase 2, MASTER-ONLY). One plain root. The real host is ALWAYS shown beside the label, so a name can never mask the destination. --}}
<div>
@if ($links->isNotEmpty())
    <div class="ns-pg__card ns-ring" x-data="{
            ask: null,
            seen: (() => { try { return JSON.parse(localStorage.getItem('bl:seen') || '[]'); } catch (e) { return []; } })(),
            go(url, host) { if (this.seen.includes(host)) { window.open(url, '_blank', 'noopener,noreferrer'); return; } this.ask = { url, host }; },
            leave() { const h = this.ask.host; if (!this.seen.includes(h)) { this.seen.push(h); try { localStorage.setItem('bl:seen', JSON.stringify(this.seen.slice(-100))); } catch (e) {} } window.open(this.ask.url, '_blank', 'noopener,noreferrer'); this.ask = null; }
         }" @keydown.escape.window="ask = null">
        <h2 class="ns-bh__h" style="margin-bottom:8px"><x-nx.icon name="globe" /> Links</h2>
        <div class="ns-pg__stack ns-pg__stack--sm">
            @foreach ($links as $l)
                <div wire:key="pl-{{ $l->id }}" class="ns-pg__kv" style="gap:10px">
                    <a href="{{ $l->normalized_url }}" rel="noopener noreferrer nofollow ugc" target="_blank" @click.prevent="go(@js($l->normalized_url), @js($l->host))" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:rgb(var(--nx-teal-ink));text-decoration:none">
                        <b>{{ $l->label }}</b> <span class="ns-small">· {{ $l->host }}</span>
                    </a>
                    <button type="button" wire:click="report({{ $l->id }})" wire:confirm="Report this link as unsafe or misleading?" class="ns-small" style="flex:none;text-decoration:underline">Report</button>
                </div>
            @endforeach
        </div>
        @if ($flash)<p class="ns-small" role="status" style="margin-top:8px">{{ $flash }}</p>@endif

        <div x-show="ask" x-cloak x-transition.opacity class="fixed inset-0 z-[90] flex items-center justify-center p-4" role="presentation">
            <div class="absolute inset-0 bg-black/55" @click="ask = null" aria-hidden="true"></div>
            <div x-show="ask" x-trap.noscroll="ask" role="dialog" aria-modal="true" aria-labelledby="bl-leave-t" class="relative w-full max-w-sm rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-2xl">
                <h3 id="bl-leave-t" class="text-base font-semibold text-[rgb(var(--nx-text))]">You're leaving {{ \App\Support\BrandSettings::name() }}</h3>
                <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">This opens <b x-text="ask && ask.host"></b>, a website run by the business. We checked it when it was added, but we do not control it.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="ask = null" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-4 py-2 text-sm">Stay here</button>
                    <button type="button" @click="leave()" class="rounded-full bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white">Continue</button>
                </div>
            </div>
        </div>
    </div>
@endif
</div>

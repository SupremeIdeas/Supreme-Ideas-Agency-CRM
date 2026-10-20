{{-- nx:converted --}}
{{-- Updates (N2N Batch 9, MASTER-ONLY): rings + full-screen viewer. Skin tokens only. --}}
<div class="mx-auto w-full max-w-2xl px-3 py-3">
    @if ($canPost)
        <div class="mb-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">@livewire(\App\Livewire\N2N\StatusComposer::class)</div>
    @endif
    @if (count($rings) === 0 && count($highlights) === 0)
        <div class="mt-10 text-center">
            <img src="{{ asset('images/illustrations/nc-updates.svg') }}" alt="" width="240" height="180" class="mx-auto h-36 w-auto sm:h-40" data-n2n-empty>
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">No updates yet</h2>
            <p class="mx-auto mt-1 max-w-xs text-sm text-[rgb(var(--nx-text-2))]">Updates from Naara Pro brands you are connected with show up here.</p>
            <a href="{{ route('n2n.people') }}" wire:navigate class="ns-cta ns-cta--pill mt-4 inline-flex">Find people</a>
        </div>
    @else
        @if (count($rings) > 0)
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Latest</h2>
        <ul class="flex gap-4 overflow-x-auto pb-2" aria-label="Latest updates">
            @foreach ($rings as $r)
                <li wire:key="ring-{{ $r['brand_id'] }}" class="w-20 shrink-0 text-center">
                    <button type="button" wire:click="view({{ $r['brand_id'] }}, '{{ $r['source'] }}')" wire:loading.attr="disabled" wire:target="view" class="block w-full" aria-label="Open update from {{ $r['name'] }}{{ $r['seen'] ? '' : ' (new)' }}">
                        <span class="mx-auto block h-16 w-16 rounded-full p-[3px] {{ $r['seen'] ? 'bg-[rgb(var(--nx-line-strong))]' : 'bg-[rgb(var(--nx-cta-b))]' }}">
                            @if ($r['source'] === 'status' && $r['cover'] === '')
                                {{-- nx:allow:start a text status ring wears the colour the member picked --}}
                                <span class="block h-full w-full rounded-full border-2 border-[rgb(var(--nx-surface))]" style="background: {{ $r['bg'] }}"></span>
                                {{-- nx:allow:end --}}
                            @else
                                <img src="{{ $r['cover'] }}" alt="" loading="lazy" width="64" height="64" class="h-full w-full rounded-full border-2 border-[rgb(var(--nx-surface))] object-cover">
                            @endif
                        </span>
                        <span class="mt-1 block truncate text-xs font-medium text-[rgb(var(--nx-text))]">{{ $r['name'] }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
        @endif
        @endif
        @if (count($highlights) > 0)
            <h2 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Highlights</h2>
            <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4" aria-label="Highlights from brand profiles">
                @foreach ($highlights as $h)
                    <li wire:key="hl-{{ $h['brand_id'] }}">
                        <button type="button" wire:click="view({{ $h['brand_id'] }}, 'gallery')" wire:loading.attr="disabled" wire:target="view" class="relative block aspect-[3/4] w-full overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))]" aria-label="Open highlights from {{ $h['name'] }}">
                            <img src="{{ $h['cover'] }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            {{-- nx:allow:start the caption sits on a fixed dark scrim over a photo --}}
                            <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-2 text-left text-xs font-medium text-white"><span class="block truncate">{{ $h['name'] }}</span>{{ $h['count'] }} {{ \Illuminate\Support\Str::plural('photo', $h['count']) }}</span>
                            {{-- nx:allow:end --}}
                        </button>
                    </li>
                @endforeach
            </ul>
    @endif

    @if ($open !== null && count($items) > 0)
        <div class="fixed inset-0 z-[70] flex flex-col bg-[rgb(var(--nx-canvas))]" role="dialog" aria-modal="true" aria-label="Update viewer"
             x-data="{ i: 0, n: {{ count($items) }} }" @keydown.escape.window="$wire.close()" @keydown.arrow-right.window="i = Math.min(i + 1, n - 1)" @keydown.arrow-left.window="i = Math.max(i - 1, 0)">
            <div class="flex items-center gap-2 px-3 py-2">
                <div class="flex flex-1 gap-1" aria-hidden="true">
                    @foreach ($items as $k => $it)<span class="h-1 flex-1 rounded-full" :class="i >= {{ $k }} ? 'bg-[rgb(var(--nx-cta-b))]' : 'bg-[rgb(var(--nx-line-strong))]'"></span>@endforeach
                </div>
                <button type="button" wire:click="close" class="nn__iconbtn" aria-label="Close"><x-nx.icon name="x" /></button>
            </div>
            <div class="relative min-h-0 flex-1" @click="const r = $el.getBoundingClientRect(); ($event.clientX - r.left) < r.width / 3 ? i = Math.max(i - 1, 0) : i = Math.min(i + 1, n - 1)">
                @foreach ($items as $k => $it)
                    <figure x-show="i === {{ $k }}" class="flex h-full flex-col items-center justify-center" @if ($k > 0) x-cloak style="display:none" @endif>
                        @if ($it['type'] === 'text')
                            {{-- nx:allow:start the member's chosen backdrop with white text --}}
                            <div class="flex h-full w-full items-center justify-center p-8 text-center text-3xl leading-snug text-white" style="background: {{ $it['bg'] }}; font-family: {{ ['serif' => 'Georgia, serif', 'mono' => 'ui-monospace, monospace', 'rounded' => 'ui-rounded, system-ui', 'script' => 'cursive'][$it['font']] ?? 'system-ui, sans-serif' }}; font-weight: {{ $it['font'] === 'bold' ? 800 : 500 }}">{{ $it['body'] }}</div>
                            {{-- nx:allow:end --}}
                        @elseif ($it['type'] === 'video')
                            <video src="{{ $it['url'] }}" controls playsinline preload="metadata" class="max-h-full max-w-full" x-effect="if (i !== {{ $k }}) $el.pause()"></video>
                        @elseif ($it['type'] === 'voice')
                            <audio src="{{ $it['url'] }}" controls preload="metadata" class="w-4/5 max-w-sm" x-effect="if (i !== {{ $k }}) $el.pause()"></audio>
                        @else
                            <img src="{{ $it['url'] }}" alt="{{ $it['caption'] }}" @if ($k > 1) loading="lazy" @endif class="max-h-full max-w-full object-contain">
                        @endif
                        @if (! empty($it['mentioned_you']))<span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white" data-status-mentioned><x-nx.icon name="at-sign" class="h-3.5 w-3.5" /> Mentioned you</span>@endif
                        @if ($it['caption'])<figcaption class="absolute inset-x-0 bottom-2 mx-auto max-w-md rounded-xl bg-black/55 px-3 py-2 text-center text-sm text-white">{{ $it['caption'] }}</figcaption>@endif
                    </figure>
                @endforeach
            </div>
            <div class="bg-[rgb(var(--nx-surface))] p-2" style="padding-bottom: calc(8px + env(safe-area-inset-bottom))" @click.stop>
                @if ($notice)<p class="px-2 pb-1 text-xs text-[rgb(var(--nx-ok))]" role="status">{{ $notice }}</p>@endif
                @if ($error)<p class="px-2 pb-1 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif
                @foreach ($items as $k => $it)
                    <div x-show="i === {{ $k }}" @if ($k > 0) x-cloak style="display:none" @endif>
                        <div class="flex items-center justify-center gap-1 pb-2">
                            @foreach ($reactions as $e)
                                <button type="button" wire:click="{{ $it['source'] === 'status' ? 'reactStatus' : 'react' }}({{ $it['id'] }}, '{{ $e }}')" aria-label="React {{ $e }}" aria-pressed="{{ $it['reaction'] === $e ? 'true' : 'false' }}"
                                        class="rounded-full px-2 py-1 text-xl {{ $it['reaction'] === $e ? 'bg-[rgb(var(--nx-teal)/0.25)]' : '' }}">{{ $e }}</button>
                            @endforeach
                        </div>
                        <form wire:submit="sendReply({{ $it['id'] }})" class="flex items-center gap-2">
                            <input type="text" wire:model="reply" maxlength="500" placeholder="Reply (a normal message, charged as one)" aria-label="Reply to this update" class="min-w-0 flex-1 rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-4 py-2 text-sm text-[rgb(var(--nx-text))]">
                            <button type="submit" wire:loading.attr="disabled" wire:target="sendReply" class="ns-cta ns-cta--pill ns-cta--sm">Send</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

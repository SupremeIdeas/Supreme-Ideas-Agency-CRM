{{-- nx:converted --}}
{{-- Admin > Handbooks (MASTER-ONLY). Skin tokens only. --}}
<div>
@php($in = 'ns-input')
<x-nx.page class="ns-pg">
    <div class="ns-pg__head">
        <div><h1 class="ns-h1">Handbooks</h1><p class="ns-sub" style="margin:4px 0 0">Travel handbook catalogue, sales and uploads. Revenue is your own income and is read from the ledger.</p></div>
        <span style="display:flex;gap:8px;flex-wrap:wrap"><a href="{{ route('admin.handbooks.refunds') }}" class="ns-cta ns-cta--pill ns-cta--ghost" data-refunds-link>Refund requests @if ($pendingRefunds > 0)({{ $pendingRefunds }})@endif</a><a href="{{ route('admin.handbooks.settings') }}" class="ns-cta ns-cta--pill ns-cta--ghost">Store and unlock settings</a><a href="{{ route('admin.handbooks.new') }}" class="ns-cta ns-cta--pill">New handbook</a></span>
    </div>

    @if ($flash)<div class="ns-pg__card ns-ring" role="status" style="margin-top:12px">{{ $flash }}</div>@endif
    <div class="ns-pg__stats" data-handbook-stats>
        <div class="ns-pg__stat ns-ring"><small>Purchases</small><b>{{ (int) $totals->purchases }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Download upgrades</small><b>{{ (int) $totals->upgrades }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Free unlocks (eSIM, Pro)</small><b>{{ (int) $totals->unlocks }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Revenue (books + audio)</small><b>${{ number_format((float) $totals->revenue + $audioRevenue, 2) }}</b></div>
    </div>

    <div class="hb-filters" style="margin-top:16px">
        <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search title or ID (e.g. GBR-01)" class="{{ $in }}" aria-label="Search">
        <select wire:model.live="country" class="{{ $in }}" aria-label="Country"><option value="">All countries</option>@foreach ($countries as $c)<option>{{ $c }}</option>@endforeach</select>
        <select wire:model.live="series" class="{{ $in }}" aria-label="Series"><option value="">All series</option>@foreach ($seriesList as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
        <select wire:model.live="status" class="{{ $in }}" aria-label="Status"><option value="">Any status</option>@foreach (\App\Models\Handbook::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
        <select wire:model.live="category" class="{{ $in }}" aria-label="Category"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
    </div>

    <div style="overflow:auto;margin-top:14px">
        <table class="ns-table" style="width:100%;font-size:13px" data-handbook-table>
            <thead><tr><th align="left">Cover</th><th align="left">Title</th><th align="left">Country</th><th align="left">Status</th><th align="right">Price</th><th align="center" title="Landing page Popular shelf">Featured</th><th align="center" title="A matching eSIM unlocks it">eSIM</th><th align="center" title="Can be unlocked with a Pro slot">Pro</th><th align="center" title="Can be bought">Buy</th><th></th></tr></thead>
            <tbody>
            @forelse ($books as $b)
                @php($s = $sales[$b->id] ?? null)
                <tr wire:key="hb-{{ $b->id }}">
                    <td style="width:48px">@if ($b->cover_path)<img src="{{ route('admin.handbooks.cover', $b->id) }}" alt="Cover of {{ $b->title }}" width="40" height="53" style="border-radius:6px;object-fit:cover" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'ns-pg__hint',textContent:'No cover'}))">@else<span class="ns-pg__hint">No cover</span>@endif</td>
                    <td><b style="color:rgb(var(--nx-text))">{{ $b->title }}</b>@if ($b->catalogue_id) <span class="ns-pg__hint">{{ $b->catalogue_id }}</span>@endif<span class="ns-pg__hint" style="display:block;margin:2px 0 0">{{ (int) ($s->purchases ?? 0) + (int) ($s->upgrades ?? 0) }} sold · {{ (int) ($s->unlocks ?? 0) }} free · ${{ number_format((float) ($s->revenue ?? 0), 2) }} · verified {{ $b->last_verified_on?->format('M j, Y') ?? 'not set' }}</span></td>
                    <td>{{ $b->country_code }} {{ $b->series_slot }}</td>
                    <td>{{ \App\Models\Handbook::STATUSES[$b->status] ?? $b->status }}@if ($b->status === \App\Models\Handbook::DRAFT)@php($todo = $b->publishProblems())@if ($todo)<span class="ns-pg__hint" style="display:block;max-width:15rem" data-draft-todo title="{{ implode(' ', $todo) }}">Needs: {{ collect($todo)->map(fn ($t) => lcfirst(rtrim(preg_replace('/^(Add|Set|Choose) /', '', $t), '.')))->take(4)->implode(', ') }}</span>@endif @endif</td>
                    <td align="right">${{ number_format((float) $b->list_price_usd, 2) }}</td>
                    @foreach (['is_featured' => 'Featured', 'unlock_via_esim' => 'eSIM unlock', 'unlock_via_pro' => 'Pro unlock', 'allow_purchase' => 'Buying'] as $f => $lab)
                        <td align="center"><button type="button" wire:click="toggle({{ $b->id }}, '{{ $f }}')" wire:loading.attr="disabled" role="switch" aria-checked="{{ $b->{$f} ? 'true' : 'false' }}" aria-label="{{ $lab }} for {{ $b->title }}" class="hb-tgl {{ $b->{$f} ? 'is-on' : '' }}"><i></i></button></td>
                    @endforeach
                    <td align="right" style="white-space:nowrap"><div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap"><button type="button" wire:click="togglePublish({{ $b->id }})" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">{{ $b->status === 'published' ? 'Unpublish' : 'Publish' }}</button> <a href="{{ route('admin.handbooks.edit', $b->id) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Edit</a> <a href="{{ route('admin.handbooks.manuscript', $b->id) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" data-open-studio>Manuscript</a> @if ($b->epub_path)<a href="{{ route('admin.handbooks.preview', $b->id) }}" target="_blank" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Preview</a> <a href="{{ route('admin.handbooks.download', [$b->id, 'epub']) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" data-hb-download="epub">EPUB</a>@endif @if ($b->pdf_path)<a href="{{ route('admin.handbooks.download', [$b->id, 'pdf']) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" data-hb-download="pdf">PDF</a>@endif</div></td>
                </tr>
            @empty
                <tr><td colspan="9" class="ns-pg__hint">No handbooks match. Create one or run <code>php artisan handbooks:import</code>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-nx.page>
</div>

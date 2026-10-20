{{-- nx:converted --}}
{{-- Brand Connect for the owner (MASTER-ONLY): connections, accept or decline, welcome message. Skin tokens only. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('brand.manage') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> Brand dashboard</a>
            <h1 class="ns-h1" style="margin-top:6px">Connections</h1>
            <p class="ns-sub" style="margin:4px 0 0">People who connected with {{ $brand->brand_name }}, and how you welcome them.</p>
        </div>
    </div>

    @if ($flash)<div class="ns-pg__card ns-ring" role="status" style="margin-top:12px">{{ $flash }}</div>@endif
    @if ($error)<div class="ns-pg__card ns-ring" role="alert" style="margin-top:12px"><span class="ns-pg__fielderr">{{ $error }}</span></div>@endif

    <form wire:submit="save" class="ns-pg__card ns-ring" style="margin-top:16px" data-connect-settings>
        <h2 class="ns-pg__h2" style="margin-bottom:8px">How people connect</h2>
        <div class="ns-seg ns-small" role="group" aria-label="Connection mode">
            @foreach (\App\Services\Brands\BrandConnectService::MODES as $k => $label)
                <button type="button" wire:click="$set('mode', '{{ $k }}')" class="{{ $mode === $k ? 'is-on' : '' }}" aria-pressed="{{ $mode === $k ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-top:14px;color:rgb(var(--nx-text))"><input type="checkbox" wire:model.live="introEnabled"> Send a welcome message when someone connects</label>
        @if ($introEnabled)
            <label class="ns-pg__hint" for="intro-text" style="display:block;margin:10px 0 4px">Welcome message ({{ mb_strlen($introText) }}/240)</label>
            <textarea id="intro-text" wire:model.live.debounce.300ms="introText" rows="3" maxlength="240" class="ns-input" placeholder="Welcome! Thanks for connecting. Ask us anything here."></textarea>
            <p class="ns-pg__hint" style="margin:6px 0 0">Sent from you as a Naara chat message, so no links. It costs <b style="color:rgb(var(--nx-text))">{{ $cost }}</b> NaaraCredit each, paid from your balance (you have {{ $available }}). No credit means no message; your wallet is never used. Each person gets it once, and they can accept or decline it like any message request.</p>
        @endif
        <button type="submit" class="ns-cta ns-cta--pill" style="margin-top:14px" wire:loading.attr="disabled" wire:target="save">Save</button>
    </form>

    <div class="ns-seg ns-small" role="tablist" style="margin-top:20px">
        @foreach (['pending' => 'Waiting', 'accepted' => 'Connected', 'declined' => 'Declined'] as $k => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $k }}')" class="{{ $tab === $k ? 'is-on' : '' }}" aria-selected="{{ $tab === $k ? 'true' : 'false' }}">{{ $label }} ({{ (int) ($counts[$k] ?? 0) }})</button>
        @endforeach
    </div>

    <ul style="list-style:none;margin:12px 0 0;padding:0;display:grid;gap:10px" data-connect-list>
        @forelse ($rows as $r)
            <li class="ns-pg__card ns-ring" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap" wire:key="bcr-{{ $r->id }}">
                <x-nx.avatar :name="$r->user?->name ?: '?'" :size="40" />
                <div style="flex:1;min-width:9rem">
                    <b style="color:rgb(var(--nx-text))">{{ $r->user?->name ?: 'Member' }}</b>@if ($r->user?->username) <span class="ns-pg__hint">&#64;{{ $r->user->username }}</span>@endif
                    <div class="ns-pg__hint" style="margin:2px 0 0">{{ $r->created_at->diffForHumans() }}@if ($r->status === 'accepted' && $r->intro_status !== 'none') · welcome: {{ ['queued' => 'on its way', 'sent' => 'sent', 'no_credit' => 'not sent (no credit)', 'blocked' => 'not delivered', 'failed' => 'failed'][$r->intro_status] ?? $r->intro_status }}@endif</div>
                </div>
                @if ($r->status === 'pending')
                    <button type="button" wire:click="accept({{ $r->id }})" wire:loading.attr="disabled" class="ns-cta ns-cta--pill">Accept</button>
                    <button type="button" wire:click="decline({{ $r->id }})" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--ghost">Decline</button>
                @endif
            </li>
        @empty
            <li class="ns-pg__hint" style="margin:0">Nobody here yet.</li>
        @endforelse
    </ul>
</x-nx.page>
</div>

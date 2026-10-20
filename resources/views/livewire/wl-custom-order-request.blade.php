{{-- nx:converted --}}
{{-- White Label custom order request (Funnel Phase 8, MASTER-ONLY). Skin tokens only. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <h1 class="ns-h1">Custom White Label</h1>
            <p class="ns-sub" style="margin:4px 0 0">Need more than a fixed plan? Tell us what you want and we will send a priced quote. Nothing is charged until you accept it.</p>
        </div>
    </div>

    @if ($error)<div class="ns-pg__callout" role="alert" style="color:rgb(var(--nx-bad))"><x-nx.icon name="alert" /><div><p style="margin:0">{{ $error }}</p></div></div>@endif

    @if ($orders->isNotEmpty())
        <div class="ns-pg__card ns-ring" data-wlco-list>
            <b>Your requests</b>
            <ul style="list-style:none;margin:8px 0 0;padding:0;display:grid;gap:6px">
                @foreach ($orders as $o)
                    <li wire:key="o-{{ $o->id }}"><a href="{{ route('wl-custom-orders.show', $o->reference) }}" wire:navigate class="ns-sub" style="display:flex;justify-content:space-between;gap:8px;color:rgb(var(--nx-text))"><span>{{ $o->reference }} · {{ $o->brand_name }}</span><span class="ns-nt__chip">{{ ucfirst($o->status) }}</span></a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="submit" class="ns-pg__card ns-ring" data-wlco-form style="display:grid;gap:12px">
        <b>What do you need?</b>
        <div>
            <label class="ns-sub" for="wlco-brand">Your brand name</label>
            <input id="wlco-brand" type="text" wire:model="brand_name" maxlength="120" class="ns-input" autocomplete="organization">
            @error('brand_name')<p class="ns-sub" role="alert" style="color:rgb(var(--nx-bad));margin:4px 0 0">{{ $message }}</p>@enderror
        </div>
        <fieldset style="border:0;padding:0;margin:0">
            <legend class="ns-sub">Tick everything that applies</legend>
            <div style="display:grid;gap:6px;margin-top:6px">
                @foreach ($moduleOptions as $key => $label)
                    <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" wire:model="modules" value="{{ $key }}"><span>{{ $label }}</span></label>
                @endforeach
            </div>
            @error('modules')<p class="ns-sub" role="alert" style="color:rgb(var(--nx-bad));margin:4px 0 0">{{ $message }}</p>@enderror
        </fieldset>
        <div>
            <label class="ns-sub" for="wlco-brief">Describe what you want built</label>
            <textarea id="wlco-brief" wire:model="brief" rows="6" maxlength="4000" class="ns-input" placeholder="Who are your customers, which countries, what must be different from the standard platform?"></textarea>
            @error('brief')<p class="ns-sub" role="alert" style="color:rgb(var(--nx-bad));margin:4px 0 0">{{ $message }}</p>@enderror
        </div>
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
            <div><label class="ns-sub" for="wlco-budget">Budget (optional)</label>
                <select id="wlco-budget" wire:model="budget_band" class="ns-input"><option value="">Choose</option>@foreach ($budgets as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div><label class="ns-sub" for="wlco-timeline">Timeline (optional)</label>
                <select id="wlco-timeline" wire:model="timeline" class="ns-input"><option value="">Choose</option>@foreach ($timelines as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        </div>
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
            <div><label class="ns-sub" for="wlco-email">Contact email</label>
                <input id="wlco-email" type="email" wire:model="contact_email" class="ns-input" autocomplete="email">
                @error('contact_email')<p class="ns-sub" role="alert" style="color:rgb(var(--nx-bad));margin:4px 0 0">{{ $message }}</p>@enderror</div>
            <div><label class="ns-sub" for="wlco-wa">WhatsApp (optional)</label>
                <input id="wlco-wa" type="tel" wire:model="contact_whatsapp" class="ns-input" autocomplete="tel">
                @error('contact_whatsapp')<p class="ns-sub" role="alert" style="color:rgb(var(--nx-bad));margin:4px 0 0">Use digits, spaces and + only.</p>@enderror</div>
        </div>
        <div><label class="ns-sub" for="wlco-link">A link that shows what you like (optional)</label>
            <input id="wlco-link" type="url" wire:model="reference_link" class="ns-input" placeholder="https://">
            @error('reference_link')<p class="ns-sub" role="alert" style="color:rgb(var(--nx-bad));margin:4px 0 0">{{ $message }}</p>@enderror</div>
        <div><button type="submit" wire:loading.attr="disabled" wire:target="submit" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="submit">Request a quote</span><span wire:loading wire:target="submit">Sending...</span></button></div>
    </form>
</x-nx.page>
</div>

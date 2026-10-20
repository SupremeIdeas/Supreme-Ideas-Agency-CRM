{{-- nx:converted --}}
{{-- White Label purchase wizard (Funnel Phase 3, MASTER-ONLY). Skin tokens only. One question at a time; every step autosaves; nothing here pretends a payment exists. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <h1 class="ns-h1">Set up your White Label</h1>
            <p class="ns-sub" style="margin:4px 0 0">Step {{ $step }} of {{ count($keys) }}. Your answers save as you go.</p>
        </div>
        <div class="ns-pg__card ns-ring" style="padding:10px 14px;margin:0;display:flex;align-items:center;gap:10px" data-wl-funnel-id>
            <span><small class="ns-sub" style="display:block">Your White Label ID</small><b style="letter-spacing:.04em">{{ $inst->public_code }}</b></span>
            <button type="button" x-data="{ done: false }" @click="navigator.clipboard.writeText(@js($inst->public_code)).then(() => { done = true; setTimeout(() => done = false, 1800) })" class="ns-cta ns-cta--pill ns-cta--sm ns-cta--ghost"><span x-show="!done">Copy</span><span x-show="done" x-cloak>Copied</span></button>
        </div>
    </div>

    <ol style="display:flex;gap:6px;list-style:none;padding:0;margin:14px 0" aria-label="Progress">
        @foreach ($keys as $i => $k)
            <li style="flex:1;height:6px;border-radius:99px;background:{{ $i + 1 <= $step ? 'rgb(var(--nx-teal))' : 'rgb(var(--nx-line-strong))' }}"></li>
        @endforeach
    </ol>

    @if ($notice)<div class="ns-pg__callout ns-pg__callout--ok" role="status"><x-nx.icon name="check" /><div><p style="margin:0">{{ $notice }}</p></div></div>@endif

    <div class="ns-pg__card ns-ring" data-wl-step="{{ $keys[$step - 1] }}">
        {{-- The guide speaks first, then asks. --}}
        @php($ask = ['brand' => ['What should we call your White Label?', 'This is the name we use when we set up your White Label on our hosting server.'], 'palette' => ['Pick your colours.', 'Choose a palette, or make your own. You can fine tune it after launch.'], 'hosting' => ['Where should it live?', null], 'contact' => ['How should we reach you?', 'We only message you about this purchase and your setup.'], 'billing' => ['Who is this for?', 'This goes on your invoice and receipt.'], 'plan' => ['Which plan fits?', 'Prices are live from our catalogue.'], 'review' => ['Here is everything. Take a look.', null]][$keys[$step - 1]])
        <img src="{{ asset('images/illustrations/wl-'.$keys[$step - 1].'.svg') }}" alt="" width="240" height="180" class="mx-auto mb-3 h-36 w-auto sm:h-40" data-wl-step-art>
        <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:14px">
            <x-nia-tile icon="sparkles" />
            <div><b style="color:rgb(var(--nx-text))">{{ $ask[0] }}</b>@if ($ask[1])<p class="ns-sub" style="margin:2px 0 0">{{ $ask[1] }}</p>@endif</div>
        </div>

        @if ($keys[$step - 1] === 'brand')
            <label class="ns-pg__lbl" for="wl-brand">Brand name</label>
            <input id="wl-brand" type="text" wire:model="brand_name" maxlength="60" autocomplete="organization" class="ns-input" placeholder="For example Ada Connect">
            @error('brand_name')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror

        @elseif ($keys[$step - 1] === 'palette')
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px">
                @foreach ($palettes as $p)
                    <label class="ns-pg__card ns-ring" style="margin:0;padding:10px;cursor:pointer;{{ $palette_key === $p['slug'] ? 'outline:2px solid rgb(var(--nx-teal))' : '' }}">
                        <input type="radio" wire:model.live="palette_key" value="{{ $p['slug'] }}" class="sr-only">
                        <span style="display:flex;gap:4px"><i style="width:26px;height:26px;border-radius:50%;background:{{ $p['primary'] }}"></i><i style="width:26px;height:26px;border-radius:50%;background:{{ $p['accent'] ?? $p['primary'] }}"></i></span>
                        <small style="display:block;margin-top:6px;color:rgb(var(--nx-text))">{{ $p['name'] }}</small>
                    </label>
                @endforeach
                <label class="ns-pg__card ns-ring" style="margin:0;padding:10px;cursor:pointer;{{ $palette_key === 'custom' ? 'outline:2px solid rgb(var(--nx-teal))' : '' }}">
                    <input type="radio" wire:model.live="palette_key" value="custom" class="sr-only"><b style="color:rgb(var(--nx-text))">Custom palette</b><small class="ns-sub" style="display:block">Choose your own colours</small>
                </label>
            </div>
            @if ($palette_key === 'custom')
                <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:12px">
                    <label class="ns-pg__lbl">Main colour <input type="color" wire:model.live="primary" aria-label="Main colour"></label>
                    <label class="ns-pg__lbl">Accent colour <input type="color" wire:model.live="accent" aria-label="Accent colour"></label>
                </div>
                @if ($contrast !== null && $contrast < 4.5)<p class="ns-sub" style="margin-top:8px" role="status">White text on this main colour is hard to read (contrast {{ $contrast }}, 4.5 is the guideline). Pick a darker main colour for buttons and headers.</p>@endif
            @endif
            @error('palette_key')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror @error('primary')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror

        @elseif ($keys[$step - 1] === 'hosting')
            <label class="ns-pg__card ns-ring" style="display:flex;gap:10px;margin:0 0 10px;cursor:pointer"><input type="radio" wire:model.live="hosting_choice" value="supreme_ideas_server"><span><b style="color:rgb(var(--nx-text))">Supreme Ideas Agency hosting (recommended)</b><small class="ns-sub" style="display:block">{{ $hostingTerms }}</small></span></label>
            <label class="ns-pg__card ns-ring" style="display:flex;gap:10px;margin:0;cursor:pointer"><input type="radio" wire:model.live="hosting_choice" value="own_vps"><span><b style="color:rgb(var(--nx-text))">My own server</b><small class="ns-sub" style="display:block">A VPS you control. After payment we ask for its details in the app.</small></span></label>
            @if ($hosting_choice !== 'supreme_ideas_server')
                <label style="display:flex;gap:10px;margin-top:12px;align-items:flex-start"><input type="checkbox" wire:model="disclaimer"><span class="ns-sub">I understand this platform has heavy server-side requirements and may not run on ordinary shared hosting. A VPS is recommended.</span></label>
                @error('disclaimer')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror
            @endif

        @elseif ($keys[$step - 1] === 'contact')
            <label class="ns-pg__lbl" for="wl-pref">Preferred way to be contacted</label>
            <select id="wl-pref" wire:model.live="contact_preference" class="ns-input"><option value="notification">Naara notification</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option></select>
            <label class="ns-pg__lbl" for="wl-wa" style="margin-top:10px">WhatsApp number (optional)</label>
            <input id="wl-wa" type="tel" wire:model="whatsapp" class="ns-input" placeholder="+2348012345678" autocomplete="tel">
            @error('whatsapp')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror
            @if (trim($whatsapp) !== '')<label style="display:flex;gap:10px;margin-top:10px;align-items:flex-start"><input type="checkbox" wire:model="whatsapp_consent"><span class="ns-sub">Yes, you may contact me on WhatsApp about this purchase.</span></label>@error('whatsapp_consent')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror @endif

        @elseif ($keys[$step - 1] === 'billing')
            <div style="display:grid;gap:10px">
                <div><label class="ns-pg__lbl" for="wl-bn">Name or company</label><input id="wl-bn" type="text" wire:model="billing_name" class="ns-input" autocomplete="organization">@error('billing_name')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="ns-pg__lbl" for="wl-ba">Billing address</label><input id="wl-ba" type="text" wire:model="billing_address" class="ns-input" autocomplete="street-address">@error('billing_address')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:10px"><div><label class="ns-pg__lbl" for="wl-bc">Country (2 letters)</label><input id="wl-bc" type="text" wire:model="billing_country" maxlength="2" class="ns-input" style="text-transform:uppercase">@error('billing_country')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                    <div><label class="ns-pg__lbl" for="wl-bt">Tax or VAT ID (optional)</label><input id="wl-bt" type="text" wire:model="billing_tax_id" class="ns-input"></div></div>
                <div><label class="ns-pg__lbl" for="wl-be">Billing email</label><input id="wl-be" type="email" wire:model="billing_email" class="ns-input" autocomplete="email">@error('billing_email')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
            </div>

        @elseif ($keys[$step - 1] === 'plan')
            <div style="display:grid;gap:10px">
                @foreach ($plans as $p)
                    <label class="ns-pg__card ns-ring" style="display:flex;gap:10px;margin:0;cursor:pointer;{{ $plan_key === $p->key ? 'outline:2px solid rgb(var(--nx-teal))' : '' }}">
                        <input type="radio" wire:model.live="plan_key" value="{{ $p->key }}">
                        <span style="flex:1"><b style="color:rgb(var(--nx-text))">{{ $p->name }}@if ($p->key === $inst->plan_key_at_landing) <small class="ns-sub">(your choice on the landing page)</small>@endif</b><small class="ns-sub" style="display:block">{{ $p->tagline }}</small></span>
                        <b style="color:rgb(var(--nx-text))">${{ number_format((float) $p->price_usd) }}</b>
                    </label>
                @endforeach
            </div>
            @error('plan_key')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror
            @if (\App\Services\WhiteLabel\Funnel\WlCustomOrders::enabled())
                <p class="ns-sub" style="margin-top:12px" data-wlco-link>Need something none of these cover? <a href="{{ route('wl-custom-orders') }}" wire:navigate>Request a custom quote</a>.</p>
            @endif

        @else
            @php($d = $review['draft'])
            <dl style="display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:0;color:rgb(var(--nx-text))">
                <dt class="ns-sub">Brand</dt><dd>{{ $d['brand_name'] ?? 'not set' }}</dd>
                <dt class="ns-sub">Palette</dt><dd>{{ ($d['palette_key'] ?? '') === 'custom' ? 'Custom ('.($d['primary'] ?? '').' / '.($d['accent'] ?? '').')' : ($d['palette_key'] ?? 'not set') }}</dd>
                <dt class="ns-sub">Hosting</dt><dd>{{ ($d['hosting_choice'] ?? '') === 'supreme_ideas_server' ? 'Supreme Ideas Agency hosting' : (isset($d['hosting_choice']) ? 'Your own server' : 'not set') }}</dd>
                <dt class="ns-sub">Contact</dt><dd>{{ $d['contact_preference'] ?? 'not set' }}@if (! empty($d['whatsapp'])) · {{ $d['whatsapp'] }}@endif</dd>
                <dt class="ns-sub">Billing</dt><dd>{{ $d['billing_name'] ?? 'not set' }}@if (! empty($d['billing_country'])), {{ $d['billing_country'] }}@endif</dd>
                <dt class="ns-sub">Plan</dt><dd>{{ $review['plan']?->name ?? 'not set' }}@if ($review['price_usd'] !== null) · <b>${{ number_format($review['price_usd']) }}</b>@endif</dd>
            </dl>
            @if ($review['balance_to_extended'])<p class="ns-sub" style="margin-top:10px">If you later upgrade to Extended, the balance is ${{ number_format($review['balance_to_extended']) }}. It is worked out from the live plan prices.</p>@endif
            @if ($review['missing'])<p class="ns-sub" style="margin-top:10px;color:rgb(var(--nx-bad))" role="alert">Still to answer: {{ implode(', ', $review['missing']) }}.</p>@endif
            @if (\App\Services\WhiteLabel\Funnel\WlFunnelManualPayments::enabled() && ! $review['missing'])
                <div style="margin-top:14px"><a href="{{ route('wl-funnel.pay') }}" wire:navigate class="ns-cta ns-cta--pill" data-wl-pay-now>Pay now</a></div>
            @else
            <div class="ns-pg__callout" style="margin-top:14px"><x-nx.icon name="info" /><div><b>Payment is not open yet.</b><p style="margin:2px 0 0">Your answers are saved under {{ $inst->public_code }}. Save the draft and we will tell you the moment you can pay.</p></div></div>
            @endif
        @endif

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;align-items:center">
            @if ($step > 1)<button type="button" wire:click="back" class="ns-cta ns-cta--pill ns-cta--ghost">Back</button>@endif
            @if ($step < count($keys))
                <button type="button" wire:click="next" wire:loading.attr="disabled" wire:target="next" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="next">Next</span><span wire:loading wire:target="next">Saving...</span></button>
            @endif
            <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" class="ns-cta ns-cta--pill ns-cta--ghost" style="margin-inline-start:auto">Save and continue later</button>
        </div>
    </div>

    @if ($inst->draft_step > 1)
        <p class="ns-sub" style="margin-top:12px">Edit an earlier answer:
            @foreach ($keys as $i => $k)@if ($i + 1 < $step || ($i + 1 <= $inst->draft_step && $i + 1 !== $step))<button type="button" wire:click="goTo({{ $i + 1 }})" class="ns-linkink" style="text-decoration:underline;margin-inline-end:8px">{{ ucfirst($k) }}</button>@endif @endforeach
        </p>
    @endif
</x-nx.page>
</div>

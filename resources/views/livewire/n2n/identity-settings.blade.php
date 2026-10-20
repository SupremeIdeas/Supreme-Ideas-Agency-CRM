{{-- nx:converted --}}
{{-- N2N identity and discovery settings (MASTER-ONLY). Skin tokens only. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('account') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> Account</a>
            <h1 class="ns-h1" style="margin-top:6px">{{ __('n2n.identity.title') }}</h1>
            <p class="ns-sub" style="margin:4px 0 0">{{ __('n2n.identity.intro') }}</p>
        </div>
        <img src="{{ asset('images/illustrations/nc-identity.svg') }}" alt="" width="240" height="180" class="hidden h-28 w-auto sm:block" data-n2n-art>
    </div>
    <x-n2n.section-tabs />

    @if ($message)<div class="ns-pg__card ns-ring" role="status" style="margin-top:12px">{{ $message }}</div>@endif
    @if ($error)<div class="ns-pg__card ns-ring" role="alert" style="margin-top:12px"><span class="ns-pg__fielderr">{{ $error }}</span></div>@endif

    <div class="ns-pg__card ns-ring" style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('n2n.chat') }}" wire:navigate class="ns-cta ns-cta--pill">Open Naara chat</a>
        <a href="{{ route('n2n.credits') }}" wire:navigate class="ns-cta ns-cta--pill">NaaraCredit</a>
        <a href="{{ route('n2n.gestures') }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--ghost">Chat gestures</a>
    </div>

    <div class="ns-pg__card ns-ring">
        <span class="ns-pg__lbl">{{ __('n2n.identity.naara_id') }}</span>
        <div x-data="{ copied: false }" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:4px">
            <b class="ns-pg__bignum" style="font-size:1.4rem;letter-spacing:.04em" id="n2n-id">{{ $naaraId }}</b>
            <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" x-on:click="navigator.clipboard.writeText('{{ $naaraId }}').then(() => { copied = true; setTimeout(() => copied = false, 1600) })">
                <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
            </button>
        </div>
        <p class="ns-sub" style="margin:6px 0 0">{{ __('n2n.identity.naara_id_help') }}</p>
    </div>

    <form wire:submit="saveUsername" class="ns-pg__card ns-ring">
        <label class="ns-pg__lbl" for="n2n-username">{{ __('n2n.identity.username') }}</label>
        <div style="display:flex;gap:8px;margin-top:4px">
            <input id="n2n-username" type="text" wire:model="username" maxlength="20" autocomplete="off" autocapitalize="none" spellcheck="false" class="ns-input" placeholder="yourname">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveUsername" class="ns-cta ns-cta--pill">{{ __('n2n.identity.save') }}</button>
        </div>
    </form>

    <div class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:6px">{{ __('n2n.identity.phone') }}</h2>
        <p class="ns-sub" style="margin:0 0 10px">{{ __('n2n.identity.phone_help') }}</p>
        @if ($linked)
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><span class="ns-st ns-st--ok">Verified</span>
                <button type="button" wire:click="unlinkPhone" wire:confirm="Remove your verified number?" class="ns-pg__act ns-pg__act--link">{{ __('n2n.identity.unlink') }}</button></div>
        @endif
        <form wire:submit="sendCode" style="display:flex;gap:8px;margin-top:10px">
            <input type="tel" wire:model="phone" autocomplete="tel" class="ns-input" placeholder="+234 801 234 5678" aria-label="Phone number">
            <button type="submit" wire:loading.attr="disabled" wire:target="sendCode" class="ns-cta ns-cta--pill">{{ __('n2n.identity.send_code') }}</button>
        </form>
        @if ($codeSent)
            <form wire:submit="verifyCode" style="display:flex;gap:8px;margin-top:10px">
                <input type="text" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="ns-input" placeholder="123456" aria-label="Verification code">
                <button type="submit" wire:loading.attr="disabled" wire:target="verifyCode" class="ns-cta ns-cta--pill">{{ __('n2n.identity.verify') }}</button>
            </form>
        @endif
    </div>

    <form wire:submit="saveDiscovery" class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:8px">{{ __('n2n.identity.discovery') }}</h2>
        <label style="display:flex;gap:8px;margin:6px 0"><input type="checkbox" wire:model="byPhone"> <span>{{ __('n2n.identity.by_phone') }}</span></label>
        <label style="display:flex;gap:8px;margin:6px 0"><input type="checkbox" wire:model="byEmail"> <span>{{ __('n2n.identity.by_email') }}</span></label>
        <label style="display:flex;gap:8px;margin:6px 0"><input type="checkbox" wire:model="hideMe"> <span>{{ __('n2n.identity.hide_me') }}</span></label>
        <button type="submit" wire:loading.attr="disabled" wire:target="saveDiscovery" class="ns-cta ns-cta--pill" style="margin-top:8px">{{ __('n2n.identity.save') }}</button>
    </form>

    <form wire:submit="savePrivacy" class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:8px">Messaging privacy</h2>
        <label class="ns-pg__lbl" for="n2n-who">Who can message you</label>
        <select id="n2n-who" wire:model="whoCanMessage" class="ns-input">
            <option value="everyone">Everyone (new people arrive as a request)</option>
            <option value="connections">Only my contacts</option>
            <option value="nobody">Nobody</option>
        </select>
        <label style="display:flex;gap:8px;margin:10px 0 6px"><input type="checkbox" wire:model="readReceipts"> <span>Let people see when I have read their message</span></label>
        <label style="display:flex;gap:8px;margin:10px 0 6px"><input type="checkbox" wire:model="privateVisits"> <span>Do not show my name when I visit a brand profile (I am still counted)</span></label>
        <label style="display:flex;gap:8px;margin:6px 0"><input type="checkbox" wire:model="typingIndicator"> <span>Show when I am typing</span></label>
        <label style="display:flex;gap:8px;margin:6px 0"><input type="checkbox" wire:model="hidePresence"> <span>Hide my last seen and online status (I will not see anyone else's either)</span></label>
        <button type="submit" wire:loading.attr="disabled" wire:target="savePrivacy" class="ns-cta ns-cta--pill" style="margin-top:8px">{{ __('n2n.identity.save') }}</button>
    </form>

    <form wire:submit="importContacts" class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:6px">{{ __('n2n.identity.import') }}</h2>
        <p class="ns-sub" style="margin:0 0 10px">{{ __('n2n.identity.import_help') }} (up to {{ $importMax }} contacts.)</p>
        <input type="file" wire:model="contactsFile" accept=".vcf,.csv,.txt" class="ns-input" aria-label="Contacts file">
        @error('contactsFile')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
        <label style="display:flex;gap:8px;margin:10px 0"><input type="checkbox" wire:model="consent"> <span>{{ __('n2n.identity.consent') }}</span></label>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <button type="submit" wire:loading.attr="disabled" wire:target="importContacts,contactsFile" class="ns-cta ns-cta--pill">{{ __('n2n.identity.import') }}</button>
            @if ($importedCount > 0)
                <button type="button" wire:click="deleteImported" wire:confirm="Delete all imported contacts?" class="ns-pg__act ns-pg__act--link">{{ __('n2n.identity.delete_imported') }} ({{ $importedCount }})</button>
            @endif
        </div>
        @if ($knownCount > 0)<p class="ns-sub" style="margin:10px 0 0">{{ $knownCount }} of your contacts are on Naara.</p>@endif
    </form>
</x-nx.page>
</div>

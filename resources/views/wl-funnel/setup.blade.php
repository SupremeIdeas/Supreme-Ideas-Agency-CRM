{{-- nx:converted --}}
{{-- Setup details after payment (MASTER-ONLY). A plain form: a server password is never held in client-side state. Skin tokens only. --}}
<x-layouts.customer>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head"><div><h1 class="ns-h1">Set up {{ $license->brand_name }}</h1><p class="ns-sub" style="margin:4px 0 0">Reference {{ $inst->public_code }}</p></div></div>
    @if (session('status'))<div class="ns-pg__callout ns-pg__callout--ok" role="status"><x-nx.icon name="check" /><div><p style="margin:0">{{ session('status') }}</p></div></div>@endif
    <form method="POST" action="{{ route('wl-funnel.setup.store') }}" class="ns-pg__card ns-ring" style="display:grid;gap:12px" autocomplete="off">
        @csrf
        <label class="ns-sub">WhatsApp number (with country code)<input type="tel" name="whatsapp_number" value="{{ old('whatsapp_number', $intake->whatsapp_number ?? ($d['whatsapp'] ?? '')) }}" class="ns-input" style="width:100%" placeholder="+2348012345678" required></label>
        @error('whatsapp_number')<small style="color:rgb(var(--nx-bad))" role="alert">{{ $message }}</small>@enderror
        @if ($own)
            <div class="ns-pg__callout" role="note"><x-nx.icon name="info" /><div><b>Use a temporary, limited account.</b><p style="margin:2px 0 0">Create a login just for us, give it only what setup needs, and change or delete it when we finish. Your details are stored encrypted, only an administrator can reveal them (and every reveal is logged), and they are erased after your setup is complete.</p></div></div>
            <label class="ns-sub">Server address or control-panel URL<input type="text" name="hosting_host" value="{{ old('hosting_host') }}" class="ns-input" style="width:100%" required></label>
            <label class="ns-sub">Username<input type="text" name="hosting_username" value="{{ old('hosting_username') }}" class="ns-input" style="width:100%" required></label>
            <label class="ns-sub">Password or SSH key<input type="password" name="hosting_password" class="ns-input" style="width:100%" required autocomplete="new-password"></label>
            <label class="ns-sub">Notes (port, panel type, anything we should know)<textarea name="hosting_notes" rows="3" class="ns-input" style="width:100%">{{ old('hosting_notes') }}</textarea></label>
            @foreach (['hosting_host', 'hosting_username', 'hosting_password', 'hosting_notes'] as $f)@error($f)<small style="color:rgb(var(--nx-bad))" role="alert">{{ $message }}</small>@enderror @endforeach
        @else
            <p class="ns-sub" style="margin:0">You chose Supreme Ideas hosting, so we never ask for a server login.</p>
        @endif
        <label class="ns-sub">Anything else we should know (optional)<textarea name="additional_notes" rows="3" class="ns-input" style="width:100%">{{ old('additional_notes', $intake->additional_notes ?? '') }}</textarea></label>
        <button type="submit" class="ns-cta ns-cta--pill" onclick="this.disabled=true;this.form.submit()">{{ $intake ? 'Update my details' : 'Send my details' }}</button>
    </form>
</x-nx.page>
</x-layouts.customer>

{{-- nx:converted --}}
{{-- Quick "Naara chat" button (N2N, MASTER-ONLY): shown only on the routes the admin chose, never on the dashboard home. Dismissible for a day. --}}
@php($unread = \App\Support\N2N\FloatingChat::unread())
<div x-data="{ gone: false, key: 'n2n_fab_hidden_until' }" x-init="gone = (() => { try { return Number(localStorage.getItem(key) || 0) > Date.now(); } catch (e) { return false; } })()" x-show="!gone" x-cloak class="nn-fab" data-dock="n2n" data-dock-label="Naara chat" data-dock-layer="10" data-dock-anchor="left:0" style="display:none">
    <a href="{{ route('n2n.chat') }}" wire:navigate aria-label="Open Naara chat{{ $unread > 0 ? ', '.$unread.' unread' : '' }}" class="nn-fab__btn">
        <img src="{{ \App\Support\N2N\N2NSettings::logoUrl() }}" alt="" width="40" height="40" loading="lazy">
        @if ($unread > 0)<b class="nn__badge nn__badge--dot">{{ $unread > 9 ? '9+' : $unread }}</b>@endif
    </a>
    <button type="button" class="nn-fab__x" aria-label="Hide the Naara chat button for today" x-on:click="gone = true; try { localStorage.setItem(key, String(Date.now() + {{ \App\Support\N2N\FloatingChat::dismissHours() }} * 3600000)); } catch (e) {}"><x-nx.icon name="x" /></button>
</div>

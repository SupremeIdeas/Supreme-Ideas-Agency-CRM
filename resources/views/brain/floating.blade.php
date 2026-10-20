{{-- nx:converted --}}
{{-- Floating NaaraBrain button (MASTER-ONLY). Where it sits is admin-owned (Admin > NaaraBrain): side, height above the bottom nav, and which sections show it. Dismissible for a while.
     Self-contained: its own scoped <style>, skin tokens only, so no shared stylesheet carries Brain. --}}
@php($brnSide = \App\Support\Brain\BrainSettings::widgetSide())
<div x-data="{ gone: false, key: 'brain_fab_hidden_until' }" x-init="gone = (() => { try { return Number(localStorage.getItem(key) || 0) > Date.now(); } catch (e) { return false; } })()" x-show="!gone" x-cloak
     class="brn-fab brn-fab--{{ $brnSide }}" data-dock="brain" data-dock-label="NaaraBrain" data-dock-layer="40" data-dock-anchor="{{ $brnSide }}:{{ $brnSide === 'left' ? 64 : 192 }}" style="display:none; --brn-fab-b: {{ \App\Support\Brain\BrainSettings::widgetOffset() }}px" data-brain-widget="{{ $brnSide }}">
    <a href="{{ route('brain.chat') }}" wire:navigate aria-label="{{ __('brain.open') }}" title="{{ __('brain.name') }}" class="brn-fab__btn">
        <img src="{{ \App\Support\Brain\BrainSettings::logo('mark') }}" alt="" width="38" height="38" loading="lazy" draggable="false">
    </a>
    <button type="button" class="brn-fab__x" aria-label="{{ __('brain.hide_widget') }}"
            x-on:click="gone = true; try { localStorage.setItem(key, String(Date.now() + {{ \App\Support\Brain\BrainSettings::dismissHours() }} * 3600000)); } catch (e) {}"><x-nx.icon name="x" /></button>
</div>
<style>
    .brn-fab { position: fixed; z-index: 35; bottom: var(--brn-fab-b, 96px); }
    .brn-fab--left { left: 14px; }
    .brn-fab--right { right: 14px; bottom: calc(var(--brn-fab-b, 96px) + 192px); }   /* clears the Wizard, My Journey and WhatsApp stack */
    body:has(.nn-fab) .brn-fab--left { bottom: calc(var(--brn-fab-b, 96px) + 64px); }  /* sits above the quick Naara chat button when both show */
    .brn-fab__btn { position: relative; display: grid; place-items: center; width: 54px; height: 54px; border-radius: 50%; background: rgb(var(--nx-surface)); box-shadow: inset 0 0 0 1px rgb(var(--nx-line)), var(--nx-e2); transition: transform .15s; }
    .brn-fab__btn:active { transform: scale(.94); }
    .brn-fab__btn img { width: 38px; height: 38px; object-fit: contain; }
    .brn-fab__x { position: absolute; top: -6px; width: 20px; height: 20px; display: grid; place-items: center; border: 0; border-radius: 50%; font-size: 11px; cursor: pointer; color: rgb(var(--nx-text-2)); background: rgb(var(--nx-surface-3)); box-shadow: inset 0 0 0 1px rgb(var(--nx-line)); }
    .brn-fab--left .brn-fab__x { inset-inline-end: -6px; } .brn-fab--right .brn-fab__x { inset-inline-start: -6px; }
    @media (min-width: 1024px) {
        .brn-fab { bottom: 24px; } .brn-fab--left { left: 19.5rem; } .brn-fab--right { bottom: calc(24px + 192px); }
        body:has(.nn-fab) .brn-fab--left { bottom: 88px; }
    }
    @media (prefers-reduced-motion: reduce) { .brn-fab__btn { transition: none; } }
</style>

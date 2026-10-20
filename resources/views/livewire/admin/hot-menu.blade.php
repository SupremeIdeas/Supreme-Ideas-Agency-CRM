{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="relative">
    {{-- Poll keeps badge counts current without a websocket server (mirrors
         the customer notification bell's own pattern). --}}
    <div wire:poll.30s class="hidden"></div>

    {{-- Keyed to THIS Livewire instance's own id (frontend-UX-fix blueprint
         Phase A) — the mobile and desktop headers each mount a separate
         instance of this component, and open-modal/close-modal fire as
         GLOBAL window events matched by name. A shared literal name would
         open both instances' dialogs together the moment either bell is
         clicked, once the modal engine's own x-teleport fix removed the
         `lg:hidden` header ancestor that had been accidentally masking one
         of them (see notification-center.blade.php for the full story). --}}
    @php
        $modalName = 'admin-hot-menu-'.$this->getId();
    @endphp

    {{-- Trigger --}}
    <button type="button" @click="$dispatch('open-modal', { name: '{{ $modalName }}' })" aria-label="Admin quick menu"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]">
        <x-icon name="grid" class="h-5 w-5" />
        @if ($total > 0)
            <span class="absolute -right-0.5 -top-0.5 flex min-w-[18px] items-center justify-center rounded-full bg-[rgb(var(--nx-gold))] px-1 text-[10px] font-bold leading-none text-[rgb(var(--nx-on-gold))]"
                  style="height:18px">{{ $total > 99 ? '99+' : $total }}</span>
        @endif
    </button>

    {{-- The ONE modal engine (S31) — same slide-up-from-the-bottom sheet
         shape as every other modal in the platform. --}}
    <x-ui.modal :name="$modalName" title="Quick menu" max-width="md">
        <div class="-mx-6 max-h-[65vh] overflow-y-auto border-t border-[rgb(var(--nx-line))]">
            @forelse ($sections as $s)
                <button type="button" wire:click="open('{{ $s['key'] }}', '{{ $s['route'] }}')"
                   class="flex w-full items-center gap-3 border-b border-[rgb(var(--nx-line))] px-6 py-3 text-left transition hover:bg-[rgb(var(--nx-surface-2))]">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]">
                        @if (class_exists(\App\Support\MasterMarketing\MenuIcons::class))<x-master-marketing.menu-icon :route="$s['route']" surface="admin" :fallback="$s['icon']" :size="24" />@else<x-icon :name="$s['icon']" class="h-4 w-4" />@endif
                    </span>
                    <span class="min-w-0 flex-1 text-sm font-medium text-[rgb(var(--nx-text))]">{{ $s['label'] }}</span>
                    @if (($s['escalation'] ?? 0) > 0)
                        {{-- Visually distinct from the plain count badge — KYC escalations
                             need to stand out (Tier 5 #11 gives this real data). --}}
                        <span class="rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-2 py-0.5 text-[11px] font-bold text-[rgb(var(--nx-bad))]">
                            {{ $s['escalation'] }} escalated
                        </span>
                    @endif
                    @if (($s['count'] ?? null) !== null && $s['count'] > 0)
                        <span class="flex min-w-[22px] items-center justify-center rounded-full bg-[rgb(var(--nx-gold)/0.15)] px-1.5 py-0.5 text-[11px] font-bold text-[rgb(var(--nx-gold-ink))]">
                            {{ $s['count'] > 99 ? '99+' : $s['count'] }}
                        </span>
                    @endif
                </button>
            @empty
                <div class="flex flex-col items-center gap-2 px-6 py-10 text-center text-[rgb(var(--nx-text-2))]">
                    <x-icon name="grid" class="h-8 w-8 opacity-40" />
                    <p class="text-sm">Nothing available to you here yet.</p>
                </div>
            @endforelse
        </div>
    </x-ui.modal>
</div>

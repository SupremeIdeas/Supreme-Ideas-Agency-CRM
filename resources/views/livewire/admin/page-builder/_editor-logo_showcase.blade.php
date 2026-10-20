{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="space-y-4">
    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Heading (optional)</label>
        <input type="text" wire:model="config.heading" placeholder="Trusted by" class="{{ $inp }}"></div>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Logos</span>
            <button type="button" wire:click="addRepeaterItem" class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">+ Logo</button>
        </div>
        @foreach (($config['logos'] ?? []) as $i => $logo)
            <div class="flex items-center gap-2 rounded-xl border border-[rgb(var(--nx-line)/0.7)] p-3" wire:key="logo-{{ $i }}">
                @if (! empty($logo['src']))<img src="{{ $logo['src'] }}" class="h-8 w-16 object-contain">@endif
                <input type="text" wire:model="config.logos.{{ $i }}.alt" placeholder="Alt text" class="{{ $inp }} max-w-[140px]">
                <input type="file" wire:model="upload" accept="image/*" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-2 file:py-1 file:text-xs file:text-[rgb(var(--nx-teal-ink))]">
                <button type="button" wire:click="uploadRepeaterImage({{ $i }})" class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">Set</button>
                <button type="button" wire:click="removeRepeaterItem({{ $i }})" class="shrink-0 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
            </div>
        @endforeach
    </div>
</div>

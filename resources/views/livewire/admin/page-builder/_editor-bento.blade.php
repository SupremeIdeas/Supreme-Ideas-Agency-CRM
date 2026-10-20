{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Heading</label>
            <input type="text" wire:model="config.heading" class="{{ $inp }}"></div>
        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Layout</label>
            <select wire:model="config.layout" class="{{ $inp }}">
                <option value="rhythm">Rhythm (2/1/2/1)</option>
                <option value="featured">Featured first</option>
                <option value="uniform">Uniform grid</option>
            </select></div>
    </div>
    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Subheading</label>
        <input type="text" wire:model="config.subheading" class="{{ $inp }}"></div>

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Cards</span>
            <button type="button" wire:click="addRepeaterItem" class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">+ Card</button>
        </div>
        @foreach (($config['cards'] ?? []) as $i => $card)
            <div class="rounded-xl border border-[rgb(var(--nx-line)/0.7)] p-3" wire:key="bento-card-{{ $i }}">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-medium text-[rgb(var(--nx-text-2))]">Card {{ $i + 1 }}</span>
                    <button type="button" wire:click="removeRepeaterItem({{ $i }})" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="text" wire:model="config.cards.{{ $i }}.title" placeholder="Title" class="{{ $inp }}">
                    <input type="text" wire:model="config.cards.{{ $i }}.badge" placeholder="Badge (optional)" class="{{ $inp }}">
                </div>
                <textarea wire:model="config.cards.{{ $i }}.body" rows="2" placeholder="Short description" class="{{ $inp }} mt-2"></textarea>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <input type="text" wire:model="config.cards.{{ $i }}.icon" placeholder="Icon name (fallback)" class="{{ $inp }}">
                    <input type="text" wire:model="config.cards.{{ $i }}.cta_label" placeholder="CTA label" class="{{ $inp }}">
                </div>
                <input type="text" wire:model="config.cards.{{ $i }}.cta_target" placeholder="CTA target: URL, /path, or route" class="{{ $inp }} mt-2">
                <div class="mt-2 flex items-center gap-2">
                    @if (! empty($card['image']))<img src="{{ $card['image'] }}" class="h-10 w-16 rounded object-cover">@endif
                    <input type="file" wire:model="upload" accept="image/*" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-2 file:py-1 file:text-xs file:text-[rgb(var(--nx-teal-ink))]">
                    <button type="button" wire:click="uploadRepeaterImage({{ $i }})" class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">Set</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

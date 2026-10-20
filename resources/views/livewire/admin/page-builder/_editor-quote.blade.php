{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="space-y-4">
    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Quote</label>
        <textarea wire:model="config.quote" rows="3" class="{{ $inp }}"></textarea></div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Attribution</label>
            <input type="text" wire:model="config.attribution" class="{{ $inp }}"></div>
        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Treatment</label>
            <select wire:model="config.treatment" class="{{ $inp }}">
                <option value="gradient">Brand gradient</option>
                <option value="dark">Dark navy</option>
                <option value="minimal">Minimal</option>
            </select></div>
    </div>
</div>

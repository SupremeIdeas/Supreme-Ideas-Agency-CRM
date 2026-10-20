{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Caption</label>
            <input type="text" wire:model="config.caption" class="{{ $inp }}"></div>
        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Language</label>
            <input type="text" wire:model="config.language" placeholder="bash, php, json…" class="{{ $inp }}"></div>
    </div>
    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Code</label>
        <textarea wire:model="config.code" rows="8" spellcheck="false" class="{{ $inp }} font-mono text-xs"></textarea></div>
</div>

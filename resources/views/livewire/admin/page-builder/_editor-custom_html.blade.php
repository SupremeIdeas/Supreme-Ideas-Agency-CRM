{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="space-y-4">
    <div class="rounded-lg bg-[rgb(var(--nx-warn)/0.12)] p-2.5 text-xs text-[rgb(var(--nx-warn))]">
        <x-icon name="shield" class="mr-1 inline h-3.5 w-3.5" />
        Pasted HTML is sanitized on save — scripts, iframes, event handlers and unsafe URLs are stripped automatically.
    </div>
    <div>
        <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">HTML</label>
        <textarea wire:model="config.html" rows="10" spellcheck="false"
            class="{{ $inp }} font-mono text-xs" placeholder="<h2>Heading</h2>&#10;<p>Your content…</p>"></textarea>
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Width</label>
        <select wire:model="config.max_width" class="{{ $inp }}">
            <option value="container">Contained</option>
            <option value="full">Full width</option>
        </select>
    </div>
</div>

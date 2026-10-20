{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Heading</label>
            <input type="text" wire:model="config.heading" class="{{ $inp }}"></div>
        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Style</label>
            <select wire:model="config.style" class="{{ $inp }}"><option value="bordered">Bordered</option><option value="plain">Plain</option></select></div>
    </div>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Questions</span>
            <button type="button" wire:click="addRepeaterItem" class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">+ Q&amp;A</button>
        </div>
        @foreach (($config['items'] ?? []) as $i => $item)
            <div class="rounded-xl border border-[rgb(var(--nx-line)/0.7)] p-3" wire:key="faq-{{ $i }}">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-medium text-[rgb(var(--nx-text-2))]">#{{ $i + 1 }}</span>
                    <button type="button" wire:click="removeRepeaterItem({{ $i }})" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                </div>
                <input type="text" wire:model="config.items.{{ $i }}.q" placeholder="Question" class="{{ $inp }}">
                <textarea wire:model="config.items.{{ $i }}.a" rows="2" placeholder="Answer" class="{{ $inp }} mt-2"></textarea>
            </div>
        @endforeach
    </div>
</div>

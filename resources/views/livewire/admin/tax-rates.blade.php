{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Tax / VAT rates</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Set a tax percent per country (ISO code, e.g. GB, DE). Tax is added on top of the price and shown as its own line on checkout and receipts — <strong>only</strong> for the countries you list here. Leave empty to charge no tax anywhere. Whether to charge tax in a country is a legal/accounting decision, not a technical one.</p>
    </div>

    @if ($saved)
        <div class="mb-4 rounded-lg border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] px-4 py-2.5 text-sm text-[rgb(var(--nx-ok))]">{{ $saved }}</div>
    @endif

    <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="space-y-2">
            @foreach ($rows as $i => $row)
                <div wire:key="tax-{{ $i }}" class="flex items-center gap-2">
                    <input wire:model="rows.{{ $i }}.country" placeholder="ISO (GB)" maxlength="2"
                           class="w-28 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm uppercase">
                    <div class="relative flex-1">
                        <input wire:model="rows.{{ $i }}.rate" type="number" step="0.001" min="0" max="100" placeholder="Rate"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 pr-8 text-sm">
                        <span class="pointer-events-none absolute right-3 top-2 text-sm text-[rgb(var(--nx-text-2))]">%</span>
                    </div>
                    <button wire:click="removeRow({{ $i }})" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
            @endforeach
        </div>
        <button wire:click="addRow" class="mt-3 text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">+ Add country</button>

        <div class="mt-5 border-t border-[rgb(var(--nx-line))] pt-4">
            <button wire:click="save" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save tax rates</button>
        </div>
    </div>
</div>

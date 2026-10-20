{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Floating nav</h1>
            <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">The pill bar on the public site. Repoint any slot to any page — no code change.</p>
        </div>
        <button type="button" wire:click="addSlot" class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]"><x-icon name="plus" class="h-3.5 w-3.5" /> Add slot</button>
    </div>

    <div class="space-y-3">
        @foreach ($slots as $id => $slot)
            <div class="rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-4" wire:key="slot-{{ $id }}">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Label</label>
                        <input type="text" wire:model="slots.{{ $id }}.label" class="{{ $inp }}"></div>
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Icon</label>
                        <input type="text" wire:model="slots.{{ $id }}.icon" placeholder="globe, wifi, phone…" class="{{ $inp }}"></div>
                    <div class="sm:col-span-2"><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Target <span class="font-normal text-[rgb(var(--nx-text-2))]">(route name, /path, URL, or "wizard")</span></label>
                        <input type="text" wire:model="slots.{{ $id }}.target" class="{{ $inp }}"></div>
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Visible to</label>
                        <select wire:model="slots.{{ $id }}.visibility" class="{{ $inp }}">
                            <option value="all">Everyone</option><option value="auth">Signed-in only</option><option value="guest">Signed-out only</option>
                        </select></div>
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Order</label>
                        <input type="number" wire:model="slots.{{ $id }}.position" class="{{ $inp }}"></div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="slots.{{ $id }}.is_active" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]"> Active</label>
                    <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="slots.{{ $id }}.is_center" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]"> Glowing centerpiece</label>
                    <button type="button" wire:click="remove({{ $id }})" wire:confirm="Remove this slot?" class="ml-auto text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex gap-2">
        <button type="button" wire:click="save" class="flex-1 rounded-full bg-[rgb(var(--nx-cta-b))] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">Save floating nav</button>
        <button type="button" wire:click="resetDefaults" wire:confirm="Reset the floating nav to defaults?" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-5 py-3 text-sm font-semibold text-[rgb(var(--nx-text-2))]">Reset</button>
    </div>
</div>

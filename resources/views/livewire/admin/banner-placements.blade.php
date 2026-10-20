{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Banner placements</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">The "More from Naara" banner carousel — choose where it shows and how much of each slide it displays. Turning a placement off removes it from that page immediately, no deploy.</p>
    </div>

    @foreach ($placements as $key => $label)
        @php($f = $form[$key])
        <div wire:key="placement-{{ $key }}" class="mb-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $label }}</p>
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $key }}</p>
                </div>
                <button type="button" wire:click="toggle('{{ $key }}')"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $f['enabled'] ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $f['enabled'] ? 'bg-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-line-strong))]' }}"></span>
                    {{ $f['enabled'] ? 'On' : 'Off' }}
                </button>
            </div>

            @if ($saved === $key)
                <div class="mb-3 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-3 py-2 text-xs text-[rgb(var(--nx-ok))]">
                    <x-icon name="badge-check" class="h-4 w-4" /> Saved.
                </div>
            @endif

            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[220px] flex-1">
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Display style</label>
                    <select wire:model="form.{{ $key }}.display_style"
                            class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        @foreach ($displayStyles as $styleKey => $styleLabel)
                            <option value="{{ $styleKey }}">{{ $styleLabel }}</option>
                        @endforeach
                    </select>
                    @error("form.{$key}.display_style") <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="save('{{ $key }}')" wire:loading.attr="disabled" wire:target="save('{{ $key }}')"
                        class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    Save
                </button>
            </div>
        </div>
    @endforeach
</div>

{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Brand icons</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        Every payment gateway shows a logo automatically — a real bundled brand mark where one is shipped,
        otherwise a lettered chip. Upload an official logo here to override any of them. Accepts
        <strong>WebP, SVG or PNG</strong> (also JPG), up to 1&nbsp;MB. Social sign-in logos have their own
        override screen — see <a href="{{ route('admin.service-icons') }}" class="underline">Service icons</a>.
    </p>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Upload --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Gateway / mark</label>
                <select wire:model="uploadSlug" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm capitalize">
                    <option value="">Choose one…</option>
                    @foreach ($rows as $row)
                        <option value="{{ $row['slug'] }}">{{ $row['slug'] }}</option>
                    @endforeach
                </select>
                @error('uploadSlug') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Logo file</label>
                <input type="file" wire:model="upload" accept="image/webp,image/svg+xml,image/png,image/jpeg"
                       class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b))] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                @error('upload') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <x-ui.btn variant="primary" type="button" wire:click="save" target="save" icon="upload">Save logo</x-ui.btn>
        </div>
    </div>

    {{-- Current set --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
        @foreach ($rows as $row)
            @php($slug = $row['slug'])
            <div wire:key="pay-{{ $slug }}" class="nx-card relative !p-4 text-center">
                @if ($row['state'] === 'missing')
                    <span class="absolute right-2 top-2 rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-1.5 py-0.5 text-[9px] font-bold uppercase text-[rgb(var(--nx-warn))]">Needs logo</span>
                @endif
                <span class="mx-auto flex h-12 w-12 items-center justify-center">
                    <x-payment-icon :slug="$slug" class="h-10" />
                </span>
                <p class="mt-2 text-xs font-semibold capitalize text-[rgb(var(--nx-text))]">{{ $slug }}</p>
                @if ($row['state'] === 'custom')
                    <button type="button" wire:click="remove('{{ $slug }}')" class="mt-1 text-[11px] font-medium text-[rgb(var(--nx-bad))] hover:underline">Remove override</button>
                @elseif ($row['state'] === 'bundled')
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Built-in mark</p>
                @else
                    <button type="button" wire:click="$set('uploadSlug', '{{ $slug }}')" class="mt-1 text-[11px] font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">Add a logo</button>
                @endif
            </div>
        @endforeach
    </div>
</div>

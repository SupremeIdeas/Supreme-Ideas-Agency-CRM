{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Social Hunt</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Manage NaaraSim's own follow handles and featured brand partners. Each follow grants a one-time surprise NaaraCredit reward (never shown to users before they follow).</p>
    </div>

    @if ($saved)
        <div class="mb-4 rounded-lg border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] px-4 py-2.5 text-sm text-[rgb(var(--nx-ok))]">{{ $saved }}</div>
    @endif

    @php
        $input = 'rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm';
    @endphp

    {{-- Platform handles --}}
    <section class="mb-8 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))]">NaaraSim handles</h2>
        <div class="space-y-2">
            @forelse ($handles as $h)
                <div wire:key="h-{{ $h->id }}" class="flex items-center gap-3 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2">
                    <x-service-icon :slug="$h->platform" class="h-5 w-5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $h->handle_label }} <span class="text-xs text-[rgb(var(--nx-text-2))]">· {{ $h->credit_reward }} cr · {{ $h->verification }}</span></p>
                        <p class="truncate text-xs text-[rgb(var(--nx-text-2))]">{{ $h->handle_url }}</p>
                    </div>
                    <button wire:click="toggleHandle({{ $h->id }})" class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $h->is_active ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">{{ $h->is_active ? 'Active' : 'Off' }}</button>
                    <button wire:click="deleteHandle({{ $h->id }})" wire:confirm="Remove this handle?" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No handles yet.</p>
            @endforelse
        </div>
        <div class="mt-4 grid gap-2 border-t border-[rgb(var(--nx-line))] pt-4 sm:grid-cols-6">
            <select wire:model="handle.platform" class="{{ $input }} sm:col-span-1">
                @foreach ($platforms as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
            </select>
            <input wire:model="handle.handle_label" placeholder="Label" class="{{ $input }} sm:col-span-1">
            <input wire:model="handle.handle_url" placeholder="https://…" class="{{ $input }} sm:col-span-2">
            <input wire:model="handle.credit_reward" type="number" step="0.5" placeholder="Reward" class="{{ $input }} sm:col-span-1">
            <select wire:model="handle.verification" class="{{ $input }} sm:col-span-1">
                <option value="self">Self-confirmed</option>
                <option value="api">API-verified</option>
            </select>
            <button wire:click="addHandle" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] sm:col-span-6">Add handle</button>
        </div>
        @error('handle.handle_url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        @error('handle.handle_label') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
    </section>

    {{-- Brand partners --}}
    <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))]">Featured brand partners</h2>
        <div class="space-y-4">
            @forelse ($brands as $b)
                <div wire:key="b-{{ $b->id }}" class="rounded-xl border border-[rgb(var(--nx-line))] p-4">
                    <div class="flex items-center gap-3">
                        <span class="h-8 w-8 shrink-0 rounded-lg" style="background: {{ $b->background_color }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $b->brand_name }}</p>
                            <p class="truncate text-xs text-[rgb(var(--nx-text-2))]">{{ $b->short_description }}</p>
                        </div>
                        <button wire:click="toggleBrand({{ $b->id }})" class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $b->is_active ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">{{ $b->is_active ? 'Active' : 'Off' }}</button>
                        <button wire:click="deleteBrand({{ $b->id }})" wire:confirm="Remove this brand and its handles?" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                    {{-- Brand handles --}}
                    <div class="mt-3 space-y-1.5 pl-11">
                        @foreach ($b->handles as $bhh)
                            <div class="flex items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
                                <x-service-icon :slug="$bhh->platform" class="h-4 w-4" />
                                <span class="flex-1 truncate">{{ $bhh->handle_label }} · {{ $bhh->credit_reward }} cr</span>
                                <button wire:click="deleteBrandHandle({{ $bhh->id }})" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                            </div>
                        @endforeach
                        <div class="grid gap-2 pt-1 sm:grid-cols-6">
                            <select wire:model="bh.{{ $b->id }}.platform" class="{{ $input }} sm:col-span-1">
                                @foreach ($platforms as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
                            </select>
                            <input wire:model="bh.{{ $b->id }}.handle_label" placeholder="Label" class="{{ $input }} sm:col-span-1">
                            <input wire:model="bh.{{ $b->id }}.handle_url" placeholder="https://…" class="{{ $input }} sm:col-span-2">
                            <input wire:model="bh.{{ $b->id }}.credit_reward" type="number" step="0.5" placeholder="Reward" class="{{ $input }} sm:col-span-1">
                            <button wire:click="addBrandHandle({{ $b->id }})" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-2 text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))] sm:col-span-1">Add</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No brand partners yet.</p>
            @endforelse
        </div>
        {{-- New brand --}}
        <div class="mt-4 grid gap-2 border-t border-[rgb(var(--nx-line))] pt-4 sm:grid-cols-6">
            <input wire:model="brand.brand_name" placeholder="Brand name" class="{{ $input }} sm:col-span-2">
            <input wire:model="brand.short_description" placeholder="Short description" class="{{ $input }} sm:col-span-2">
            <input wire:model="brand.background_color" type="color" class="h-9 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] sm:col-span-1">
            <input type="file" wire:model="brandImage" accept="image/*" class="text-xs sm:col-span-1">
            <button wire:click="addBrand" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] sm:col-span-6">Add brand partner</button>
        </div>
        @error('brand.brand_name') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        @error('brand.background_color') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
    </section>
</div>

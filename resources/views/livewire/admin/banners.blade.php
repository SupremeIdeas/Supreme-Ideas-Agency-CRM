{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Banners</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">Promo artwork shown to users. Pick a zone, upload JPG or WebP at the recommended size (plus an optional MP4/WebM video that loops over it), and optionally attach a link or a coupon. Dashboard-home banners rotate in a carousel; the other zones show the top banner.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] p-3 text-sm text-[rgb(var(--nx-teal-ink))]">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    {{-- Create --}}
    <section class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="image" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> New banner
        </h2>

        <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title <span class="font-normal text-[rgb(var(--nx-text-2))]">(also the image alt text)</span></label>
                <input wire:model="title" type="text" placeholder="July data sale — 10% off"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @error('title') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Zone</label>
                <select wire:model.live="placement" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @foreach ($placements as $key => [$label, $desktop, $mobile])
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">
                    Recommended: {{ $placements[$placement][1] }} px (desktop), {{ $placements[$placement][2] }} px (mobile).
                </p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Artwork (JPG/WebP, ≤ 2 MB)</label>
                <input wire:model="image" type="file" accept="image/jpeg,image/webp"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-md file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                <div wire:loading wire:target="image" class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Uploading…</div>
                @error('image') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Mobile artwork <span class="font-normal text-[rgb(var(--nx-text-2))]">(optional — tighter crop for phones)</span></label>
                <input wire:model="image_mobile" type="file" accept="image/jpeg,image/webp"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-md file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                <div wire:loading wire:target="image_mobile" class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Uploading…</div>
                @error('image_mobile') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Motion video <span class="font-normal text-[rgb(var(--nx-text-2))]">(optional — MP4/WebM, ≤ 10 MB)</span></label>
                <input wire:model="video" type="file" accept="video/mp4,video/webm"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-md file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Plays muted &amp; looping over the artwork above (great for the “More” menu). The artwork stays as the poster and the reduced-motion fallback.</p>
                <div wire:loading wire:target="video" class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Uploading video…</div>
                @error('video') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Link <span class="font-normal text-[rgb(var(--nx-text-2))]">(optional — /catalogue or https://…)</span></label>
                <input wire:model="link_url" type="text" placeholder="/catalogue"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @error('link_url') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Coupon <span class="font-normal text-[rgb(var(--nx-text-2))]">(optional — shows a copyable code chip)</span></label>
                <select wire:model="coupon_id" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <option value="">No coupon</option>
                    @foreach ($couponOptions as $c)
                        <option value="{{ $c->id }}">{{ $c->code }} — {{ rtrim(rtrim(number_format((float) $c->percent_off, 2), '0'), '.') }}%</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Manage codes on the <a href="{{ route('admin.coupons') }}" class="text-[rgb(var(--nx-teal-ink))] hover:underline" wire:navigate>Coupons page</a>.</p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Order</label>
                    <input wire:model="sort_order" type="number" min="0" max="999"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Starts <span class="font-normal text-[rgb(var(--nx-text-2))]">(opt.)</span></label>
                    <input wire:model="starts_at" type="datetime-local"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Ends <span class="font-normal text-[rgb(var(--nx-text-2))]">(opt.)</span></label>
                    <input wire:model="ends_at" type="datetime-local"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error('ends_at') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="flex items-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="save,image,image_mobile,video"
                        class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Publish banner</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Publishing…</span>
                </button>
            </div>
        </form>
    </section>

    {{-- List --}}
    <div class="space-y-3">
        @forelse ($banners as $banner)
            <div wire:key="banner-{{ $banner->id }}" class="flex flex-wrap items-center gap-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="h-16 w-40 shrink-0 rounded-lg border border-[rgb(var(--nx-line))] object-cover">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold text-[rgb(var(--nx-text))]">{{ $banner->title }}</div>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
                        <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5">{{ \App\Models\Banner::PLACEMENTS[$banner->placement][0] ?? $banner->placement }}</span>
                        @if ($banner->coupon)
                            <span class="rounded-full bg-[rgb(var(--nx-gold)/0.15)] px-2 py-0.5 font-mono font-semibold text-[rgb(var(--nx-gold-ink))]">{{ $banner->coupon->code }}</span>
                        @endif
                        @if ($banner->link_url)
                            <span class="inline-flex items-center gap-1 truncate"><x-icon name="globe" class="h-3 w-3" /> {{ $banner->link_url }}</span>
                        @endif
                        @if ($banner->starts_at || $banner->ends_at)
                            <span>{{ $banner->starts_at?->format('M j') ?? '…' }} → {{ $banner->ends_at?->format('M j') ?? '…' }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <x-ui.tag :variant="$banner->is_active ? 'live' : 'soon'">{{ $banner->is_active ? 'Live' : 'Paused' }}</x-ui.tag>
                    <button type="button" wire:click="toggle({{ $banner->id }})" wire:loading.attr="disabled"
                            class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline hover:text-[rgb(var(--nx-teal-ink))]">
                        {{ $banner->is_active ? 'Pause' : 'Resume' }}
                    </button>
                    <button type="button" wire:click="delete({{ $banner->id }})" wire:loading.attr="disabled"
                            wire:confirm="Delete this banner? Users stop seeing it immediately."
                            class="text-xs font-medium text-[rgb(var(--nx-bad))] underline hover:text-[rgb(var(--nx-bad))]">
                        Delete
                    </button>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-[rgb(var(--nx-line-strong))] p-10 text-center text-sm text-[rgb(var(--nx-text-2))]">
                No banners yet. Publish one above — it appears to users instantly.
            </div>
        @endforelse
    </div>
</div>

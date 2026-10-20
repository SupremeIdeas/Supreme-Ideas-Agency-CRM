{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Announcements &amp; offers</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Push an update or offer to every active user’s notification bell. Attach a coupon and they can claim it in one tap.</p>
    </div>

    @if ($sent)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $sent }}
        </div>
    @endif

    {{-- Composer --}}
    <form wire:submit="send" class="space-y-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title</label>
            <input type="text" wire:model="title" maxlength="120" placeholder="Weekend data sale — 20% off"
                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
            @error('title') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Message</label>
            <textarea wire:model="body" rows="3" maxlength="500" placeholder="For this weekend only, get 20% off any eSIM plan. Tap to claim."
                      class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm"></textarea>
            @error('body') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>

        {{-- AI-assisted composer (content/billing/hot-menu blueprint Phase B) --}}
        <div class="flex items-center justify-between gap-3">
            <p class="text-[11px] text-[rgb(var(--nx-text-2))]">
                @unless ($aiEnabled)
                    Add an Anthropic API key (Integrations) to draft copy with Claude.
                @else
                    Let Claude draft a few alternate takes on the title and message above.
                @endunless
            </p>
            <button type="button" wire:click="refine" @disabled(! $aiEnabled)
                    wire:loading.attr="disabled" wire:target="refine"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[rgb(var(--nx-teal))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] transition hover:bg-[rgb(var(--nx-cta-b)/0.1)] disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="zap" class="h-3.5 w-3.5" wire:loading.remove wire:target="refine" />
                <x-ui.spinner class="h-3.5 w-3.5" wire:loading wire:target="refine" />
                <span wire:loading.remove wire:target="refine">Refine with Claude</span>
                <span wire:loading wire:target="refine">Writing…</span>
            </button>
        </div>

        @if ($aiError)
            <p class="-mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $aiError }}</p>
        @endif

        @if (! empty($variations))
            <div class="space-y-2 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Variations</p>
                @foreach ($variations as $i => $variation)
                    <div wire:key="var-{{ $i }}" class="flex items-start justify-between gap-3 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $variation['title'] }}</p>
                            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $variation['body'] }}</p>
                        </div>
                        <button type="button" wire:click="applyVariation({{ $i }})"
                                class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-[rgb(var(--nx-cta-b))] px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">
                            <x-icon name="check" class="h-3.5 w-3.5" /> Apply
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Icon</label>
            <div class="flex flex-wrap gap-2">
                @foreach ($icons as $ic)
                    <button type="button" wire:click="$set('icon', '{{ $ic }}')"
                            class="flex h-10 w-10 items-center justify-center rounded-lg border transition {{ $icon === $ic ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]' : 'border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]' }}">
                        <x-icon :name="$ic" class="h-5 w-5" />
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Tier 5 #11 Phase A1 — two selectable presentation styles. --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Style</label>
            <div class="grid gap-2 sm:grid-cols-2">
                <button type="button" wire:click="$set('style', 'banner_hero')"
                        class="rounded-lg border p-3 text-left transition {{ $style === 'banner_hero' ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-cta-b)/0.05)]' : 'border-[rgb(var(--nx-line-strong))]' }}">
                    <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">Banner hero</p>
                    <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">Wide image up top, headline + one prominent button.</p>
                </button>
                <button type="button" wire:click="$set('style', 'dark_feature')"
                        class="rounded-lg border p-3 text-left transition {{ $style === 'dark_feature' ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-cta-b)/0.05)]' : 'border-[rgb(var(--nx-line-strong))]' }}">
                    <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">Dark feature card</p>
                    <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">Premium "what's new" card, inset preview + bullet points.</p>
                </button>
            </div>
        </div>

        @if ($style === 'banner_hero')
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Banner image <span class="text-[rgb(var(--nx-text-2))]">(optional — WebP/JPG, 1600×800 recommended, same as the dashboard hero)</span></label>
                <input type="file" wire:model="image" accept="{{ \App\Support\MediaStorage::acceptAttribute() }}"
                       class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-[rgb(var(--nx-teal-ink))]">
                @error('image') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Inset preview image <span class="text-[rgb(var(--nx-text-2))]">(optional — smaller, ~800×800)</span></label>
                    <input type="file" wire:model="featureImage" accept="{{ \App\Support\MediaStorage::acceptAttribute() }}"
                           class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-[rgb(var(--nx-teal-ink))]">
                    @error('featureImage') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Feature bullets <span class="text-[rgb(var(--nx-text-2))]">(one per line, optional)</span></label>
                    <textarea wire:model="bulletsText" rows="3" maxlength="1000" placeholder="Faster eSIM activation&#10;New number ports&#10;Lower fees"
                              class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm"></textarea>
                    @error('bulletsText') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Button label (optional)</label>
                <input type="text" wire:model="cta_label" maxlength="40" placeholder="Claim offer"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                @error('cta_label') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Coupon code (optional)</label>
                <input type="text" wire:model="coupon_code" maxlength="40" placeholder="WEEKEND20"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm uppercase">
                @error('coupon_code') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Tier 5 #11 Phase A2 — sitewide deep-link picker; still allows a raw
             custom URL for the rare external-link case. --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Link to (optional — ignored if a coupon is set)</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <select @change="$wire.set('cta_url', $event.target.value)"
                        class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm sm:w-56">
                    <option value="">— Pick an in-app page —</option>
                    @foreach ($deepLinks as $link)
                        <option value="{{ $link['url'] }}" @selected($cta_url === $link['url'])>{{ $link['label'] }}</option>
                    @endforeach
                </select>
                <input type="url" wire:model="cta_url" maxlength="300" placeholder="https://… (or pick a page above)"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
            </div>
            <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">With a coupon set, the button sends users to the catalogue with the code ready to claim.</p>
            @error('cta_url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>

        @if ($style === 'dark_feature')
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Secondary link (optional — e.g. "View all changelogs")</label>
                <div class="grid gap-2 sm:grid-cols-[1fr_auto_2fr]">
                    <input type="text" wire:model="secondary_label" maxlength="40" placeholder="View all changelogs"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                    <select @change="$wire.set('secondary_url', $event.target.value)"
                            class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                        <option value="">— Pick a page —</option>
                        @foreach ($deepLinks as $link)
                            <option value="{{ $link['url'] }}" @selected($secondary_url === $link['url'])>{{ $link['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="url" wire:model="secondary_url" maxlength="300" placeholder="https://…"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                </div>
                @error('secondary_label') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                @error('secondary_url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="send"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <x-icon name="bell" class="h-4 w-4" />
                <span wire:loading.remove wire:target="send">Send to everyone</span>
                <span wire:loading wire:target="send">Sending…</span>
            </button>
        </div>
    </form>

    {{-- History --}}
    <h2 class="mb-3 mt-8 text-sm font-semibold text-[rgb(var(--nx-text))]">Sent announcements</h2>
    <div class="space-y-2">
        @forelse ($announcements as $a)
            <div wire:key="a-{{ $a->id }}" class="flex items-start gap-3 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]">
                    <x-icon :name="$a->icon ?: 'gift'" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $a->title }}</p>
                    <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">{{ $a->body }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-[rgb(var(--nx-text-2))]">
                        <span>{{ $a->created_at->diffForHumans() }}</span>
                        @if ($a->coupon_code)<span class="rounded bg-[rgb(var(--nx-gold)/0.1)] px-1.5 py-0.5 font-semibold text-[rgb(var(--nx-gold-ink))]">{{ $a->coupon_code }}</span>@endif
                        <span class="inline-flex items-center gap-1">
                            @if ($a->status === 'sent')
                                <x-icon name="badge-check" class="h-3 w-3 text-[rgb(var(--nx-ok))]" /> {{ number_format($a->recipients) }} delivered
                            @else
                                Sending…
                            @endif
                        </span>
                        @if ($a->author)<span>by {{ $a->author->name }}</span>@endif
                    </div>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-[rgb(var(--nx-line))] py-10 text-center text-sm text-[rgb(var(--nx-text-2))]">No announcements yet.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $announcements->links() }}</div>
</div>

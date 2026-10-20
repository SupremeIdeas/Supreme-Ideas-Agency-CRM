{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Auth &amp; Footer</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">The media panel shown beside the login/register form, and the footer links across the site. “Supreme Ideas Agency” attribution always shows and can’t be removed.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] p-3 text-sm text-[rgb(var(--nx-teal-ink))]">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{-- Auth panel --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                <x-icon name="image" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Sign-in media panel
            </h2>

            <div class="mt-4 mb-4">
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Login treatment</label>
                <select wire:model="login_style" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <option value="auto">Auto — your uploaded media if set, else the animated WebGL scene</option>
                    <option value="webgl">Always the animated WebGL scene</option>
                    <option value="image">Image / text only (lighter, faster — no WebGL)</option>
                </select>
                <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Both treatments stay supported; this only picks which one the login page shows.</p>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Panel type</label>
                    <select wire:model.live="media_type" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                        <option value="image">Image (WebP / JPEG)</option>
                        <option value="video">Video (MP4 / WebM, muted loop)</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">
                        {{ $media_type === 'video' ? 'Video file (≤ 8 MB)' : 'Image file (≤ 8 MB)' }}
                    </label>
                    <input wire:model="media" type="file" accept="{{ $media_type === 'video' ? 'video/mp4,video/webm' : 'image/webp,image/jpeg,image/png' }}"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-md file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                    <div wire:loading wire:target="media" class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Uploading…</div>
                    @error('media') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    @if ($hasMedia)
                        <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">A panel is set. Upload to replace it.</p>
                    @else
                        <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">None set — a branded gradient shows until you upload one.</p>
                    @endif
                </div>
                @if ($media_type === 'video')
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Video poster <span class="font-normal text-[rgb(var(--nx-text-2))]">(optional still)</span></label>
                        <input wire:model="poster" type="file" accept="image/webp,image/jpeg,image/png"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-md file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                        @error('poster') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>

            <div class="mt-4 grid gap-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Headline</label>
                    <input wire:model="headline" type="text"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error('headline') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Subtext</label>
                    <textarea wire:model="subtext" rows="2"
                              class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></textarea>
                    @error('subtext') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        {{-- Sign-in illustration (master only: shown where the illustration ships) --}}
        @if ($artAvailable)
            <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                    <x-icon name="image" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Sign-in illustration
                </h2>
                <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">The artwork under the login card, the frosted glass over it, and the calm aurora behind the form. Applies to the user and admin sign-in pages.</p>

                <label class="mt-4 flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]">
                    <input type="checkbox" wire:model.live="art_enabled" class="rounded text-[rgb(var(--nx-teal-ink))]"> Show the illustration
                </label>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">How much the card covers it</label>
                        <select wire:model="art_overlap" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                            <option value="none">Not at all (sits below the card)</option>
                            <option value="light">A little (most of it visible)</option>
                            <option value="medium">About a third</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Illustration size</label>
                        <select wire:model="art_size" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                            <option value="small">Small</option>
                            <option value="medium">Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Glass card</label>
                        <select wire:model="art_glass" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                            <option value="off">Off (solid card)</option>
                            <option value="light">Light (see through)</option>
                            <option value="medium">Medium</option>
                            <option value="strong">Strong (mostly solid)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Aurora behind the form</label>
                        <select wire:model="art_aurora" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                            <option value="off">Off</option>
                            <option value="calm">Calm</option>
                            <option value="soft">Soft (a bit brighter)</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-4">
                    <img src="{{ $artCurrent['src'] }}" alt="" class="h-16 w-auto rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-1">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Replace the artwork (transparent PNG or WebP, up to 2 MB)</label>
                        <input type="file" wire:model="art_image" accept="image/png,image/webp" class="block w-full text-sm text-[rgb(var(--nx-text-2))]">
                        <div wire:loading wire:target="art_image" class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Uploading...</div>
                        @error('art_image') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                        @if ($artCustom !== '')
                            <label class="mt-2 flex items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
                                <input type="checkbox" wire:model="art_reset" class="rounded text-[rgb(var(--nx-teal-ink))]"> Go back to the standard Naara artwork
                            </label>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- Footer columns --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                    <x-icon name="grid" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Footer link columns
                </h2>
                <button type="button" wire:click="addColumn" @disabled(count($columns) >= 4)
                        class="inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-50">
                    <x-icon name="check" class="h-3.5 w-3.5" /> Add column
                </button>
            </div>

            <div class="mt-4 space-y-5">
                @forelse ($columns as $ci => $column)
                    <div wire:key="col-{{ $ci }}" class="rounded-xl border border-[rgb(var(--nx-line))] p-4">
                        <div class="flex items-center gap-2">
                            <input wire:model="columns.{{ $ci }}.heading" type="text" placeholder="Section heading"
                                   class="flex-1 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm font-semibold text-[rgb(var(--nx-text))]">
                            <button type="button" wire:click="removeColumn({{ $ci }})" class="rounded-lg p-1.5 text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]" aria-label="Remove column">
                                <x-icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                        @error("columns.$ci.heading") <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror

                        <div class="mt-3 space-y-2">
                            @foreach ($column['links'] as $li => $link)
                                <div wire:key="col-{{ $ci }}-link-{{ $li }}" class="flex items-center gap-2">
                                    <input wire:model="columns.{{ $ci }}.links.{{ $li }}.label" type="text" placeholder="Label"
                                           class="w-1/3 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
                                    <input wire:model="columns.{{ $ci }}.links.{{ $li }}.url" type="text" placeholder="/path or https://…"
                                           class="flex-1 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
                                    <button type="button" wire:click="removeLink({{ $ci }}, {{ $li }})" class="rounded-lg p-1.5 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]" aria-label="Remove link">
                                        <x-icon name="x" class="h-4 w-4" />
                                    </button>
                                </div>
                            @endforeach
                            @error("columns.$ci.links.*.label") <span class="block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            @error("columns.$ci.links.*.url") <span class="block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                            <button type="button" wire:click="addLink({{ $ci }})" class="text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">+ Add link</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">No columns — add one, or leave empty for a minimal footer.</p>
                @endforelse
            </div>
        </section>

        {{-- Legal row --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                    <x-icon name="shield" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Legal links <span class="font-normal text-[rgb(var(--nx-text-2))]">(bottom bar)</span>
                </h2>
                <button type="button" wire:click="addLegal" @disabled(count($legal) >= 6)
                        class="inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-50">
                    <x-icon name="check" class="h-3.5 w-3.5" /> Add link
                </button>
            </div>
            <div class="mt-4 space-y-2">
                @foreach ($legal as $i => $link)
                    <div wire:key="legal-{{ $i }}" class="flex items-center gap-2">
                        <input wire:model="legal.{{ $i }}.label" type="text" placeholder="Label"
                               class="w-1/3 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
                        <input wire:model="legal.{{ $i }}.url" type="text" placeholder="/legal or https://…"
                               class="flex-1 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
                        <button type="button" wire:click="removeLegal({{ $i }})" class="rounded-lg p-1.5 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]" aria-label="Remove legal link">
                            <x-icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
                @endforeach
                @error('legal.*.label') <span class="block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                @error('legal.*.url') <span class="block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
        </section>

        {{-- Footer credit phrasing (theme-integrity blueprint §2). The
             "Supreme Ideas Agency" name and its link to supremeideas.agency
             are never removable — only the surrounding phrasing is yours to
             pick. --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                <x-icon name="heart" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Footer credit line
            </h2>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">The "Supreme Ideas Agency" attribution always shows and always links to supremeideas.agency — pick how it's phrased.</p>

            <div class="mt-4 space-y-2">
                <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-3 text-sm">
                    <input type="radio" wire:model.live="credit_phrasing" value="product_of" class="mt-0.5 text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal)/0.4)]">
                    <span>A product of <span class="font-semibold">Supreme Ideas Agency</span>.</span>
                </label>
                <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-3 text-sm">
                    <input type="radio" wire:model.live="credit_phrasing" value="made_with_love" class="mt-0.5 text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal)/0.4)]">
                    <span>Made with love by <span class="font-semibold">Supreme Ideas Agency</span>.</span>
                </label>
                <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-3 text-sm">
                    <input type="radio" wire:model.live="credit_phrasing" value="custom" class="mt-0.5 text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal)/0.4)]">
                    <span>Custom…</span>
                </label>
            </div>

            @if ($credit_phrasing === 'custom')
                <div class="mt-3">
                    <input wire:model="credit_custom_text" type="text" maxlength="160" placeholder="e.g. An independent build by {agency}, for travellers everywhere."
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Must contain <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">{agency}</code> — that's where the linked name goes.</p>
                    @error('credit_custom_text') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
            @endif
        </section>

        <button type="submit" wire:loading.attr="disabled" wire:target="save,media,poster"
                class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Save changes</span>
            <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
        </button>
    </form>
</div>

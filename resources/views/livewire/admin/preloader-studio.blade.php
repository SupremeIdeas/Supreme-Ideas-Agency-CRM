{{-- nx:converted --}}
<div class="mx-auto max-w-5xl px-4 py-6" wire:key="preloader-studio">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Preloader Studio</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
            Give each kind of page its own loading animation. Brand-coloured by default, fully tunable, live the moment you save.
        </p>
    </div>

    {{-- Page-type selector --}}
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($pageTypes as $slug => $label)
            <button type="button" wire:click="loadType('{{ $slug }}')"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $type === $slug ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Inherit-from-default (non-default types only) --}}
    @if ($type !== 'default')
        <label class="mb-5 flex items-center gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-4 py-3">
            <input type="checkbox" wire:model.live="inherit" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
            <span class="text-sm font-medium text-[rgb(var(--nx-text))]">Inherit from Default — use the same preloader as every other page</span>
        </label>
    @endif

    <div class="grid gap-6 @if (! ($type !== 'default' && $inherit)) lg:grid-cols-[1fr_18rem] @endif">
        <div @class(['space-y-6', 'pointer-events-none opacity-40' => $type !== 'default' && $inherit])>
            {{-- Enabled --}}
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model.live="enabled" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                <span class="text-sm font-medium text-[rgb(var(--nx-text))]">Show a preloader on these pages</span>
            </label>

            {{-- Preset gallery --}}
            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Choose a preset</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($presets as $slug => $meta)
                        <button type="button" wire:click="selectPreset('{{ $slug }}')" wire:key="preset-{{ $slug }}"
                                class="group relative flex h-28 flex-col items-center justify-center gap-1 overflow-hidden rounded-2xl border p-2 transition {{ $preset === $slug ? 'border-[rgb(var(--nx-teal))] ring-2 ring-[rgb(var(--nx-teal)/0.4)]' : 'border-[rgb(var(--nx-line))] hover:border-[rgb(var(--nx-line-strong))]' }} bg-[rgb(var(--brand-navy))]">
                            <div class="flex flex-1 items-center justify-center">
                                <div class="nx-pl nx-pl--{{ $slug }}" style="transform:scale(.5)">
                                    @includeIf('components.preloaders.'.$slug, ['cfg' => ['loading_text' => 'Naara']])
                                </div>
                            </div>
                            <span class="relative z-10 text-[10px] font-semibold text-white/90">{{ $meta['label'] }}</span>
                            @if (! empty($meta['heavy']))
                                <span class="absolute right-1.5 top-1.5 z-10 rounded-full bg-[rgb(var(--nx-surface))] px-1.5 py-0.5 text-[8px] font-bold uppercase text-[rgb(var(--nx-text))] ring-1 ring-[rgb(var(--nx-gold))]" title="GPU-heavy — best for short overlays">Heavy</span>
                            @endif
                            @if (! empty($meta['favorite']))
                                <span class="absolute left-1.5 top-1.5 z-10 text-[rgb(var(--nx-gold-ink))]" title="Supreme Ideas favorite"><x-icon name="star" class="h-3 w-3" /></span>
                            @endif
                        </button>
                    @endforeach

                    {{-- Your own animation (GIF / WebP / Lottie JSON, per light + dark mode) --}}
                    <button type="button" wire:click="selectPreset('custom')" wire:key="preset-custom"
                            class="group relative flex h-28 flex-col items-center justify-center gap-1 overflow-hidden rounded-2xl border border-dashed p-2 transition {{ $isCustom ? 'border-[rgb(var(--nx-teal))] ring-2 ring-[rgb(var(--nx-teal)/0.4)]' : 'border-[rgb(var(--nx-line-strong))] hover:border-[rgb(var(--nx-line-strong))]' }} bg-[rgb(var(--brand-navy))]">
                        <span class="flex flex-1 items-center justify-center text-white/80"><x-icon name="upload" class="h-7 w-7" /></span>
                        <span class="relative z-10 text-[10px] font-semibold text-white/90">Your own animation</span>
                        @if ($custom !== [])
                            <span class="absolute right-1.5 top-1.5 z-10 rounded-full bg-[rgb(var(--nx-surface))] px-1.5 py-0.5 text-[8px] font-bold uppercase text-[rgb(var(--nx-text))] ring-1 ring-[rgb(var(--nx-ok))]">Uploaded</span>
                        @endif
                    </button>
                </div>
                @error('preset') <p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>

            {{-- Upload panel: one file per mode --}}
            @if ($isCustom)
                <div class="space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" wire:key="custom-panel">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Your own animation</h2>
                        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">
                            Upload a GIF, animated WebP or Lottie JSON for each mode. Visitors get the file that matches their light or dark mode;
                            if you only upload one, it is used for both. GIF and WebP up to {{ $customMaxImageKb / 1024 }} MB, Lottie up to {{ $customMaxLottieKb }} KB.
                            Lottie files must be exported without expressions and with images embedded.
                        </p>
                    </div>

                    @if ($customError)
                        <p class="rounded-xl border border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] px-3 py-2 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $customError }}</p>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['light' => 'Light mode', 'dark' => 'Dark mode'] as $variant => $variantLabel)
                            @php($meta = $custom[$variant] ?? null)
                            @php($field = 'upload'.ucfirst($variant))
                            <div class="rounded-2xl border border-[rgb(var(--nx-line))] p-3" wire:key="custom-{{ $variant }}">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $variantLabel }}</span>
                                    @if ($meta)
                                        <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[10px] font-bold uppercase text-[rgb(var(--nx-text-2))]">{{ $meta['kind'] === 'lottie' ? 'Lottie' : strtoupper($meta['kind']) }}</span>
                                    @endif
                                </div>
                                <div class="flex h-32 items-center justify-center overflow-hidden rounded-xl {{ $variant === 'dark' ? 'bg-[rgb(var(--brand-navy))]' : 'bg-white ring-1 ring-[rgb(var(--nx-line))]' }}">
                                    @if ($meta)
                                        <div class="nx-pl nx-pl--custom" style="transform:scale(.75)"
                                             x-data x-init="$nextTick(() => document.dispatchEvent(new Event('nx:lottie-init')))"
                                             wire:key="custom-pv-{{ $variant }}-{{ $meta['id'] }}">
                                            @include('components.preloaders.custom', ['cfg' => ['custom' => [$variant => $meta]], 'previewMode' => $variant])
                                        </div>
                                    @else
                                        <span class="px-3 text-center text-xs text-[rgb(var(--nx-text-2))]">
                                            {{ ($custom !== []) ? 'Using the other mode\'s file' : 'Nothing uploaded yet' }}
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">
                                        <x-icon name="upload" class="h-4 w-4" />
                                        <span wire:loading.remove wire:target="{{ $field }}">{{ $meta ? 'Replace' : 'Upload file' }}</span>
                                        <span wire:loading wire:target="{{ $field }}">Uploading…</span>
                                        <input type="file" class="sr-only" wire:model="{{ $field }}" accept="{{ $customAccept }}">
                                    </label>
                                    @if ($meta)
                                        <button type="button" wire:click="removeCustom('{{ $variant }}')" wire:loading.attr="disabled"
                                                class="rounded-xl border border-[rgb(var(--nx-line))] px-3 py-2 text-xs font-semibold text-[rgb(var(--nx-text-2))] transition hover:bg-[rgb(var(--nx-surface-2))]">Remove</button>
                                    @endif
                                </div>
                                @error($field) <p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Customization panel --}}
            <div class="space-y-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Customize</h2>

                {{-- Brand colours (artwork you upload keeps its own colours) --}}
                @unless ($isCustom)
                <label class="flex items-center gap-3">
                    <input type="checkbox" wire:model.live="useBrandColor" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                    <span class="text-sm font-medium text-[rgb(var(--nx-text))]">Use brand colours</span>
                </label>
                @endunless

                @unless ($useBrandColor || $isCustom)
                    <div class="flex flex-wrap gap-3 pl-7">
                        @for ($i = 0; $i < ($selectedMeta['c'] ?? 1); $i++)
                            <label class="flex items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
                                Colour {{ $i + 1 }}
                                <input type="color" wire:model.live="colors.{{ $i }}" class="h-8 w-10 cursor-pointer rounded border border-[rgb(var(--nx-line))] bg-transparent">
                            </label>
                        @endfor
                    </div>
                @endunless

                {{-- Neutral toggle for allow_neutral presets (e.g. dot-blast) --}}
                @if (! empty($selectedMeta['allow_neutral']))
                    <label class="flex items-center gap-3">
                        <input type="checkbox" wire:model.live="useNeutral" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                        <span class="text-sm font-medium text-[rgb(var(--nx-text))]">Use white / neutral instead of brand colour</span>
                    </label>
                @endif

                {{-- Loading text for text presets --}}
                @if (! empty($selectedMeta['text']))
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Loading text</span>
                        <input type="text" maxlength="24" wire:model.live="loadingText"
                               class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        @error('loadingText') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </label>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- Size --}}
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Size</span>
                        <select wire:model.live="size" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                            <option value="sm">Small</option>
                            <option value="md">Medium</option>
                            <option value="lg">Large</option>
                        </select>
                    </label>

                    {{-- Speed --}}
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Speed <span class="text-[rgb(var(--nx-text-2))]">({{ number_format($speed, 1) }}×)</span></span>
                        <input type="range" min="0.5" max="2" step="0.1" wire:model.live="speed" class="w-full accent-primary">
                    </label>

                    {{-- Opacity --}}
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Opacity <span class="text-[rgb(var(--nx-text-2))]">({{ round($opacity * 100) }}%)</span></span>
                        <input type="range" min="0.2" max="1" step="0.05" wire:model.live="opacity" class="w-full accent-primary">
                    </label>

                    {{-- Background opacity --}}
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Background dim <span class="text-[rgb(var(--nx-text-2))]">({{ round($bgOpacity * 100) }}%)</span></span>
                        <input type="range" min="0.2" max="1" step="0.05" wire:model.live="bgOpacity" class="w-full accent-primary">
                    </label>
                </div>

                {{-- Background colour --}}
                <label class="flex items-center gap-3 text-sm font-medium text-[rgb(var(--nx-text))]">
                    Background colour
                    <input type="color" wire:model.live="bgColor" class="h-8 w-10 cursor-pointer rounded border border-[rgb(var(--nx-line))] bg-transparent">
                    <button type="button" wire:click="$set('bgColor', null)" class="text-xs font-normal text-[rgb(var(--nx-text-2))] underline">{{ $isCustom ? 'Follow light / dark mode' : 'Use brand navy' }}</button>
                </label>

                {{-- Blur --}}
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" wire:model.live="blur" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                        <span class="text-sm font-medium text-[rgb(var(--nx-text))]">Blur the page behind</span>
                    </label>
                    @if ($blur)
                        <select wire:model.live="blurStyle" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-xs">
                            <option value="light">Light</option>
                            <option value="medium">Medium</option>
                            <option value="heavy">Heavy</option>
                        </select>
                    @endif
                </div>

                {{-- Per-section theme (blueprint 6.2): this section's own canvas, whatever the site theme is --}}
                <div class="block">
                    <span class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Preloader theme for this section</span>
                    <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Preloader theme">
                        @foreach (\App\Support\PreloaderSettings::THEMES as $tk => $tl)
                            <label class="flex items-center gap-1.5 rounded-full border border-[rgb(var(--nx-line))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]"><input type="radio" wire:model.live="theme" value="{{ $tk }}"> {{ $tl }}</label>
                        @endforeach
                        <button type="button" wire:click="applyThemeToAll('{{ $theme }}')" wire:confirm="Apply this theme to every configured section?" class="nc__chip">Apply to all</button>
                    </div>
                    <span class="mt-1 block text-xs text-[rgb(var(--nx-text-2))]">"Follow the site" uses the visitor's light or dark mode. "Always dark" or "Always light" draws this section's preloader on that canvas (and uses that mode's uploaded file) even when the site is in the other mode. An explicit background colour above still wins.</span>
                </div>

                {{-- Timing (UX/localization/platform-reach blueprint Phase D) --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Minimum display time (ms)</span>
                        <input type="number" min="0" max="10000" step="100" wire:model.live="minDisplayMs"
                               class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        <span class="mt-1 block text-xs text-[rgb(var(--nx-text-2))]">Keeps the preloader visible at least this long, even on a fast page. 0 = no minimum.</span>
                        @error('minDisplayMs') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Max shown per day</span>
                        <input type="number" min="0" max="100" step="1" wire:model.live="maxPerDay"
                               class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        <span class="mt-1 block text-xs text-[rgb(var(--nx-text-2))]">A returning visitor stops seeing it once they've hit this many today. 0 = unlimited.</span>
                        @error('maxPerDay') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </label>
                </div>
            </div>

            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            @if ($saved === $type)
                <span class="ml-3 text-sm font-medium text-[rgb(var(--nx-ok))]">Saved — live now.</span>
            @endif
        </div>

        {{-- Live preview pane (mirrors the real overlay) --}}
        @unless ($type !== 'default' && $inherit)
            @php($pv = $this->previewCfg())
            @php($pvVars = \App\Support\PreloaderSettings::resolveCssVars($pv))
            @php($pvBgRgb = $pv['bg_color'] ? \App\Support\BrandSettings::hexToChannels($pv['bg_color']) : null)
            @php($pvBg = $pvBgRgb ? 'rgb('.$pvBgRgb.' / '.$pv['bg_opacity'].')' : 'rgb(var(--brand-navy))')
            <div class="lg:sticky lg:top-6">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Live preview</h2>
                @if ($isCustom)
                    {{-- Both modes side by side, each on the surface a visitor in that mode would see. --}}
                    <div class="grid gap-3">
                        @foreach (['light' => 'rgb(255 255 255)', 'dark' => 'rgb(var(--brand-navy))'] as $mode => $surface)
                            <div>
                                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ ucfirst($mode) }} mode</span>
                                <div class="flex h-36 items-center justify-center overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))]" style="background:{{ $pvBgRgb ? $pvBg : $surface }}">
                                    @if ($custom !== [])
                                        <div class="nx-pl nx-pl--custom" style="{{ $pvVars }}opacity:var(--nx-pl-opacity,1);transform:scale(var(--nx-pl-scale,1));"
                                             x-data x-init="$nextTick(() => document.dispatchEvent(new Event('nx:lottie-init')))"
                                             wire:key="pv-custom-{{ $mode }}-{{ md5(json_encode($custom).$size) }}">
                                            @include('components.preloaders.custom', ['cfg' => $pv, 'previewMode' => $mode])
                                        </div>
                                    @else
                                        <span class="px-4 text-center text-xs text-[rgb(var(--nx-text-2))]">Upload a file to preview it</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                <div class="flex h-72 items-center justify-center overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))]"
                     style="background:{{ $pvBg }};">
                    <div class="nx-pl nx-pl--{{ $preset }}" style="{{ $pvVars }}opacity:var(--nx-pl-opacity,1);transform:scale(var(--nx-pl-scale,1));" wire:key="pv-{{ $preset }}-{{ $useBrandColor ? 'b' : 'm' }}">
                        @includeIf('components.preloaders.'.$preset, ['cfg' => $pv])
                    </div>
                </div>
                @endif
                <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Preview uses your current settings — this is exactly what visitors will see.</p>
            </div>
        @endunless
    </div>
</div>

{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Pages</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        Edit your public website with no code. Change any text, upload section images, hide sections, or reorder
        them — changes go live the moment you save. "Reset section" restores the original brand copy.
    </p>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach ($pages as $p)
                <button wire:click="$set('page', '{{ $p }}')"
                        class="rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $page === $p ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' }}">
                    {{ $p === 'faq' ? 'FAQ' : ucwords(str_replace('-', ' ', $p)) }}
                </button>
            @endforeach
        </div>
        <a href="{{ $page === 'home' ? url('/') : url('/'.$page) }}" target="_blank" rel="noopener"
           class="text-sm font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">View page</a>
    </div>

    @if ($this->switchable())
        {{-- Master sales pages: the admin can turn the whole page on or off after it is built. --}}
        <div class="mb-5 flex items-center justify-between gap-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
            <div>
                <p class="font-semibold text-[rgb(var(--nx-text))]">{{ \App\Support\MasterMarketing\PageSwitch::label($page) }}: {{ $pageLive ? 'on' : 'off' }}</p>
                <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">Off hides every link to this page and shows visitors a friendly notice instead. Your content below is kept.</p>
            </div>
            <button type="button" wire:click="togglePageLive" wire:loading.attr="disabled" role="switch" aria-checked="{{ $pageLive ? 'true' : 'false' }}"
                    class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition {{ $pageLive ? 'bg-[rgb(var(--nx-cta-b))]' : 'bg-[rgb(var(--nx-surface-3))]' }}">
                <span class="sr-only">Turn the page {{ $pageLive ? 'off' : 'on' }}</span>
                <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition {{ $pageLive ? 'translate-x-6' : 'translate-x-1' }}"></span>
            </button>
        </div>
    @endif

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($sections as $key => $fields)
            <details class="group rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]" wire:key="sec-{{ $page }}-{{ $key }}">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4">
                    <span class="flex items-center gap-3">
                        <x-icon name="chevron-right" class="h-4 w-4 text-[rgb(var(--nx-text-2))] transition group-open:rotate-90" />
                        <span class="font-semibold capitalize text-[rgb(var(--nx-text))]">{{ str_replace('-', ' ', $key) }}</span>
                        @unless ($fields['visible'] ?? true)
                            <x-ui.tag variant="soon">Hidden</x-ui.tag>
                        @endunless
                        @if ($fields['_copied'] ?? false)
                            <span class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-teal-ink))]">Reused</span>
                        @endif
                    </span>
                    <span class="flex items-center gap-1.5" x-on:click.prevent.stop>
                        <button type="button" wire:click="moveSection('{{ $key }}', -1)" title="Move up"
                                class="rounded-lg border border-[rgb(var(--nx-line))] p-1.5 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 -rotate-90" />
                        </button>
                        <button type="button" wire:click="moveSection('{{ $key }}', 1)" title="Move down"
                                class="rounded-lg border border-[rgb(var(--nx-line))] p-1.5 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-90" />
                        </button>
                        <label class="ml-2 flex items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
                            <x-ui.switch wire:model="sections.{{ $key }}.visible" label="Show section" /> Show
                        </label>
                    </span>
                </summary>

                <div class="space-y-3 border-t border-[rgb(var(--nx-line))] p-4">
                    @foreach ($fields as $field => $value)
                        @continue(in_array($field, ['visible', 'order', 'image', '_copied'], true))
                        {{-- Inline artwork fields (e.g. the three step renders) get an
                             image control instead of a text box. --}}
                        @if ($key === 'video' && $field === 'video_file')
                            @if (class_exists(\App\Http\Controllers\MasterMarketing\VideoUploadController::class))
                                <div x-show="$wire.sections.video.video_source === 'upload'" wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                    @include('livewire.admin.partials.master-marketing-video-uploader', ['section' => $key, 'field' => $field, 'value' => $value, 'sections' => $sections, 'page' => $page])
                                </div>
                            @endif
                        @elseif ($key === 'video' && $field === 'video_source')
                            <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Video source</label>
                                <select wire:model.live="sections.{{ $key }}.{{ $field }}" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm sm:w-72">
                                    <option value="upload">Upload a video file</option>
                                    <option value="link">YouTube, Vimeo or a direct link</option>
                                </select>
                            </div>
                        @elseif ($key === 'video' && $field === 'video_url')
                            <div x-show="$wire.sections.video.video_source === 'link'" wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Video link</label>
                                <input type="url" wire:model="sections.{{ $key }}.{{ $field }}" placeholder="https://www.youtube.com/watch?v=..."
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                                @php($detected = \App\Support\MasterMarketing\VideoSource::parseLink(trim((string) $value)))
                                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">
                                    @if ($detected)
                                        Detected: <span class="font-semibold">{{ ['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'file' => 'Direct video file'][$detected['kind']] ?? 'Video' }}</span>.
                                    @elseif (trim((string) $value) !== '')
                                        That link is not recognised. Use a YouTube or Vimeo address, or an https link ending in .mp4, .webm or .mov.
                                    @else
                                        Paste a YouTube or Vimeo address, or an https link to an .mp4, .webm or .mov file.
                                    @endif
                                </p>
                            </div>
                        @elseif ($key === 'video' && $field === 'player_color')
                            <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Player colour</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model="sections.{{ $key }}.{{ $field }}"
                                           {{-- nx:allow:start colour input default --}}value="{{ \App\Support\MasterMarketing\VideoSource::color(['player_color' => $value]) }}"{{-- nx:allow:end --}}
                                           class="h-9 w-12 cursor-pointer rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] p-1">
                                    <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $value ?: 'Brand teal (default)' }}</span>
                                    @if ($value)
                                        <button type="button" wire:click="$set('sections.{{ $key }}.{{ $field }}', '')" class="text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">Use brand teal</button>
                                    @endif
                                </div>
                            </div>
                        @elseif ($key === 'video' && str_starts_with($field, 'opt_'))
                            <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}" class="flex items-center justify-between gap-3 rounded-xl bg-[rgb(var(--nx-surface-2))] px-3 py-2">
                                <span class="text-sm text-[rgb(var(--nx-text))]">{{ ['opt_logo' => 'Show the brand logo on the player', 'opt_autoplay' => 'Play automatically (muted) when it scrolls into view', 'opt_loop' => 'Loop the video'][$field] ?? str_replace('_', ' ', $field) }}</span>
                                <button type="button" role="switch" aria-checked="{{ \App\Support\MasterMarketing\VideoSource::on($fields, $field, false) ? 'true' : 'false' }}"
                                        wire:click="$set('sections.{{ $key }}.{{ $field }}', '{{ \App\Support\MasterMarketing\VideoSource::on($fields, $field, false) ? 'off' : 'on' }}')"
                                        class="relative h-6 w-11 shrink-0 rounded-full transition {{ \App\Support\MasterMarketing\VideoSource::on($fields, $field, false) ? 'bg-[rgb(var(--nx-cta-b))]' : 'bg-[rgb(var(--nx-line-strong))]' }}">
                                    <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all {{ \App\Support\MasterMarketing\VideoSource::on($fields, $field, false) ? 'left-[22px]' : 'left-0.5' }}"></span>
                                </button>
                            </div>
                        @elseif ($key === 'video' && $field === 'transcript')
                            <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Transcript (optional, shown under the player)</label>
                                <textarea wire:model="sections.{{ $key }}.{{ $field }}" rows="5" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"></textarea>
                            </div>
                        @elseif (str_ends_with($field, '_image'))
                            @php($fieldImg = \App\Support\SiteContent::imageUrl((string) $value))
                            <div wire:key="fi-{{ $page }}-{{ $key }}-{{ $field }}" class="flex flex-wrap items-center gap-3 rounded-xl bg-[rgb(var(--nx-surface-2))] p-3">
                                <span class="text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ str_replace(['_image', '_'], ['', ' '], $field) }} image</span>
                                @if ($fieldImg)
                                    <img src="{{ $fieldImg }}" class="h-14 w-20 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] object-contain p-1">
                                @endif
                                @if ($imageSection === $key && $imageField === $field)
                                    <input type="file" wire:model="imageUpload" accept="image/png,image/jpeg,image/webp"
                                           class="text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b))] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                    <button type="button" wire:click="uploadFieldImage('{{ $key }}', '{{ $field }}')" wire:loading.attr="disabled"
                                            class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Use image</button>
                                    @error('imageUpload') <p class="w-full text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                                @else
                                    <button type="button" wire:click="$set('imageSection', '{{ $key }}'); $set('imageField', '{{ $field }}')" class="text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">Replace</button>
                                    <button type="button" wire:click="removeFieldImage('{{ $key }}', '{{ $field }}')" class="text-xs font-medium text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))] hover:underline">Reset to default</button>
                                @endif
                                @if ($field === 'showcase_image')
                                    <p class="w-full text-[11px] leading-relaxed text-[rgb(var(--nx-text-2))]">Shown below the description, <span class="font-medium">not as a background</span>. A landscape or device-mockup image works best (e.g. ~1200×800). It displays at a capped height, so an oversized upload scales down cleanly.</p>
                                @endif
                            </div>
                        @elseif ($field === 'subheadline_color')
                            {{-- Hero customization (owner request): a real colour swatch,
                                 not a raw hex text box. Empty = automatic (the two-tone
                                 mix-blend effect on the WebGL branch, or the shipped
                                 fixed tone on the admin-image branch). --}}
                            <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Description text colour</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model="sections.{{ $key }}.{{ $field }}"
                                           {{-- nx:allow:start colour input default --}}value="{{ $value ?: '#334155' }}"{{-- nx:allow:end --}}
                                           class="h-9 w-12 cursor-pointer rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] p-1">
                                    <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $value ?: 'Automatic (two-tone globe blend)' }}</span>
                                    @if ($value)
                                        <button type="button" wire:click="$set('sections.{{ $key }}.{{ $field }}', '')"
                                                class="text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">Reset to automatic</button>
                                    @endif
                                </div>
                            </div>
                        @elseif ($field === 'glass_depth')
                            {{-- Hero customization: a glass-morphism accent card rendered
                                 below the description, admin-tunable 0 (off) - 100 (deep
                                 blur/shadow) — see hero.blade.php's Premium Glass wrapper. --}}
                            <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                <label class="mb-1 flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                                    <span>Glass card depth (below description)</span>
                                    <span class="normal-case text-[rgb(var(--nx-text-2))]">{{ (int) $value === 0 ? 'Off' : (int) $value.'%' }}</span>
                                </label>
                                <input type="range" min="0" max="100" step="5" wire:model.live="sections.{{ $key }}.{{ $field }}"
                                       class="w-full accent-primary">
                            </div>
                        @elseif ($field === 'radius_top')
                            {{-- Phase C: top-left/top-right corner radius for this section's
                                 container (px). >0 pulls the section up over the previous one
                                 so the curve reveals the seam colour instead of a flat line. --}}
                            <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                <label class="mb-1 flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                                    <span>Top corner radius</span>
                                    <span class="normal-case text-[rgb(var(--nx-text-2))]">{{ (int) $value }}px</span>
                                </label>
                                <input type="range" min="0" max="80" step="2" wire:model.live="sections.{{ $key }}.{{ $field }}"
                                       class="w-full accent-primary">
                            </div>
                        @elseif ($field === 'image_scale')
                            {{-- Hero customization: scales the mockup + stat-card visual
                                 group (mobile, tablet AND desktop) around its shipped
                                 100% size — see hero.blade.php's --hero-img-scale use. --}}
                            <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                <label class="mb-1 flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                                    <span>Hero image size</span>
                                    <span class="normal-case text-[rgb(var(--nx-text-2))]">{{ (int) $value }}%</span>
                                </label>
                                <input type="range" min="50" max="150" step="5" wire:model.live="sections.{{ $key }}.{{ $field }}"
                                       class="w-full accent-primary">
                            </div>
                        @else
                            <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ str_replace('_', ' ', $field) }}</label>
                                @if (mb_strlen((string) $value) > 90)
                                    <textarea wire:model="sections.{{ $key }}.{{ $field }}" rows="3"
                                              class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"></textarea>
                                @else
                                    <input type="text" wire:model="sections.{{ $key }}.{{ $field }}"
                                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                                @endif
                            </div>
                        @endif
                    @endforeach

                    {{-- Section image --}}
                    <div class="flex flex-wrap items-center gap-3 border-t border-[rgb(var(--nx-line))] pt-3">
                        @if (! empty($fields['image']))
                            <img src="{{ $fields['image'] }}" class="h-14 w-24 rounded-lg border border-[rgb(var(--nx-line))] object-cover">
                            <button type="button" wire:click="removeImage('{{ $key }}')" class="text-xs font-medium text-[rgb(var(--nx-bad))] hover:underline">Remove image</button>
                        @else
                            <span class="text-xs text-[rgb(var(--nx-text-2))]">No section image</span>
                        @endif
                        @if ($imageSection === $key)
                            <input type="file" wire:model="imageUpload" accept="image/png,image/jpeg,image/webp"
                                   class="text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b))] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                            <button type="button" wire:click="uploadImage('{{ $key }}')" wire:loading.attr="disabled"
                                    class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Use image</button>
                            @error('imageUpload') <p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                        @else
                            <button type="button" wire:click="$set('imageSection', '{{ $key }}')" class="text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                                {{ empty($fields['image']) ? 'Add image' : 'Replace image' }}
                            </button>
                        @endif
                        {{-- Reuse on another page (portable sections only). --}}
                        @if (in_array($key, \App\Support\SiteContent::PORTABLE_SECTIONS, true))
                            <div class="ml-auto flex items-center gap-2" x-data="{ target: '' }">
                                <label class="text-xs font-medium text-[rgb(var(--nx-text-2))]">Copy to</label>
                                <select x-model="target" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-xs">
                                    <option value="">Choose a page…</option>
                                    @foreach ($pages as $p)
                                        @continue($p === $page)
                                        <option value="{{ $p }}">{{ $p === 'faq' ? 'FAQ' : ucfirst(str_replace('-', ' ', $p)) }}</option>
                                    @endforeach
                                </select>
                                <button type="button"
                                        x-on:click="if (target) { $wire.copyTo('{{ $key }}', target); target = '' }"
                                        class="rounded-lg border border-[rgb(var(--nx-teal)/0.3)] bg-[rgb(var(--nx-cta-b)/0.05)] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.1)]">Copy</button>
                            </div>
                        @endif
                        @if ($fields['_copied'] ?? false)
                            <button type="button" wire:click="removeSection('{{ $key }}')" wire:confirm="Remove this reused section from this page?"
                                    class="text-xs font-medium text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))] hover:underline">Remove</button>
                        @else
                            <button type="button" wire:click="resetSection('{{ $key }}')" wire:confirm="Restore this section to the original brand copy?"
                                    class="{{ in_array($key, \App\Support\SiteContent::PORTABLE_SECTIONS, true) ? '' : 'ml-auto' }} text-xs font-medium text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))] hover:underline">Reset section</button>
                        @endif
                    </div>
                </div>
            </details>
        @endforeach
    </div>

    <div class="sticky bottom-4 mt-6 flex flex-wrap justify-end gap-2">
        <x-ui.btn variant="primary" type="button" wire:click="save" target="save" icon="badge-check">Save page</x-ui.btn>
        <x-ui.btn variant="ghost" type="button" wire:click="translateNow" target="translateNow" icon="globe" title="Translate the text you edited on this page into every other language">Translate edits</x-ui.btn>
    </div>
</div>

{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Homepage media</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">The video section and the scroll-revealed story, both shown after the flag carousel on the marketing homepage.</p>
    </div>

    @if ($saved)
        <div class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- ===================== Video section (§8) ===================== --}}
    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="mb-4 text-base font-semibold text-[rgb(var(--nx-text))]">Video section</h2>

        <form wire:submit="saveVideoHeading" class="mb-6 space-y-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Section heading</label>
                <input type="text" wire:model="vid_heading" placeholder="{{ \App\Support\HomeVideos::DEFAULT_HEADING }}"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('vid_heading') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Sub-heading</label>
                <input type="text" wire:model="vid_subheading" placeholder="{{ \App\Support\HomeVideos::DEFAULT_SUBHEADING }}"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save heading</button>
            </div>
        </form>

        {{-- Existing entries --}}
        <div class="mb-6 space-y-2">
            @forelse ($videos as $v)
                <div wire:key="hv-{{ $v->id }}" class="flex items-center gap-3 rounded-xl border border-[rgb(var(--nx-line))] p-2.5">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[rgb(var(--nx-surface-3))]">
                        @if ($v->posterOrFallback())
                            <img src="{{ $v->posterOrFallback() }}" alt="" class="h-full w-full object-cover">
                        @else
                            <x-icon name="play" class="h-5 w-5 text-[rgb(var(--nx-text-2))]" />
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $v->title }}</p>
                        <p class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ ucfirst($v->source_type) }} · {{ ucfirst($v->orientation) }}{{ $v->is_active ? '' : ' · hidden' }}</p>
                    </div>
                    <button type="button" wire:click="toggleVideo({{ $v->id }})"
                            class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                        {{ $v->is_active ? 'Hide' : 'Show' }}
                    </button>
                    <button type="button" wire:click="deleteVideo({{ $v->id }})" wire:confirm="Remove this video?"
                            class="rounded-lg p-1.5 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-bad)/0.1)] hover:text-[rgb(var(--nx-bad))]">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No videos yet — add one below. The section is hidden on the homepage until at least one is active.</p>
            @endforelse
        </div>

        {{-- Add a video --}}
        <form wire:submit="addVideo" class="space-y-3 rounded-xl bg-[rgb(var(--nx-surface-2))] p-4">
            <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">Add a video</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title</label>
                    <input type="text" wire:model="v_title" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @error('v_title') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Orientation</label>
                    <select wire:model="v_orientation" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        <option value="portrait">Portrait (default on mobile)</option>
                        <option value="landscape">Landscape</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Source</label>
                <select wire:model.live="v_source" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    <option value="youtube">YouTube link</option>
                    <option value="upload">Upload a clip (MP4/WebM)</option>
                </select>
            </div>

            @if ($v_source === 'youtube')
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">YouTube link or video id</label>
                    <input type="text" wire:model="v_youtube" placeholder="https://youtu.be/…"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @error('v_youtube') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Video file (MP4/WebM, ≤10 MB)</label>
                    <input type="file" wire:model="v_upload" accept="video/mp4,video/webm"
                           class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-[rgb(var(--nx-teal-ink))]">
                    @error('v_upload') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Poster image <span class="text-[rgb(var(--nx-text-2))]">(optional — YouTube thumbnail used if blank; standard 1280×720)</span></label>
                <input type="file" wire:model="v_poster" accept="{{ \App\Support\MediaStorage::acceptAttribute() }}"
                       class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-[rgb(var(--nx-teal-ink))]">
                @error('v_poster') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="addVideo,v_upload,v_poster"
                        class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <span wire:loading.remove wire:target="addVideo">Add video</span>
                    <span wire:loading wire:target="addVideo,v_upload,v_poster" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                </button>
            </div>
        </form>
    </div>

    {{-- ===================== Story section (§9) ===================== --}}
    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <form wire:submit="saveStory" class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Story section</h2>
                <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text-2))]">
                    <x-ui.switch wire:model="story_enabled" label="Show story section" />
                    <span>Show on homepage</span>
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Eyebrow</label>
                    <input type="text" wire:model="story_eyebrow" placeholder="{{ \App\Support\HomeStory::DEFAULTS['eyebrow'] }}"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Transition line (leads into the next section)</label>
                    <input type="text" wire:model="story_transition" placeholder="{{ \App\Support\HomeStory::DEFAULTS['transition'] }}"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Heading</label>
                <input type="text" wire:model="story_heading" placeholder="{{ \App\Support\HomeStory::DEFAULTS['heading'] }}"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('story_heading') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Body <span class="text-[rgb(var(--nx-text-2))]">(blank line between paragraphs)</span></label>
                <textarea wire:model="story_body" rows="5" placeholder="Tell the Naara story…"
                          class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"></textarea>
                @error('story_body') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save story</button>
            </div>
        </form>
    </div>
</div>

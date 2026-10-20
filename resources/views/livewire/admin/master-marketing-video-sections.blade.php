{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Marketing video sections</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Put a video in the hero of every marketing page (a rounded poster card with a play button right under the headline), or further down the Homepage, Naara Chat, NaaraBrain, Naara Gift and White Label pages. Paste a YouTube or Vimeo link, or upload a file; either way set a poster image so the card looks right before anyone presses play. A slot with no visible video shows nothing, so there is never an empty gap. Hiding or removing a video takes effect straight away.</p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-4 text-sm">
        <p class="max-w-xl text-xs text-[rgb(var(--nx-text-2))]">New uploads go straight into a private vault: no public file address, played through short-lived signed links, no download button. Videos uploaded earlier can be moved in with one click.</p>
        <button type="button" wire:click="lockExisting" wire:confirm="Move every earlier uploaded video into the private vault?" wire:loading.attr="disabled" wire:target="lockExisting" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-4 py-2 text-xs font-semibold text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-3))] disabled:opacity-60">Lock earlier uploads</button>
    </div>

    @foreach ($slots as $key => $label)
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">{{ $label }}</h2>
            <div class="mt-3 space-y-2">
                @forelse ($rows[$key] ?? [] as $v)
                    <div wire:key="mmv-{{ $v->id }}" class="flex flex-wrap items-center gap-2 rounded-xl border border-[rgb(var(--nx-line))] p-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $v->title }}</p>
                            <p class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $v->source_type === 'upload' ? 'Uploaded file' : 'Link' }}{{ $v->is_visible ? '' : ' · hidden' }}{{ $v->starts_at ? ' · from '.$v->starts_at->format('j M Y') : '' }}{{ $v->ends_at ? ' · until '.$v->ends_at->format('j M Y') : '' }}</p>
                        </div>
                        <button type="button" wire:click="move({{ $v->id }}, 'up')" class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1.5 text-xs text-[rgb(var(--nx-text-2))]" aria-label="Move up">Up</button>
                        <button type="button" wire:click="move({{ $v->id }}, 'down')" class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1.5 text-xs text-[rgb(var(--nx-text-2))]" aria-label="Move down">Down</button>
                        <button type="button" wire:click="toggle({{ $v->id }})" wire:loading.attr="disabled" class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $v->is_visible ? 'Hide' : 'Show' }}</button>
                        <button type="button" wire:click="delete({{ $v->id }})" wire:confirm="Remove this video?" class="rounded-lg p-1.5 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]" aria-label="Remove"><x-icon name="x" class="h-4 w-4" /></button>
                    </div>
                @empty
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">No video here yet.</p>
                @endforelse
            </div>
        </div>
    @endforeach

    {{-- Videos attached to single blog posts and guides --}}
    @php($dynamic = $rows->filter(fn ($r, $k) => ! isset($slots[$k])))
    @foreach ($dynamic as $key => $list)
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">@if (str_starts_with($key, 'blog:'))Blog post: {{ $posts->firstWhere('id', (int) substr($key, 5))?->title ?? '#'.substr($key, 5) }}@else Guide: {{ ucfirst(str_replace('_', ' ', substr($key, 6))) }}@endif</h2>
            <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">Shown as a carousel in this order; the next one starts when one ends.</p>
            <div class="mt-3 space-y-2">
                @foreach ($list as $v)
                    <div wire:key="mmv-{{ $v->id }}" class="flex flex-wrap items-center gap-2 rounded-xl border border-[rgb(var(--nx-line))] p-2.5">
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $v->title }}</p><p class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $v->source_type === 'upload' ? 'Uploaded file' : 'Link' }} · {{ $v->orientation }}{{ $v->is_visible ? '' : ' · hidden' }}</p></div>
                        <button type="button" wire:click="move({{ $v->id }}, 'up')" class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1.5 text-xs text-[rgb(var(--nx-text-2))]">Up</button>
                        <button type="button" wire:click="move({{ $v->id }}, 'down')" class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1.5 text-xs text-[rgb(var(--nx-text-2))]">Down</button>
                        <button type="button" wire:click="toggle({{ $v->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $v->is_visible ? 'Hide' : 'Show' }}</button>
                        <button type="button" wire:click="delete({{ $v->id }})" wire:confirm="Remove this video?" class="rounded-lg p-1.5 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]" aria-label="Remove"><x-icon name="x" class="h-4 w-4" /></button>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <form wire:submit="add" class="space-y-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Add a video</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Where it shows</label>
                <select wire:model.live="slot" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @foreach ($slots as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    <option value="@blog">A blog post (any number of videos)</option><option value="@guide">A user guide</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Source</label>
                <select wire:model.live="source" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    <option value="url">YouTube / Vimeo / video link</option><option value="upload">Upload a file</option>
                </select>
            </div>
        </div>
        @if ($slot === '@blog')
            <div><label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Blog post</label>
                <select wire:model="targetId" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"><option value="">Choose a post</option>@foreach ($posts as $p)<option value="{{ $p->id }}">{{ \Illuminate\Support\Str::limit($p->title, 80) }}</option>@endforeach</select>
                @error('targetId') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror</div>
        @elseif ($slot === '@guide')
            <div><label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">User guide</label>
                <select wire:model="targetId" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"><option value="">Choose a guide</option>@foreach ($guides as $g)<option value="{{ $g }}">{{ ucfirst(str_replace('_', ' ', $g)) }}</option>@endforeach</select>
                @error('targetId') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror</div>
        @endif
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title</label>
            <input type="text" wire:model="title" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            @error('title') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Label chip on a hero card (optional)</label><input type="text" wire:model="label" maxlength="40" placeholder="Product tour" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">@error('label') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Length shown on the card (optional)</label><input type="text" wire:model="duration" placeholder="2:30" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">@error('duration') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">Use minutes:seconds, like 2:30.</p> @enderror</div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Short description (optional)</label>
            <input type="text" wire:model="description" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
        </div>
        @if ($source === 'url')
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Link</label>
                <input type="url" wire:model="url" placeholder="https://www.youtube.com/watch?v=..." class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        @else
            <div wire:key="uploader-{{ $uploaderKey }}">
                @include('livewire.admin.partials.master-marketing-video-uploader', ['value' => $fileUrl, 'field' => 'file', 'propsOverride' => ['file' => 'fileUrl', 'source' => 'source', 'poster' => 'posterUrl'], 'posterCurrent' => $posterUrl])
                @error('fileUrl') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">Upload the video first, then press Add video.</p> @enderror
            </div>
        @endif
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Poster image (the fallback picture; optional for an upload, a YouTube link falls back to its own thumbnail)</label>
            <input type="file" wire:model="poster" accept="{{ \App\Support\MediaStorage::acceptAttribute() }}" class="block w-full text-sm text-[rgb(var(--nx-text-2))]">
            @error('poster') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Show from (optional)</label><input type="datetime-local" wire:model="starts_at" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"></div>
            <div><label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Hide after (optional)</label><input type="datetime-local" wire:model="ends_at" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">@error('ends_at') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror</div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">How was it filmed?</label>
                <select wire:model="orientation" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"><option value="landscape">Landscape (wide): full screen turns the phone sideways</option><option value="portrait">Portrait (tall): full screen stays upright</option></select></div>
            <label class="flex items-center gap-2 self-end pb-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="protect"> Block saving an uploaded file (no download button, no right-click)</label>
        </div>
        <div class="flex flex-wrap gap-4 text-sm text-[rgb(var(--nx-text))]">
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="autoplay"> Silent looping preview inside the card (uploaded files; skipped for Data Saver and reduced motion)</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="loop"> Loop</label>
        </div>
        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="add,poster" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">Add video</button>
        </div>
    </form>
</div>

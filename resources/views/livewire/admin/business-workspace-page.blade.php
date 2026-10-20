{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-3xl space-y-4 p-4">
    @php($inp = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]')
    <div>
        <a href="{{ route('admin.business-suite') }}" wire:navigate class="text-sm text-[rgb(var(--nx-text-2))]">Business Suite</a>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Workspace page</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">The title, rotating words, description and picture at the top of every member's Business workspace.</p>
    </div>
    @if ($saved)<div role="status" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm text-[rgb(var(--nx-text))]">{{ $saved }}</div>@endif

    <form wire:submit="save" class="space-y-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <label class="block"><span class="font-semibold">Title</span>
            <input type="text" wire:model="title" maxlength="40" class="mt-1 {{ $inp }}"></label>
        @error('title')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror

        <fieldset>
            <legend class="font-semibold">Rotating words (up to 4)</legend>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Shown one at a time as "Your <em>word</em>" under the title. Leave a box empty to use fewer.</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @for ($i = 0; $i < 4; $i++)
                    <input type="text" wire:model="words.{{ $i }}" maxlength="24" aria-label="Rotating word {{ $i + 1 }}" placeholder="Word {{ $i + 1 }}" class="{{ $inp }}">
                @endfor
            </div>
            @error('words.*')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
            @error('words.0')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        </fieldset>

        <label class="block"><span class="font-semibold">Description</span>
            <textarea wire:model="description" rows="3" maxlength="320" class="mt-1 {{ $inp }}"></textarea></label>
        @error('description')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror

        <div class="flex flex-wrap gap-2">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill ns-cta--sm">Save wording</button>
            <button type="button" wire:click="resetAll" wire:confirm="Reset the title, words and description to the defaults?" class="nc__chip">Reset wording</button>
        </div>
    </form>

    <section class="space-y-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <h2 class="font-semibold">Picture</h2>
        <img src="{{ $hero['src'] }}" alt="Current workspace picture" class="max-h-40 w-auto rounded-xl" loading="lazy">
        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $custom ? 'A custom picture is in use.' : 'The shipped illustration is in use.' }} Use a wide PNG, JPG or WebP at least 600px across (about 1400 x 400 works best, transparent background looks best). It is converted to WebP automatically and fades at the bottom on the page.</p>
        <div class="flex flex-wrap items-center gap-2">
            <input type="file" wire:model="image" accept="image/png,image/jpeg,image/webp" aria-label="Choose a picture" class="text-xs">
            <button type="button" wire:click="uploadImage" wire:loading.attr="disabled" wire:target="uploadImage,image" class="ns-cta ns-cta--pill ns-cta--sm">Upload</button>
            @if ($custom)<button type="button" wire:click="resetImage" wire:confirm="Go back to the shipped illustration?" class="nc__chip">Reset picture</button>@endif
        </div>
        @error('image')<p class="text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
        <div wire:loading wire:target="image" class="text-xs text-[rgb(var(--nx-text-2))]">Uploading...</div>
    </section>
</div>

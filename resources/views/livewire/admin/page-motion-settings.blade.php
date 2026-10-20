{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-2xl space-y-4 p-4">
    <div>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Page transitions</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">A soft fade or a fluid spring when members move between dashboard pages. The header and bottom bar stay still; only the page content moves. Pure CSS (opacity and transform), so it stays light. It never runs on this admin area, the marketing site, or for members whose device asks for reduced motion.</p>
    </div>
    @if ($saved)<div role="status" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm text-[rgb(var(--nx-text))]">{{ $saved }}</div>@endif

    <form wire:submit="save" class="space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <label class="flex items-center justify-between gap-3">
            <span><b>Page transitions</b><br><span class="text-[rgb(var(--nx-text-2))]">Turn the whole effect on or off for every member.</span></span>
            <input type="checkbox" wire:model="enabled" class="h-5 w-5 accent-[rgb(var(--nx-cta-b))]" aria-label="Page transitions on or off">
        </label>
        <fieldset>
            <legend class="mb-1 font-semibold">Style</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($styles as $k => $l)
                    <label class="flex items-start gap-2 rounded-xl border border-[rgb(var(--nx-line))] p-3 has-[:checked]:border-[rgb(var(--nx-cta-b))]">
                        <input type="radio" wire:model="style" value="{{ $k }}" class="mt-1 accent-[rgb(var(--nx-cta-b))]">
                        <span><b>{{ $l }}</b><br><span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $k === 'fade' ? 'Content dims on tap and fades in with a small lift.' : 'Content springs in, and the first blocks of the page rise one after another.' }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="mb-1 font-semibold">Speed</legend>
            <div class="flex flex-wrap gap-2">
                @foreach ($speeds as $k => $l)
                    <label class="flex items-center gap-2 rounded-full border border-[rgb(var(--nx-line))] px-3 py-1.5 has-[:checked]:border-[rgb(var(--nx-cta-b))]"><input type="radio" wire:model="speed" value="{{ $k }}" class="accent-[rgb(var(--nx-cta-b))]"> {{ $l }}</label>
                @endforeach
            </div>
        </fieldset>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving…</span></button>
    </form>
</div>

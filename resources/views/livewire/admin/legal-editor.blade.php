{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Legal pages</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">Edit the public legal documents. These live at stable URLs that Google/Facebook login reviews and app stores expect. Ships with best-practice defaults — edit only what you need, or reset to the original.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] p-3 text-sm text-[rgb(var(--nx-teal-ink))]">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ($docs as $doc)
            <button type="button" wire:click="$set('slug', '{{ $doc['slug'] }}')"
                    @class([
                        'rounded-full px-4 py-1.5 text-sm font-medium transition',
                        'bg-[rgb(var(--nx-cta-b))] text-white' => $slug === $doc['slug'],
                        'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' => $slug !== $doc['slug'],
                    ])>{{ $doc['title'] }}</button>
        @endforeach
    </div>

    <form wire:submit="save" class="space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        @php($review = \App\Support\LegalReview::record($slug))
        <div @class(['flex flex-wrap items-center justify-between gap-2 rounded-xl px-3 py-2 text-xs', 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]' => $review, 'bg-[rgb(var(--nx-warn)/0.14)] text-[rgb(var(--nx-warn))]' => ! $review])>
            <span class="flex items-center gap-2 font-semibold">
                <x-icon :name="$review ? 'badge-check' : 'alert-triangle'" class="h-4 w-4 shrink-0" />
                @if ($review) Reviewed by counsel on {{ $review['on'] }}@if ($review['by']) (recorded by {{ $review['by'] }})@endif
                @else Draft: not yet reviewed by counsel. These are starting points, not legal advice.
                @endif
            </span>
            @if (! $review && auth()->user()?->hasRole('super_admin'))
                <button type="button" wire:click="markReviewed" wire:loading.attr="disabled" wire:target="markReviewed"
                        wire:confirm="Record that a lawyer has reviewed the CURRENT text of this page? Saving any edit later clears this."
                        class="rounded-lg border border-current px-2.5 py-1 font-semibold hover:opacity-80 disabled:opacity-60">Mark as reviewed by counsel</button>
            @endif
        </div>
        <div class="flex items-center justify-between">
            <a href="{{ route('legal.show', $slug) }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                <x-icon name="globe" class="h-3.5 w-3.5" /> View public page: /legal/{{ $slug }}
            </a>
            <button type="button" wire:click="resetToDefault" wire:confirm="Reset this document to the shipped best-practice copy? Your edits will be removed."
                    class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline hover:text-[rgb(var(--nx-bad))]">Reset to default</button>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title</label>
            <input wire:model="title" type="text"
                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
            @error('title') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Body</label>
            <p class="mb-1 text-[11px] text-[rgb(var(--nx-text-2))]">Use <code class="font-mono">## Heading</code> for section headings, <code class="font-mono">- item</code> for bullets, and a blank line between paragraphs. <code class="font-mono">{brand}</code> is replaced with your brand name. HTML is not allowed (rendered as plain text for safety).</p>
            <textarea wire:model="body" rows="18"
                      class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 font-mono text-xs leading-relaxed text-[rgb(var(--nx-text))]"></textarea>
            @error('body') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Save page</span>
            <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
        </button>
    </form>
</div>

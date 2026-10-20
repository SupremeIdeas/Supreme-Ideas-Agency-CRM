<x-layouts.app title="What's New — NaaraSim">
    <main class="mx-auto max-w-2xl px-4 py-12">
        <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-1 text-sm text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))] ">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Home
        </a>
        <h1 class="text-3xl font-bold text-[rgb(var(--nx-text))] ">What's new</h1>
        <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))] ">The latest updates and improvements, in order.</p>

        @if ($entries->isEmpty())
            <p class="mt-10 rounded-xl border border-dashed border-[rgb(var(--nx-line))] py-10 text-center text-sm text-[rgb(var(--nx-text-2))] ">
                Nothing published yet — check back soon.
            </p>
        @else
            <div class="mt-8 space-y-5">
                @foreach ($entries as $entry)
                    <article wire:key="cl-{{ $entry->id }}" class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5  ">
                        <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-teal-ink))]">
                            <span class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 ">{{ $entry->version }}</span>
                            <span class="text-[rgb(var(--nx-text-2))]">{{ $entry->released_at->format('j M Y') }}</span>
                        </div>
                        <h2 class="text-lg font-semibold text-[rgb(var(--nx-text))] ">{{ $entry->summary }}</h2>
                        @if ($entry->notes)
                            <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))] ">{{ $entry->notes }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
            <div class="mt-6">{{ $entries->links() }}</div>
        @endif
    </main>
</x-layouts.app>

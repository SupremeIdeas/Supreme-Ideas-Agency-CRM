{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="space-y-6 pb-6 pt-6 sm:pt-10">
    <div>
        <span class="inline-flex items-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">Help Center</span>
        <h1 class="mt-4 text-3xl font-bold text-[rgb(var(--nx-text))] sm:text-4xl">
            How can we <span class="text-[rgb(var(--nx-teal-ink))]">help you?</span>
        </h1>
        <p class="mt-3 max-w-lg text-[rgb(var(--nx-text-2))]">Search, browse articles or start a chat with our support team.</p>
    </div>

    {{-- Primary action: continue an open thread, or start a fresh one. Chat
         itself needs an account — guests are routed to sign in first. --}}
    <div class="rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface)/0.9)] p-6 text-center shadow-sm backdrop-blur">
        @if ($openConversation)
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]">
                <x-avatar :src="$agentAvatar" :name="$agentName" size="h-14 w-14" />
            </span>
            <h2 class="mt-3 text-lg font-bold text-[rgb(var(--nx-text))]">You have an open conversation</h2>
            <p class="mx-auto mt-1 max-w-sm text-sm text-[rgb(var(--nx-text-2))]">Pick up where you left off with {{ $agentName }}{{ $openConversation->assigned_to ? ' and our team' : '' }}.</p>
            <a href="{{ route('support') }}" wire:navigate
               class="mt-4 inline-flex items-center gap-2 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-5 py-2.5 text-sm font-bold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.15)]">
                <x-icon name="message-circle" class="h-4 w-4" /> Continue chat <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        @else
            <img src="{{ asset('images/illustrations/es-help.svg') }}" alt="" width="240" height="180" loading="lazy" class="mx-auto h-36 w-auto" data-empty-art>
            <h2 class="mt-3 text-lg font-bold text-[rgb(var(--nx-text))]">No conversations yet.</h2>
            <p class="mx-auto mt-1 max-w-sm text-sm text-[rgb(var(--nx-text-2))]">Start a new chat and our team will be happy to help you.</p>
            <a href="{{ auth()->check() ? route('support') : route('login') }}" wire:navigate
               class="mt-4 inline-flex items-center gap-2 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-5 py-2.5 text-sm font-bold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.15)]">
                <x-icon name="message-circle" class="h-4 w-4" /> Start a new chat <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        @endif
    </div>

    {{-- Search + recent articles. --}}
    <div class="rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface)/0.9)] p-5 shadow-sm backdrop-blur">
        <div data-nx-field class="flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-surface-3))] px-3 py-2.5 shadow-[inset_0_0_0_1px_rgb(var(--nx-edge))]">
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search for articles"
                   class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-[rgb(var(--nx-text))] focus:ring-0">
            <x-icon name="search" class="h-4 w-4 shrink-0 text-[rgb(var(--nx-text-2))]" />
        </div>

        <h3 class="mt-5 text-sm font-bold text-[rgb(var(--nx-text))]">Recent articles</h3>
        <div class="mt-2 space-y-2">
            @forelse ($articles as $post)
                <a href="{{ route('blog.show', $post) }}" wire:navigate
                   class="flex items-center gap-3 rounded-2xl bg-[rgb(var(--nx-surface-2))] p-3 transition hover:bg-[rgb(var(--nx-surface-3))]">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]">
                        <x-icon name="file-text" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $post->title }}</span>
                        <span class="block truncate text-xs text-[rgb(var(--nx-text-2))]">{{ $post->excerpt }}</span>
                    </span>
                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-[rgb(var(--nx-text-2))]" />
                </a>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">{{ $search !== '' ? 'No articles match your search.' : 'No articles published yet.' }}</p>
            @endforelse
        </div>
    </div>

    {{-- Connect: the brand's real social handles (owner-configured; hidden
         entirely until at least one is set — no dead links). --}}
    @if (! empty($socials))
        <div class="rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface)/0.9)] p-2 shadow-sm backdrop-blur">
            @foreach ($socials as $s)
                <a href="{{ $s['url'] }}" target="_blank" rel="noopener"
                   class="flex items-center gap-3 rounded-2xl p-3 transition hover:bg-[rgb(var(--nx-surface-2))]">
                    <x-service-icon :slug="$s['icon']" class="h-9 w-9 rounded-full" />
                    <span class="flex-1 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $s['label'] }}</span>
                    <x-icon name="chevron-right" class="h-4 w-4 text-[rgb(var(--nx-text-2))]" />
                </a>
            @endforeach
        </div>
    @endif
</div>

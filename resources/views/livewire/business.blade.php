{{-- nx:converted (skin canvas + tokens) --}}
<div>
<x-nx.page class="px-0">
<div class="mx-auto max-w-3xl">
    <div class="mb-3">
        <h1 class="bz-title font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]" aria-label="{{ \App\Support\Business\WorkspaceHero::title() }}: your {{ implode(', ', \App\Support\Business\WorkspaceHero::words()) }}"
            x-data="{ i: 0, words: @js(\App\Support\Business\WorkspaceHero::words()), reduce: window.matchMedia('(prefers-reduced-motion: reduce)').matches }"
            x-init="if (! reduce) { setInterval(() => i = (i + 1) % words.length, 2400) }">
            <span class="bz-title__lead" aria-hidden="true">{{ \App\Support\Business\WorkspaceHero::title() }}</span>
            <span class="bz-title__rot" aria-hidden="true"><span class="bz-title__your">Your </span><span class="bz-title__win"><template x-for="(w, n) in words" :key="n"><span class="bz-title__word" :class="n === i ? 'is-on' : (n === (i + words.length - 1) % words.length ? 'is-out' : '')" x-text="w"></span></template></span></span>
        </h1>
        <p class="mt-1 text-sm leading-relaxed text-[rgb(var(--nx-text-2))] ">{{ \App\Support\Business\WorkspaceHero::description() }}</p>
        <div class="bz-hero" aria-hidden="true">
            @php
                $heroImg = \App\Support\Business\WorkspaceHero::image();
            @endphp
            <img src="{{ $heroImg['src'] }}" @if ($heroImg['srcset']) srcset="{{ $heroImg['srcset'] }}" sizes="(max-width: 768px) 100vw, 768px" @endif width="{{ $heroImg['width'] }}" height="{{ $heroImg['height'] }}" alt="" decoding="async" fetchpriority="high">
        </div>
    </div>

    @if ($switchable->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            <button wire:click="switchTo(null)" wire:loading.attr="disabled"
                    class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition disabled:opacity-60 {{ ! $active ? 'bg-[rgb(var(--nx-cta-b))] text-white shadow-sm' : 'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-text-2))] ring-1 ring-[rgb(var(--nx-line))]   ' }}">Personal</button>
            @foreach ($switchable as $ws)
                <button wire:click="switchTo({{ $ws->id }})" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-semibold transition disabled:opacity-60 {{ $active?->id === $ws->id ? 'bg-[rgb(var(--nx-cta-b))] text-white shadow-sm' : 'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-text-2))] ring-1 ring-[rgb(var(--nx-line))]   ' }}">
                    <x-icon :name="$ws->isBusiness() ? 'briefcase' : 'users'" class="h-3 w-3" /> {{ $ws->name }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($group && \App\Support\Business\WorkspaceAdminAccess::can($group, auth()->user()))
        @php
            $wz = app(\App\Support\Business\SetupWizard::class);
            $wzSteps = $wz->steps($group, auth()->user());
            $wzPct = $wz->percent($wzSteps);
        @endphp
        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" data-setup-card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-[rgb(var(--nx-text))]">{{ $wzPct >= 100 ? 'Setup complete' : 'Finish setup' }} <span class="font-normal text-[rgb(var(--nx-text-2))]">{{ $wzPct }}%</span></p>
                    <div class="mt-2 h-2 w-48 max-w-full overflow-hidden rounded-full bg-[rgb(var(--nx-surface-3))]"><div class="h-full rounded-full bg-[rgb(var(--nx-teal))]" style="width: {{ $wzPct }}%"></div></div>
                </div>
                <button type="button" onclick="Livewire.dispatch('business-setup-open')" data-tour="business.setup" class="ns-cta !w-auto px-5">{{ $wzPct >= 100 ? 'Review setup' : ($wzPct > 0 ? 'Continue setup' : 'Start setup') }}</button>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($wzSteps as $s)
                    <span class="rounded-full border border-[rgb(var(--nx-line))] px-2.5 py-1 text-[11px] font-semibold {{ match ($s['status']) { 'done' => 'text-[rgb(var(--nx-ok))]', 'attention' => 'text-[rgb(var(--nx-warn))]', default => 'text-[rgb(var(--nx-text-2))]' } }}" data-setup-status="{{ $s['key'] }}:{{ $s['status'] }}">{{ $s['title'] }}: {{ match ($s['status']) { 'done' => 'Connected', 'attention' => 'Needs attention', 'locked' => 'Locked', default => 'Not set up' } }}</span>
                @endforeach
            </div>
        </div>
        <livewire:business-setup-wizard />
    @endif

    @if (! $group)
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6  ">
            <h2 class="mb-4 text-sm font-bold text-[rgb(var(--nx-text))] " data-tour="business.create">Create your business workspace</h2>
            @if ($error)<p role="alert" class="mb-3 text-xs font-semibold text-[rgb(var(--nx-bad))]">{{ $error }}</p>@endif
            <form wire:submit="createWorkspace" class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))] ">Workspace name</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3.5 py-2.5 text-sm   ">
                    @error('name') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))] ">Business email (optional)</label>
                    <input type="email" wire:model="businessEmail" placeholder="you@yourcompany.com" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3.5 py-2.5 text-sm   ">
                    @error('businessEmail') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="createWorkspace"
                        class="inline-flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <x-icon name="briefcase" class="h-4 w-4" /> Create workspace
                </button>
            </form>
        </div>
    @else
        @if ($group->needsBusinessEmailVerification())
            <div class="mb-6 flex items-center gap-2 rounded-2xl border border-[rgb(var(--nx-gold)/0.3)] bg-[rgb(var(--nx-gold)/0.1)] px-4 py-3 text-sm text-[rgb(var(--nx-gold-ink))]   ">
                <x-icon name="mail" class="h-4 w-4 shrink-0" /> Confirm {{ $group->business_email }} — check your inbox for a verification link.
            </div>
        @endif

        @php
            $unreadNow = \App\Support\Business\BusinessNav::unread($group);
            $linesNow = \App\Models\VirtualNumber::where('wallet_group_id', $group->id)->count();
            $seatsNow = 1 + $members->whereNotNull('accepted_at')->count();
        @endphp
        <div class="bz-stats" data-tour="business.stats" role="list" aria-label="Workspace at a glance">
            <a role="listitem" href="{{ route('numbers.messages') }}" wire:navigate class="bz-stat bz-stat--lead"><span class="bz-stat__t"><b>{{ $unreadNow }}</b><span>Unread</span></span><x-nx.icon name="inbox" /></a>
            <div role="listitem" class="bz-stat"><span class="bz-stat__t"><b>{{ $linesNow }}</b><span>Lines</span></span><x-nx.icon name="phone" /></div>
            <div role="listitem" class="bz-stat"><span class="bz-stat__t"><b>{{ $seatsNow }}</b><span>Team</span></span><x-nx.icon name="users" /></div>
        </div>

        <a href="{{ route('business.call-flows') }}" wire:navigate data-tour="business.call-flows"
           class="mb-6 flex items-center justify-between gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 transition hover:border-[rgb(var(--nx-teal))]  ">
            <span class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))] "><x-icon name="phone-forwarded" class="h-4 w-4" /></span>
                <span><span class="block text-sm font-bold text-[rgb(var(--nx-text))] ">Call flows</span><span class="block text-xs text-[rgb(var(--nx-text-2))] ">Menus, ring groups and business hours for your lines</span></span></span>
            <x-icon name="chevron-right" class="h-4 w-4 text-[rgb(var(--nx-text-2))]" />
        </a>

        @if ($aiLive)
            <a href="{{ route('business.assistant') }}" wire:navigate data-tour="business.assistant"
               class="mb-6 flex items-center justify-between gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 transition hover:border-[rgb(var(--nx-teal))]">
                <span class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="mic" class="h-4 w-4" /></span>
                    <span><span class="block text-sm font-bold text-[rgb(var(--nx-text))]">AI voice assistant</span><span class="block text-xs text-[rgb(var(--nx-text-2))]">Answers calls, takes callbacks, hands over to your team</span></span></span>
                <x-icon name="chevron-right" class="h-4 w-4 text-[rgb(var(--nx-text-2))]" />
            </a>
        @endif

        @if ($inboxLive)
            <a href="{{ route('business.channels') }}" wire:navigate data-tour="business.channels"
               class="mb-6 flex items-center justify-between gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 transition hover:border-[rgb(var(--nx-teal))]">
                <span class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="message-circle" class="h-4 w-4" /></span>
                    <span><span class="block text-sm font-bold text-[rgb(var(--nx-text))]">Inbox channels</span><span class="block text-xs text-[rgb(var(--nx-text-2))]">Connect WhatsApp, Telegram and Instagram</span></span></span>
                <x-icon name="chevron-right" class="h-4 w-4 text-[rgb(var(--nx-text-2))]" />
            </a>
        @endif

        @if ($recordingLive)
            <a href="{{ route('business.recordings') }}" wire:navigate data-tour="business.recordings"
               class="mb-6 flex items-center justify-between gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 transition hover:border-[rgb(var(--nx-teal))]">
                <span class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="mic" class="h-4 w-4" /></span>
                    <span><span class="block text-sm font-bold text-[rgb(var(--nx-text))]">Call recordings</span><span class="block text-xs text-[rgb(var(--nx-text-2))]">Settings, recordings and transcripts for your lines</span></span></span>
                <x-icon name="chevron-right" class="h-4 w-4 text-[rgb(var(--nx-text-2))]" />
            </a>
        @endif

        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6  ">
            <h2 class="mb-4 flex items-center gap-1.5 text-sm font-bold text-[rgb(var(--nx-text))] ">
                <x-icon name="user-plus" class="h-4 w-4 text-[rgb(var(--nx-gold-ink))] " /> Invite a seat
            </h2>
            @if ($error)
                <p class="mb-3 text-xs font-semibold text-[rgb(var(--nx-bad))]">{{ $error }}</p>
            @endif
            <form wire:submit="inviteSeat" class="grid gap-3 sm:grid-cols-2">
                <input type="email" wire:model="inviteEmail" placeholder="teammate@email.com" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3.5 py-2.5 text-sm sm:col-span-2">
                <select wire:model="inviteRole" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3.5 py-2.5 text-sm   ">
                    @foreach ($roles as $r)
                        <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" wire:model="inviteCapUsd" placeholder="USD cap (blank = uncapped)" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3.5 py-2.5 text-sm   ">
                <button type="submit" wire:loading.attr="disabled" wire:target="inviteSeat"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60 sm:col-span-2">
                    <x-icon name="user-plus" class="h-4 w-4" /> Send invite
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6  ">
            <h2 class="mb-4 text-sm font-bold text-[rgb(var(--nx-text))] " data-tour="business.seats">Seats</h2>
            @forelse ($members as $member)
                <div class="flex items-center justify-between gap-3 border-b border-[rgb(var(--nx-line))] py-3 last:border-0 " wire:key="member-{{ $member->id }}">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-[rgb(var(--nx-text))] ">{{ $member->user->name ?? $member->invited_email }}</p>
                        <p class="text-xs text-[rgb(var(--nx-text-2))] ">
                            {{ ucfirst($member->role ?? 'member') }} ·
                            @if ($member->awaitingRegistration()) awaiting registration
                            @elseif ($member->isActive()) active
                            @else invited @endif
                        </p>
                    </div>
                    <button wire:click="removeSeat({{ $member->id }})" wire:loading.attr="disabled" wire:target="removeSeat({{ $member->id }})"
                            wire:confirm="Remove this seat?"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] transition hover:bg-[rgb(var(--nx-bad)/0.1)] hover:text-[rgb(var(--nx-bad))] disabled:opacity-60 ">
                        <x-icon name="trash" class="h-4 w-4" />
                    </button>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))] ">No seats yet — invite your first teammate above.</p>
            @endforelse
        </div>
    @endif
</div>
</x-nx.page>
</div>

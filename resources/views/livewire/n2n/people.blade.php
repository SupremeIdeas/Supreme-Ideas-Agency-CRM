{{-- nx:converted --}}
{{-- People (N2N Batch 9, MASTER-ONLY): connections, follow-backs, contacts on Naara, recent chats, add by handle, invite. Skin tokens only. --}}
<div class="mx-auto w-full max-w-2xl px-3 py-3">
    @if ($message)<p class="mb-2 rounded-xl bg-[rgb(var(--nx-ok)/0.12)] px-3 py-2 text-sm text-[rgb(var(--nx-ok))]" role="status">{{ $message }}</p>@endif
    @if ($error)<p class="mb-2 rounded-xl bg-[rgb(var(--nx-bad)/0.1)] px-3 py-2 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif

    <div class="mb-3 flex gap-1 rounded-full border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-1" role="tablist" aria-label="People or brands" data-people-tabs data-tour="people.tabs">
        @foreach (['people' => 'People', 'brands' => 'Brands'] as $k => $l)
            <button type="button" role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}" wire:click="setTab('{{ $k }}')" data-people-tab="{{ $k }}" data-tour="people.tab-{{ $k }}"
                    @class(['flex-1 rounded-full px-4 py-1.5 text-sm font-semibold transition', 'ns-cta' => $tab === $k, 'text-[rgb(var(--nx-text-2))]' => $tab !== $k])>{{ $l }}</button>
        @endforeach
    </div>

    @if ($tab === 'brands')
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3" data-brands-panel>
            <label class="block text-xs font-semibold text-[rgb(var(--nx-text-2))]" for="pp-bq">Find a brand to connect with</label>
            <input id="pp-bq" data-tour="people.brand-search" type="search" wire:model.live.debounce.400ms="brandQ" autocomplete="off" placeholder="Search brands, music, food, travel..."
                   class="mt-1 w-full rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-4 py-2 text-sm text-[rgb(var(--nx-text))]">
            <div class="mt-3 flex flex-wrap gap-1.5" role="tablist" aria-label="Brand views">
                @foreach (['discover' => 'Discover', 'following' => 'Connected', 'rewards' => 'Earn credits'] as $k => $l)
                    <button type="button" role="tab" aria-selected="{{ $brandTab === $k ? 'true' : 'false' }}" wire:click="setBrandTab('{{ $k }}')" data-brand-tab="{{ $k }}"
                            @class(['rounded-full border px-3 py-1 text-xs font-semibold', 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.18)] text-[rgb(var(--nx-text))]' => $brandTab === $k, 'border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]' => $brandTab !== $k])>{{ $l }}</button>
                @endforeach
            </div>
            @if (count($brandCategories) > 1)
                <select wire:model.live="category" aria-label="Category" class="mt-3 w-full rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-3 py-1.5 text-xs text-[rgb(var(--nx-text))]">
                    <option value="">All categories</option>
                    @foreach ($brandCategories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
            @endif
        </div>

        @if (count($quickBrands) > 0)
            <section class="mt-3" aria-label="Quick connect" data-quick-connect data-tour="people.quick-connect">
                <h2 class="mb-1.5 px-1 text-xs font-bold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Quick connect</h2>
                <ul class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width:none">
                    @foreach ($quickBrands as $qb)
                        <li wire:key="quick-{{ $qb['id'] }}" class="w-32 shrink-0 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-2.5 text-center">
                            @if ($qb['logo'])<img src="{{ $qb['logo'] }}" alt="" width="48" height="48" loading="lazy" class="mx-auto h-12 w-12 rounded-full object-cover">@else<span class="mx-auto block w-fit"><x-nx.avatar :name="$qb['name']" :size="48" /></span>@endif
                            <b class="mt-1.5 block truncate text-xs text-[rgb(var(--nx-text))]">{{ $qb['name'] }}</b>
                            <small class="block truncate text-[10px] text-[rgb(var(--nx-text-2))]">{{ $qb['category'] ?: 'Brand' }}</small>
                            <button type="button" wire:click="toggleBrand({{ $qb['id'] }})" wire:loading.attr="disabled" wire:target="toggleBrand,connectAndMessage" class="ns-cta ns-cta--pill ns-cta--sm mt-2 w-full justify-center">Connect</button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <ul class="mt-3 overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]" aria-label="Brands" data-tour="people.brand-list">
            @forelse ($brandCards as $b)
                <li wire:key="brand-{{ $b['id'] }}" class="border-b border-[rgb(var(--nx-line)/0.6)] px-3 py-3 last:border-0" data-brand-card="{{ $b['id'] }}">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('rewards.hunt.profile', $b['id']) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                            @if ($b['logo'])<img src="{{ $b['logo'] }}" alt="" width="44" height="44" loading="lazy" class="h-11 w-11 rounded-full object-cover">@else<x-nx.avatar :name="$b['name']" :size="44" />@endif
                            <span class="min-w-0">
                                <b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $b['name'] }}</b>
                                <small class="block truncate text-[rgb(var(--nx-text-2))]">{{ $b['category'] ?: 'Brand' }} · {{ number_format($b['followers']) }} {{ \Illuminate\Support\Str::plural('follower', $b['followers']) }}</small>
                            </span>
                        </a>
                        @if ($b['following'])
                            <button type="button" wire:click="toggleBrand({{ $b['id'] }})" wire:loading.attr="disabled" wire:target="toggleBrand" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text))]">Connected</button>
                        @else
                            <button type="button" wire:click="toggleBrand({{ $b['id'] }})" wire:loading.attr="disabled" wire:target="toggleBrand,connectAndMessage" class="ns-cta ns-cta--pill ns-cta--sm">Connect</button>
                        @endif
                    </div>
                    @if ($b['about'])<p class="mt-1.5 line-clamp-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $b['about'] }}</p>@endif
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        @if ($b['following'] && $b['pages_left'] > 0)
                            <a href="{{ route('rewards.hunt.profile', $b['id']) }}" wire:navigate class="rounded-full bg-[rgb(var(--nx-ok)/0.12)] px-2.5 py-1 font-semibold text-[rgb(var(--nx-ok))]">Follow {{ $b['pages_left'] }} {{ \Illuminate\Support\Str::plural('page', $b['pages_left']) }}@if ($b['credits_left'] > 0) · earn {{ rtrim(rtrim(number_format($b['credits_left'], 2), '0'), '.') }} credits @endif</a>
                        @elseif ($b['following'])
                            <span class="rounded-full bg-[rgb(var(--nx-surface-2))] px-2.5 py-1 text-[rgb(var(--nx-text-2))]">You follow all their pages</span>
                        @elseif ($b['pages_left'] > 0)
                            <span class="rounded-full bg-[rgb(var(--nx-surface-2))] px-2.5 py-1 text-[rgb(var(--nx-text-2))]">{{ $b['pages_left'] }} {{ \Illuminate\Support\Str::plural('page', $b['pages_left']) }} to follow after you connect</span>
                        @endif
                        @if ($b['can_message'] && $b['naara_id'] && ! $b['following'])
                            <button type="button" wire:click="connectAndMessage({{ $b['id'] }})" wire:loading.attr="disabled" wire:target="toggleBrand,connectAndMessage" data-connect-message class="rounded-full border border-[rgb(var(--nx-teal))] px-2.5 py-1 font-semibold text-[rgb(var(--nx-teal-ink))]">Connect and message</button>
                        @elseif ($b['can_message'] && $b['naara_id'])
                            <a href="{{ route('n2n.chat', ['to' => $b['naara_id']]) }}" wire:navigate class="rounded-full border border-[rgb(var(--nx-line-strong))] px-2.5 py-1 font-semibold text-[rgb(var(--nx-text))]">Message</a>
                        @elseif ($b['chat_closed'])
                            <span class="text-[rgb(var(--nx-text-2))]">Chat opens once you are connected with each other</span>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">
                    @if ($brandTab === 'following') You have not connected with a brand yet. Try Discover.
                    @elseif ($brandTab === 'rewards') No pages left to follow. You have followed everything from the brands you are connected with.
                    @else No brands match. @endif
                </li>
            @endforelse
        </ul>
    @else
    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
        <label class="block text-xs font-semibold text-[rgb(var(--nx-text-2))]" for="pp-q">Add by username, Naara ID, phone or email</label>
        <input id="pp-q" data-tour="people.add" type="text" wire:model.live.debounce.500ms="q" autocomplete="off" placeholder="@name, NR-..., +234... or name@mail.com"
               class="mt-1 w-full rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-4 py-2 text-sm text-[rgb(var(--nx-text))]">
        @if ($hit)
            <div class="mt-3 flex items-center gap-3">
                <x-nx.avatar :name="$hit['name']" :size="40" />
                <div class="min-w-0 flex-1"><b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $hit['name'] }}</b><small class="text-[rgb(var(--nx-text-2))]">@if ($hit['username']){{ '@'.$hit['username'] }}@endif</small></div>
                <a href="{{ route('n2n.chat', ['to' => $hit['naara_id']]) }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--sm">Chat</a>
                <button type="button" wire:click="follow({{ $hit['id'] }})" wire:loading.attr="disabled" wire:target="follow" class="ns-cta ns-cta--pill ns-cta--sm">Follow</button>
            </div>
        @elseif ($miss)
            <div class="mt-3 text-sm text-[rgb(var(--nx-text))]">
                <p>Not on Naara yet. Invite them:</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <input type="number" min="0" step="0.5" wire:model="inviteCredits" placeholder="Gift credits (optional)" aria-label="Credits to gift" class="w-44 rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                    <button type="button" wire:click="invite" wire:loading.attr="disabled" wire:target="invite" class="ns-cta ns-cta--pill ns-cta--sm">Send invite</button>
                </div>
                <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">A credit gift is held for them for 24 hours and returned to you if they do not join.</p>
            </div>
        @endif
        <div x-data="{ done: false }" class="mt-3 flex items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
            <span>Or share your invite link:</span>
            <button type="button" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1 font-semibold text-[rgb(var(--nx-text))]"
                    x-on:click="navigator.share ? navigator.share({title: 'Join me on Naara', url: @js($inviteLink)}).catch(() => {}) : navigator.clipboard.writeText(@js($inviteLink)).then(() => { done = true; setTimeout(() => done = false, 1600) })">
                <span x-text="done ? 'Link copied' : 'Share'">Share</span>
            </button>
        </div>
    </div>

    @foreach ([['Follow requests', $requests, 'Follow back to connect'], ['Connections', $connections, null], ['Contacts on Naara', $contacts, null], ['Recent chats', $recent, null]] as [$title, $list, $hint])
        @if (count($list) > 0)
            <section class="mt-4" aria-label="{{ $title }}">
                <h2 class="px-1 text-xs font-bold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $title }} ({{ count($list) }})</h2>
                <ul class="mt-1 overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                    @foreach ($list as $p)
                        <li wire:key="{{ \Illuminate\Support\Str::slug($title) }}-{{ $p['id'] }}" class="flex items-center gap-3 border-b border-[rgb(var(--nx-line)/0.6)] px-3 py-2 last:border-0">
                            <a @if ($p['handle']) href="{{ route('n2n.profile', ['handle' => $p['handle']]) }}" wire:navigate @endif class="flex min-w-0 flex-1 items-center gap-3">
                                <x-nx.avatar :name="$p['name']" :size="40" />
                                <span class="min-w-0"><b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $p['name'] }}</b><small class="text-[rgb(var(--nx-text-2))]">@if ($p['username']){{ '@'.$p['username'] }}@endif @if ($p['connected']) · connected @endif</small></span>
                            </a>
                            @if ($p['naara_id'])<a href="{{ route('n2n.chat', ['to' => $p['naara_id']]) }}" wire:navigate class="nn__iconbtn" aria-label="Message {{ $p['name'] }}"><x-nx.icon name="message-circle" /></a>@endif
                            @if ($p['following'])
                                <button type="button" wire:click="unfollow({{ $p['id'] }})" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text))]">Following</button>
                            @else
                                <button type="button" wire:click="follow({{ $p['id'] }})" wire:loading.attr="disabled" wire:target="follow" class="ns-cta ns-cta--pill ns-cta--sm">{{ $title === 'Follow requests' ? 'Follow back' : 'Follow' }}</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    @if (! count($requests) && ! count($connections) && ! count($contacts) && ! count($recent))
        <x-n2n.empty art="nc-people" title="No one here yet" text="Add someone above, or import your contacts in Naara ID settings." class="mt-6" />
    @endif
    @endif
</div>

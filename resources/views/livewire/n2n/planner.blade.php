{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- Naara Planner (N2N, MASTER-ONLY): upcoming events across chats + private follow-ups. Skin tokens only; times are shown in the viewer's own timezone. --}}
<div class="mx-auto w-full max-w-2xl px-3 py-3" data-n2n-planner>
    <x-n2n.section-tabs />
    <div class="nc__chips" role="tablist" aria-label="Planner">
        <button type="button" role="tab" wire:click="setTab('events')" aria-selected="{{ $tab === 'events' ? 'true' : 'false' }}" class="nc__chip {{ $tab === 'events' ? 'is-on' : '' }}">Upcoming events</button>
        <button type="button" role="tab" wire:click="setTab('followups')" aria-selected="{{ $tab === 'followups' ? 'true' : 'false' }}" class="nc__chip {{ $tab === 'followups' ? 'is-on' : '' }}">Follow-ups @if ($followups->whereNull('done_at')->count())<span>({{ $followups->whereNull('done_at')->count() }})</span>@endif</button>
    </div>

    @if ($error)<p role="alert" class="mt-2 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-bad))]">{{ $error }}</p>@endif
    @if ($notice)<p role="status" class="mt-2 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">{{ $notice }}</p>@endif

    @if ($tab === 'events')
        @php($lab = ['going' => 'Going', 'maybe' => 'Maybe', 'declined' => 'Can\'t go', 'no_response' => 'Not responded'])
        <ul class="mt-2 overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
            @forelse ($events as $row)
                @php($ev = $row['event'])
                <li wire:key="pe-{{ $ev->id }}" class="flex items-center gap-3 border-b border-[rgb(var(--nx-line)/0.6)] px-3 py-3 last:border-0">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text))]"><x-nx.icon name="calendar" /></span>
                    <a href="{{ route('n2n.chat', ['c' => $ev->conversation_id]) }}" wire:navigate class="min-w-0 flex-1">
                        <b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $ev->title }}</b>
                        <small class="block text-[rgb(var(--nx-text-2))]"><time datetime="{{ $ev->starts_at->toIso8601String() }}" x-data x-init="$el.textContent = new Date($el.dateTime).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })">{{ $ev->starts_at->copy()->setTimezone($ev->timezone)->format('D j M, H:i') }}</time> · You: {{ $lab[$row['mine']] ?? 'Not responded' }}</small>
                    </a>
                    <a href="{{ route('n2n.event.ics', $ev->id) }}" class="nn__iconbtn" aria-label="Add {{ $ev->title }} to my calendar" download><x-nx.icon name="download" /></a>
                </li>
            @empty
                <li><x-n2n.empty art="nc-planner" title="No upcoming events" text="Open a chat, tap the menu and choose Plan an event." /></li>
            @endforelse
        </ul>
    @else
        <form class="mt-2 grid gap-2 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3"
            x-data="{ custom: '', iso() { return this.custom ? new Date(this.custom).toISOString() : ''; } }" x-on:submit.prevent="$wire.add(iso())">
            <label class="grid gap-1"><span class="text-xs text-[rgb(var(--nx-text-2))]">What do you want to follow up on? Only you can see this.</span>
                <input type="text" wire:model="title" maxlength="120" required placeholder="e.g. Call John about the invoice" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-[rgb(var(--nx-text))]"></label>
            <label class="grid gap-1"><span class="text-xs text-[rgb(var(--nx-text-2))]">Remind me at (optional)</span>
                <input type="datetime-local" x-model="custom" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-[rgb(var(--nx-text))]"></label>
            <button type="submit" class="ns-cta ns-cta--pill ns-cta--sm justify-self-start" wire:loading.attr="disabled" wire:target="add"><span wire:loading.remove wire:target="add">Add follow-up</span><span wire:loading wire:target="add">Saving...</span></button>
        </form>
        <ul class="mt-3 overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
            @forelse ($followups as $f)
                <li wire:key="fu-{{ $f->id }}" class="flex items-center gap-2 border-b border-[rgb(var(--nx-line)/0.6)] px-3 py-2 last:border-0">
                    <button type="button" wire:click="toggleDone({{ $f->id }})" class="nn__iconbtn" aria-label="{{ $f->done_at ? 'Mark as not done' : 'Mark as done' }}" aria-pressed="{{ $f->done_at ? 'true' : 'false' }}"><x-nx.icon name="{{ $f->done_at ? 'check-circle' : 'circle' }}" /></button>
                    <div class="min-w-0 flex-1">
                        <b class="block truncate text-sm {{ $f->done_at ? 'line-through opacity-70' : '' }} text-[rgb(var(--nx-text))]">{{ $f->title }}</b>
                        <small class="block text-[rgb(var(--nx-text-2))]">
                            @if ($f->about){{ $f->about->name }} · @endif
                            @if ($f->due_at)<time datetime="{{ $f->due_at->toIso8601String() }}" x-data x-init="$el.textContent = new Date($el.dateTime).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })">{{ $f->due_at->format('D j M, H:i') }} UTC</time>@else No reminder time @endif
                            @if ($f->conversation_id && isset($mine[$f->conversation_id])) · <a href="{{ route('n2n.chat', ['c' => $f->conversation_id]) }}" wire:navigate class="underline">Open chat</a>@endif
                        </small>
                    </div>
                    <button type="button" wire:click="toggleStar({{ $f->id }})" class="nn__iconbtn" aria-label="{{ $f->starred ? 'Unstar' : 'Star' }}" aria-pressed="{{ $f->starred ? 'true' : 'false' }}"><x-nx.icon name="star" /></button>
                    <button type="button" wire:click="remove({{ $f->id }})" wire:confirm="Delete this follow-up?" class="nn__iconbtn" aria-label="Delete follow-up"><x-nx.icon name="trash" /></button>
                </li>
            @empty
                <li><x-n2n.empty art="nc-followups" title="No follow-ups yet" text="Add one above, or open a chat menu and choose Remind me to follow up." /></li>
            @endforelse
        </ul>
    @endif
</div>

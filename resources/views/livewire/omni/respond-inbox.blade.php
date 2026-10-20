{{-- nx:converted (skin canvas + tokens) --}}
<div wire:poll.5s>
<x-nx.page class="px-0">
<div class="mx-auto max-w-6xl pb-28" data-respond-inbox>
    <div class="mb-4 flex items-center gap-3 px-1">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="message-circle" class="h-5 w-5" /></span>
        <div>
            <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]">Respond</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">One inbox for every channel your team answers.</p>
        </div>
        @if (\App\Support\Business\WorkspaceAdminAccess::groupFor(auth()->user()))<a href="{{ route('business.respond.templates') }}" wire:navigate class="ml-auto rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">Templates</a><a href="{{ route('business.respond.automation') }}" wire:navigate data-tour="respond.automation" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">Automation</a>@endif
    </div>

    <div class="grid gap-4 md:grid-cols-[320px_1fr]">
        {{-- Conversation list (hidden on a phone while a thread is open) --}}
        <section class="{{ $selected ? 'hidden md:block' : '' }} rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]" aria-label="Conversations">
            <div class="border-b border-[rgb(var(--nx-line))] p-3">
                <input type="search" wire:model.live.debounce.400ms="q" placeholder="Search name, email, phone or message" aria-label="Search conversations"
                       class="w-full rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-4 py-2 text-sm text-[rgb(var(--nx-text))]">
                <div class="mt-2 flex flex-wrap gap-1.5" role="tablist" aria-label="Status" data-tour="respond.filters">
                    @foreach (['open' => 'Open', 'pending' => 'Waiting', 'resolved' => 'Done', 'all' => 'All'] as $k => $l)
                        <button type="button" role="tab" aria-selected="{{ $filter === $k ? 'true' : 'false' }}" wire:click="setFilter('{{ $k }}')" data-filter="{{ $k }}"
                                @class(['rounded-full border px-3 py-1 text-xs font-semibold', 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.18)] text-[rgb(var(--nx-text))]' => $filter === $k, 'border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]' => $filter !== $k])>
                            {{ $l }}@if ($k !== 'all' && ($counts[$k] ?? 0) > 0) <span class="opacity-70">{{ $counts[$k] }}</span>@endif
                        </button>
                    @endforeach
                </div>
                <div class="mt-1.5 flex flex-wrap gap-1.5" role="group" aria-label="Whose">
                    @foreach (['all' => 'Everyone', 'mine' => 'Mine', 'unassigned' => 'Unassigned'] as $k => $l)
                        <button type="button" wire:click="setScope('{{ $k }}')" data-scope="{{ $k }}" aria-pressed="{{ $scope === $k ? 'true' : 'false' }}"
                                @class(['rounded-full border px-3 py-1 text-xs font-semibold', 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.18)] text-[rgb(var(--nx-text))]' => $scope === $k, 'border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]' => $scope !== $k])>{{ $l }}</button>
                    @endforeach
                </div>
                @if (count($picked) > 0)
                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs" role="group" aria-label="Bulk actions">
                        <b class="text-[rgb(var(--nx-text))]">{{ count($picked) }} selected</b>
                        <button type="button" wire:click="bulkSetStatus('resolved')" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-2.5 py-1 font-semibold text-[rgb(var(--nx-text))]">Mark done</button>
                        <button type="button" wire:click="bulkSetStatus('pending')" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-2.5 py-1 font-semibold text-[rgb(var(--nx-text))]">Waiting</button>
                        <button type="button" wire:click="bulkAssignToMe" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-2.5 py-1 font-semibold text-[rgb(var(--nx-text))]">Assign to me</button>
                    </div>
                @endif
            </div>
            <ul class="max-h-[70vh] overflow-y-auto" data-tour="respond.list">
                @forelse ($conversations as $c)
                    <li wire:key="conv-{{ $c->id }}">
                        <div class="flex items-start border-b border-[rgb(var(--nx-line)/0.6)]">
                        <input type="checkbox" wire:model.live="picked" value="{{ $c->id }}" aria-label="Select conversation with {{ $c->contact?->name ?: 'Visitor' }}" class="ml-3 mt-4 h-4 w-4 shrink-0">
                        <button type="button" wire:click="select({{ $c->id }})" data-conversation="{{ $c->id }}"
                                @class(['flex w-full items-start gap-3 px-3 py-3 text-left', 'bg-[rgb(var(--nx-surface-2))]' => $selected === $c->id])>
                            <x-nx.avatar :name="$c->contact?->name ?: 'Visitor'" :size="40" />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <b class="truncate text-sm text-[rgb(var(--nx-text))]">{{ $c->contact?->name ?: ($c->contact?->email ?: 'Visitor') }}</b>
                                    <small class="shrink-0 text-[rgb(var(--nx-text-2))]">{{ $c->last_message_at?->diffForHumans(null, true, true) }}</small>
                                </span>
                                <span class="flex items-center justify-between gap-2">
                                    <small class="truncate text-[rgb(var(--nx-text-2))]">{{ $channelName($c->channel) }} · {{ $c->last_preview ?: 'No messages' }}</small>
                                    @if ($c->unread > 0 && $c->status === 'open' && $c->last_inbound_at && $c->last_inbound_at->lt($overdueBefore))<span class="shrink-0 rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-1.5 text-[10px] font-bold leading-4 text-[rgb(var(--nx-warn))]" data-overdue>Overdue</span>@endif
                                    @if ($c->unread > 0)<span class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b))] px-1.5 text-[10px] font-bold leading-4 text-white" aria-label="{{ $c->unread }} unread">{{ $c->unread > 9 ? '9+' : $c->unread }}</span>@endif
                                </span>
                            </span>
                        </button>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-sm text-[rgb(var(--nx-text-2))]">No conversations here yet. When a customer writes on a connected channel, it appears at once.</li>
                @endforelse
            </ul>
        </section>

        {{-- Thread --}}
        <section class="{{ $selected ? '' : 'hidden md:flex' }} min-h-[60vh] flex-col rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]" aria-label="Conversation">
            @if ($thread)
                <header class="flex flex-wrap items-center gap-2 border-b border-[rgb(var(--nx-line))] px-3 py-2.5">
                    <button type="button" wire:click="back" class="nn__iconbtn md:hidden" aria-label="Back to conversations"><x-nx.icon name="chevron-left" /></button>
                    <div class="min-w-0 flex-1">
                        <b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $thread->contact?->name ?: 'Visitor' }}</b>
                        <small class="text-[rgb(var(--nx-text-2))]">{{ $channelName($thread->channel) }}@if ($thread->contact?->email) · {{ $thread->contact->email }}@endif @if ($thread->contact?->phone) · {{ $thread->contact->phone }}@endif</small>
                    </div>
                    <button type="button" wire:click="toggleOptIn" data-optin data-tour="respond.consent" aria-pressed="{{ $thread->contact?->marketing_opt_in && ! $thread->contact?->opted_out_at ? 'true' : 'false' }}" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text))]" title="Record whether this customer agreed to receive broadcasts">{{ $thread->contact?->marketing_opt_in && ! $thread->contact?->opted_out_at ? 'Accepts broadcasts' : 'No broadcasts' }}</button>
                    @if ($thread->assignee_id !== auth()->id())<button type="button" wire:click="assignToMe" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text))]">Assign to me</button>@else<span class="text-xs text-[rgb(var(--nx-text-2))]">Assigned to you</span>@endif
                    @if ($thread->status === 'resolved')
                        <button type="button" wire:click="setStatus('open')" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text))]">Reopen</button>
                    @else
                        <button type="button" wire:click="setStatus('resolved')" data-resolve class="ns-cta ns-cta--pill ns-cta--sm">Mark done</button>
                    @endif
                </header>

                <div class="flex-1 space-y-2 overflow-y-auto p-3" role="log" aria-live="polite" style="max-height:55vh">
                    @foreach ($messages as $m)
                        <div wire:key="msg-{{ $m->id }}" @class(['flex', 'justify-end' => $m->direction === 'out'])>
                            <div @class(['max-w-[80%] rounded-2xl px-3 py-2 text-sm', 'bg-[rgb(var(--nx-cta-b))] text-white' => $m->direction === 'out' && $m->status !== 'failed', 'bg-[rgb(var(--nx-bad)/0.12)] text-[rgb(var(--nx-bad))]' => $m->status === 'failed', 'bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text))]' => $m->direction === 'in'])>
                                @if ($m->body)<p class="whitespace-pre-wrap break-words">{{ $m->body }}</p>@endif
                                @foreach ((array) $m->media as $a)<p class="mt-1 text-xs opacity-80">[{{ $a['type'] ?? 'file' }}{{ ! empty($a['name']) ? ': '.$a['name'] : '' }}]</p>@endforeach
                                <small class="mt-0.5 block text-[10px] opacity-70">{{ ($m->sent_at ?? $m->created_at)?->format('H:i') }}@if ($m->direction === 'out') · {{ $m->status === 'failed' ? 'Not sent: '.$m->error : $m->status }}@endif</small>
                            </div>
                        </div>
                    @endforeach
                    @foreach ($notes as $n)
                        <div wire:key="note-{{ $n->id }}" class="mx-auto max-w-[90%] rounded-xl border border-dashed border-[rgb(var(--nx-warn)/0.5)] bg-[rgb(var(--nx-warn)/0.08)] px-3 py-2 text-xs text-[rgb(var(--nx-text))]">
                            <b class="text-[rgb(var(--nx-warn))]">Private note</b> <span class="opacity-70">· {{ $n->created_at?->format('H:i') }}</span>
                            <p class="whitespace-pre-wrap break-words">{{ $n->body }}</p>
                        </div>
                    @endforeach
                </div>

                <footer class="border-t border-[rgb(var(--nx-line))] p-3">
                    @php($needsWindow = in_array($thread->channel, ['whatsapp', 'instagram', 'messenger'], true))
                    @if ($needsWindow && ! $thread->windowOpen())
                        <p class="mb-2 rounded-xl bg-[rgb(var(--nx-warn)/0.12)] px-3 py-2 text-xs text-[rgb(var(--nx-warn))]" role="status">The customer has not written in the last 24 hours, so only an approved template can start the conversation again.</p>
                    @endif
                    @if ($needsWindow && ! $thread->windowOpen() && $thread->channel === 'whatsapp')
                        <div class="mb-2 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3 text-xs" data-template-send>
                            @if (count($templates) === 0)
                                <p class="text-[rgb(var(--nx-text-2))]">You have no approved template for this number yet. <a class="font-semibold underline" href="{{ route('business.respond.templates') }}" wire:navigate>Write one in Templates</a>.</p>
                            @else
                                <label for="tpl-pick" class="mb-1 block font-semibold text-[rgb(var(--nx-text))]">Send an approved template</label>
                                <select id="tpl-pick" wire:model.live="tplKey" class="w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                    <option value="">Choose a template</option>
                                    @foreach ($templates as $t)<option value="{{ $t['name'] }}|{{ $t['language'] }}">{{ $t['name'] }} ({{ $t['language'] }})</option>@endforeach
                                </select>
                                @foreach ($templates as $t)
                                    @if ($tplKey === $t['name'].'|'.$t['language'])
                                        <p class="mt-2 whitespace-pre-wrap text-[rgb(var(--nx-text-2))]">{{ $t['body'] }}</p>
                                        @for ($i = 0; $i < $t['vars']; $i++)
                                            <input type="text" wire:model="tplParams.{{ $i }}" maxlength="200" placeholder="Value for variable {{ $i + 1 }}" aria-label="Value for variable {{ $i + 1 }}" class="mt-1.5 w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                        @endfor
                                        <button type="button" wire:click="sendTemplate" wire:loading.attr="disabled" wire:target="sendTemplate" class="ns-cta ns-cta--pill ns-cta--sm mt-2"><span wire:loading.remove wire:target="sendTemplate">Send template</span><span wire:loading wire:target="sendTemplate">Sending...</span></button>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    @endif
                    @if ($error)<p class="mb-2 rounded-xl bg-[rgb(var(--nx-bad)/0.1)] px-3 py-2 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif
                    @if ($snippets->isNotEmpty())
                        <div class="mb-2 flex flex-wrap gap-1.5" aria-label="Saved replies">
                            @foreach ($snippets as $sn)
                                <span wire:key="snip-{{ $sn->id }}" class="inline-flex items-center rounded-full border border-[rgb(var(--nx-line-strong))] text-xs text-[rgb(var(--nx-text))]">
                                    <button type="button" wire:click="useSnippet({{ $sn->id }})" class="px-2.5 py-1 font-semibold" title="{{ $sn->body }}">{{ $sn->title }}</button>
                                    <button type="button" wire:click="deleteSnippet({{ $sn->id }})" wire:confirm="Delete this saved reply?" class="pr-2 text-[rgb(var(--nx-text-2))]" aria-label="Delete saved reply {{ $sn->title }}">&times;</button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                    @if ($suggestion)
                        <div class="mb-2 rounded-xl bg-[rgb(var(--nx-teal)/0.08)] px-3 py-2 text-xs text-[rgb(var(--nx-text))]" data-ai-suggestion>
                            <b>AI suggests</b>
                            <p class="mt-0.5 whitespace-pre-wrap text-[rgb(var(--nx-text-2))]">{{ $suggestion->reply }}</p>
                            @if ($titles = $sourceTitles($suggestion->sources))<p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]" data-ai-sources>Based on your documents: {{ implode(', ', $titles) }}</p>@endif
                            <button type="button" wire:click="useSuggestion({{ $suggestion->id }})" class="mt-1.5 rounded-full border border-[rgb(var(--nx-line-strong))] px-2.5 py-1 font-semibold">Use this reply</button>
                        </div>
                    @endif
                    <form wire:submit="send" class="flex gap-2">
                        <textarea wire:model="draft" rows="1" maxlength="2000" placeholder="Write a reply" aria-label="Reply" data-reply data-tour="respond.composer"
                                  x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"
                                  class="min-h-[44px] flex-1 resize-none rounded-2xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-4 py-2.5 text-sm text-[rgb(var(--nx-text))]"></textarea>
                        <button type="submit" wire:loading.attr="disabled" wire:target="send" class="ns-cta ns-cta--pill">
                            <span wire:loading.remove wire:target="send">Send</span><span wire:loading wire:target="send">Sending...</span>
                        </button>
                    </form>
                    <details class="mt-2 text-xs text-[rgb(var(--nx-text-2))]" data-tour="respond.notes">
                        <summary class="cursor-pointer font-semibold">Private note or new saved reply</summary>
                        <form wire:submit="addNote" class="mt-2 flex gap-2">
                            <input type="text" wire:model="note" maxlength="2000" placeholder="Note for your team (the customer never sees it)" aria-label="Private note" class="min-w-0 flex-1 rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                            <button type="submit" wire:loading.attr="disabled" wire:target="addNote" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">Add note</button>
                        </form>
                        <form wire:submit="saveSnippet" class="mt-2 grid gap-2 sm:grid-cols-[10rem_1fr_auto]">
                            <input type="text" wire:model="snipTitle" maxlength="80" placeholder="Short name" aria-label="Saved reply name" class="rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                            <input type="text" wire:model="snipBody" maxlength="2000" placeholder="The reply text" aria-label="Saved reply text" class="rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveSnippet" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">Save reply</button>
                        </form>
                    </details>
                </footer>
            @else
                <div class="m-auto max-w-xs p-8 text-center text-sm text-[rgb(var(--nx-text-2))]">Pick a conversation to read it and reply.</div>
            @endif
        </section>
    </div>
</div>
</x-nx.page>
</div>

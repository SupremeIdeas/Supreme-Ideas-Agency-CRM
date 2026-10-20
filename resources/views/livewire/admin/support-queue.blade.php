{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-5xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Support tickets</h1>
    <p class="mb-4 text-sm text-[rgb(var(--nx-text-2))]">Conversations the AI handed to a human. Assign one to yourself and reply.</p>

    @if ($flash)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $flash }}
        </div>
    @endif

    {{-- Parallel online-staff list (owner request): who's around right now,
         so a busy ticket can be handed to someone free instead of queuing. --}}
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
        <span class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Online now</span>
        @forelse ($onlineAgents as $agent)
            <span class="flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-surface-2))] py-1 pl-1 pr-2.5 text-xs font-medium text-[rgb(var(--nx-text-2))]">
                <x-avatar :src="$agent->avatar" :name="$agent->name" size="h-5 w-5" :online="true" /> {{ $agent->name }}
            </span>
        @empty
            <span class="text-xs text-[rgb(var(--nx-text-2))]">Just you right now.</span>
        @endforelse
    </div>

    <div class="grid gap-4 lg:grid-cols-[300px_1fr]">
        {{-- Queue --}}
        <div class="space-y-2">
            @forelse ($tickets as $t)
                <button wire:click="select({{ $t->id }})"
                        class="w-full rounded-xl border p-3 text-left transition{{ $selected && $selected->id === $t->id
                            ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-cta-b)/0.05)]'
                            : 'border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] hover:bg-[rgb(var(--nx-surface-2))]' }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $t->user->name }}</span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium {{ $t->status === 'assigned' ? 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">
                            {{ $t->status }}
                        </span>
                    </div>
                    <p class="mt-1 truncate text-xs text-[rgb(var(--nx-text-2))]">{{ $t->escalation_reason ?: 'Escalated to a human' }}</p>
                </button>
            @empty
                <div class="rounded-xl border border-dashed border-[rgb(var(--nx-line))] p-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No open tickets. Nice.</div>
            @endforelse
        </div>

        {{-- Thread --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            @if (! $selected)
                <div class="flex h-64 items-center justify-center text-sm text-[rgb(var(--nx-text-2))]">Select a ticket to view the conversation.</div>
            @else
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-[rgb(var(--nx-line))] pb-3">
                    <div class="flex items-center gap-2">
                        @if ($selected->assignee)
                            <x-avatar :src="$selected->assignee->avatar" :name="$selected->assignee->name" size="h-8 w-8" />
                        @endif
                        <div>
                            <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $selected->user->name }} — {{ $selected->user->email }}</p>
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">
                                {{ $selected->assignee ? 'Owned by '.$selected->assignee->name : 'Unassigned — anyone online can take it' }} · {{ $selected->status }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2" x-data="{ transferTo: '' }">
                        @if (is_null($selected->assigned_to))
                            <button wire:click="assignToMe({{ $selected->id }})" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Assign to me</button>
                        @elseif ($onlineAgents->isNotEmpty())
                            {{-- Hand it to someone else — the "pass it on for higher
                                 attention" half of the single-owner rule. --}}
                            <select x-model="transferTo" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-xs">
                                <option value="">Transfer to…</option>
                                @foreach ($onlineAgents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" x-show="transferTo !== ''" x-cloak
                                    @click="$wire.transfer({{ $selected->id }}, parseInt(transferTo)); transferTo = ''"
                                    class="rounded-lg border border-[rgb(var(--nx-teal)/0.3)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.05)]">Hand off</button>
                        @endif
                        <button wire:click="resolve({{ $selected->id }})" wire:confirm="Mark this ticket resolved?" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Resolve</button>
                    </div>
                </div>

                @if (! empty($autopilotLog))
                    <div class="mb-3 rounded-lg border border-[rgb(var(--nx-gold)/0.3)] bg-[rgb(var(--nx-gold)/0.05)] p-3">
                        <p class="mb-1 flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-gold-ink))]">
                            <x-icon name="shield-check" class="h-3.5 w-3.5" /> What the AI already did
                        </p>
                        <ul class="space-y-0.5 text-xs text-[rgb(var(--nx-text-2))]">
                            @foreach ($autopilotLog as $entry)
                                <li class="flex items-start gap-1">
                                    <x-icon name="check" class="mt-0.5 h-3 w-3 shrink-0 text-[rgb(var(--nx-gold-ink))]" />
                                    <span>{{ str_replace('_', ' ', $entry['action'] ?? 'action') }}@if(isset($entry['usd'])) — ${{ number_format((float) $entry['usd'], 2) }} goodwill @endif@if(isset($entry['summary'])): {{ $entry['summary'] }}@endif</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="max-h-[50vh] space-y-3 overflow-y-auto pr-1">
                    @foreach ($thread as $m)
                        <div class="flex {{ $m['role'] === 'user' ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[80%]">
                                <p class="mb-0.5 text-[10px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                                    {{ $m['role'] === 'user' ? 'Customer' : ($m['role'] === 'staff' ? 'You / staff' : 'AI agent') }}
                                </p>
                                <div class="whitespace-pre-line rounded-2xl px-3 py-2 text-sm{{ $m['role'] === 'user'
                                        ? 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text))]'
                                        : ($m['role'] === 'staff' ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'bg-[rgb(var(--nx-gold)/0.1)] text-[rgb(var(--nx-text))]') }}">
                                    {{ $m['body'] }}
                                    @if ($m['voice'])
                                        <audio controls preload="none" src="{{ $m['voice'] }}" class="mt-2 w-full"></audio>
                                    @endif
                                    @if (! empty($m['attachment']))
                                        @if ($m['attachment_image'])
                                            <a href="{{ $m['attachment'] }}" target="_blank" rel="noopener">
                                                <img src="{{ $m['attachment'] }}" alt="{{ $m['attachment_name'] }}" class="mt-2 max-h-56 rounded-lg border border-[rgb(var(--nx-line))]" />
                                            </a>
                                        @else
                                            <a href="{{ $m['attachment'] }}" target="_blank" rel="noopener"
                                               class="mt-2 inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs">
                                                <x-icon name="file-text" class="h-4 w-4" /> {{ $m['attachment_name'] ?: 'Attachment' }}
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Staff reply: Chat Composer Pro (text + emoji; the server's sendReply() takes text only, so nothing else is offered). The
                     wire:key remounts the composer per ticket so a draft never leaks into another customer's conversation. --}}
                @if (config('composer.surfaces.staff_reply'))
                    <div class="mt-4 border-t border-[rgb(var(--nx-line))] pt-3" wire:key="reply-composer-{{ $selectedId }}"
                         x-data="{
                            async send(p) {
                                await $wire.set('reply', p.text, false);
                                await $wire.sendReply();
                                if (($wire.reply || '').trim() !== '') throw Object.assign(new Error(@js(__('composer.notSent'))), { fatal: true });
                            },
                         }"
                         x-on:cc:transmit="$event.detail.wait(send($event.detail.payload))">
                        <x-composer surface="staff" convo-id="staff-{{ $selectedId }}" :features="['emoji']" :attach-kinds="[]" :max-files="0" placeholder="Type your reply…" />
                    </div>
                @else
                    @include('livewire.admin.partials.legacy-support-reply')
                @endif
                <p class="mt-2 text-[11px] text-[rgb(var(--nx-text-2))]">Replies to paying customers are also delivered as voice if ElevenLabs is configured.</p>
            @endif
        </div>
    </div>
</div>

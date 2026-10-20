{{-- nx:converted (skin canvas + tokens) --}}
<div>
<x-nx.page class="px-0">
@php
    $field = 'w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3.5 py-2.5 text-sm text-[rgb(var(--nx-text))]   ';
    $label = 'mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))] ';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6  ';
    $btn = 'inline-flex items-center justify-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60';
    $ghost = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-[rgb(var(--nx-line))] px-3.5 py-2 text-xs font-semibold text-[rgb(var(--nx-text-2))] transition hover:border-[rgb(var(--nx-teal))] hover:text-[rgb(var(--nx-teal-ink))] disabled:opacity-60  ';
    $steps = ['numbers' => 'Line', 'team' => 'Team & hours', 'steps' => 'Call steps', 'review' => 'Go live'];
    $typeLabels = ['menu' => 'Menu (press a key)', 'queue' => 'Ring a team', 'forward' => 'Forward to a number', 'voicemail' => 'Voicemail', 'ai_agent' => 'AI voice assistant'];
    $nodeName = fn ($n) => ($n->label ?: ucfirst($n->type)).' #'.$n->id;
@endphp

<div class="mx-auto max-w-3xl">
    <div class="mb-6 flex items-center gap-3">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))] "><x-icon name="phone-forwarded" class="h-5 w-5" /></span>
        <div>
            <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))] ">Call flows</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))] ">Answer {{ $group->name }}'s business line with a menu, a team, or voicemail.</p>
        </div>
    </div>

    {{-- Step rail --}}
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($steps as $key => $title)
            <button type="button" wire:click="goto('{{ $key }}')" @disabled($key !== 'numbers' && ! $flow)
                    class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 {{ $step === $key ? 'bg-[rgb(var(--nx-cta-b))] text-white shadow-sm' : 'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-text-2))] ring-1 ring-[rgb(var(--nx-line))]   ' }}">
                <span class="flex h-4 w-4 items-center justify-center rounded-full bg-black/10 text-[10px] ">{{ $loop->iteration }}</span> {{ $title }}
            </button>
        @endforeach
    </div>

    @if ($error)
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] px-4 py-3 text-sm text-[rgb(var(--nx-bad))]   ">
            <x-icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $error }}
        </div>
    @endif

    {{-- 1 · Line --}}
    @if ($step === 'numbers')
        <div class="{{ $card }}">
            <h2 class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))] ">Choose the line to set up</h2>
            <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))] ">Only lines that support business voice appear here.</p>

            @forelse ($supported as $number)
                @php($existing = $flows[$number->id] ?? null)
                <div class="flex items-center justify-between gap-3 border-b border-[rgb(var(--nx-line))] py-3 last:border-0 " wire:key="line-{{ $number->id }}">
                    <div>
                        <p class="text-sm font-semibold text-[rgb(var(--nx-text))] ">{{ $number->phone_number }}</p>
                        <p class="text-xs {{ $existing?->isActive() ? 'text-[rgb(var(--nx-ok))] ' : 'text-[rgb(var(--nx-text-2))]' }}">
                            {{ $existing ? ($existing->isActive() ? 'Call flow live' : 'Call flow paused') : 'No call flow yet' }}
                        </p>
                    </div>
                    <button type="button" wire:click="selectNumber({{ $number->id }})" wire:loading.attr="disabled" wire:target="selectNumber({{ $number->id }})" class="{{ $btn }}">
                        {{ $existing ? 'Edit' : 'Set up' }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </button>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))] ">No business-voice lines in this workspace yet. Add one of your own lines below.</p>
            @endforelse

            @if ($unsupportedCount > 0)
                <p class="mt-3 flex items-start gap-1.5 rounded-lg bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-xs text-[rgb(var(--nx-text-2))]  ">
                    <x-icon name="alert-triangle" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    {{ $unsupportedCount }} {{ \Illuminate\Support\Str::plural('line', $unsupportedCount) }} in this workspace can't use call flows yet and {{ $unsupportedCount === 1 ? 'is' : 'are' }} hidden.
                </p>
            @endif
        </div>

        @if ($mine->isNotEmpty())
            <div class="{{ $card }} mt-4">
                <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))] ">Add one of your lines to this workspace</h2>
                @foreach ($mine as $number)
                    <div class="flex items-center justify-between gap-3 py-2" wire:key="mine-{{ $number->id }}">
                        <span class="text-sm text-[rgb(var(--nx-text))] ">{{ $number->phone_number }}</span>
                        <button type="button" wire:click="assignNumber({{ $number->id }})" wire:loading.attr="disabled" wire:target="assignNumber({{ $number->id }})"
                                wire:confirm="Move this line into {{ $group->name }}? Workspace admins will be able to manage how it's answered." class="{{ $ghost }}">
                            <x-icon name="plus" class="h-3.5 w-3.5" /> Add
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- 2 · Team & hours --}}
    @if ($step === 'team' && $flow)
        <div class="{{ $card }} mb-4">
            <h2 class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))] ">Ring groups</h2>
            <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))] ">Simultaneous rings everyone at once; sequential tries one person at a time, lowest priority number first.</p>

            @foreach ($ringGroups as $rg)
                <div class="mb-4 rounded-xl border border-[rgb(var(--nx-line))] p-4 " wire:key="rg-{{ $rg->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-[rgb(var(--nx-text))] ">{{ $rg->name }} <span class="ml-1 rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[10px] font-semibold uppercase text-[rgb(var(--nx-text-2))]  ">{{ $rg->strategy }}</span></p>
                        <button type="button" wire:click="deleteRingGroup({{ $rg->id }})" wire:confirm="Delete this ring group?" class="text-[rgb(var(--nx-text-2))] transition hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>

                    @foreach ($rg->members as $m)
                        <div class="mt-2 flex items-center justify-between gap-2 text-xs text-[rgb(var(--nx-text-2))] " wire:key="rgm-{{ $m->id }}">
                            <span>{{ $m->member?->user?->name ?? 'Seat' }} · priority {{ $m->priority }} · {{ $m->destination_number ?: 'their own line' }}{{ $m->ring_in_browser ? ' + browser and Naara app' : '' }}</span>
                            <button type="button" wire:click="removeSeat({{ $m->id }})" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                        </div>
                    @endforeach

                    <div class="mt-3 grid gap-2 sm:grid-cols-4">
                        <select wire:model="seatForm.{{ $rg->id }}.member" class="{{ $field }} sm:col-span-2">
                            <option value="">Add a seat…</option>
                            @foreach ($seats as $seat)
                                <option value="{{ $seat->id }}">{{ $seat->user?->name ?? $seat->invited_email }}</option>
                            @endforeach
                        </select>
                        <input type="number" min="0" wire:model="seatForm.{{ $rg->id }}.priority" placeholder="Priority" class="{{ $field }}">
                        <input type="tel" wire:model="seatForm.{{ $rg->id }}.number" placeholder="Override +234…" class="{{ $field }}">
                        <label class="flex items-center gap-2 text-xs text-[rgb(var(--nx-text-2))] sm:col-span-3">
                            <input type="checkbox" wire:model="seatForm.{{ $rg->id }}.browser" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))]"> Also ring in their browser dialer and, when paired, the Naara phone app
                        </label>
                        <button type="button" wire:click="addSeat({{ $rg->id }})" wire:loading.attr="disabled" wire:target="addSeat({{ $rg->id }})" class="{{ $ghost }}"><x-icon name="plus" class="h-3.5 w-3.5" /> Add seat</button>
                    </div>
                </div>
            @endforeach

            <form wire:submit="addRingGroup" class="grid gap-2 sm:grid-cols-4">
                <input type="text" wire:model="rgName" placeholder="New group, e.g. Sales" class="{{ $field }} sm:col-span-2">
                <select wire:model="rgStrategy" class="{{ $field }}">
                    <option value="simultaneous">Simultaneous</option>
                    <option value="sequential">Sequential</option>
                </select>
                <button type="submit" wire:loading.attr="disabled" wire:target="addRingGroup" class="{{ $btn }}"><x-icon name="plus" class="h-4 w-4" /> Create</button>
                @error('rgName') <p class="text-xs text-[rgb(var(--nx-bad))] sm:col-span-4">{{ $message }}</p> @enderror
            </form>
        </div>

        <div class="{{ $card }}">
            <h2 class="mb-1 flex items-center gap-1.5 text-sm font-bold text-[rgb(var(--nx-text))] "><x-icon name="clock" class="h-4 w-4" /> Business hours</h2>
            <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))] ">A menu step can send callers somewhere else outside these hours.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="{{ $label }}">Name</label><input type="text" wire:model="bhName" class="{{ $field }}"></div>
                <div><label class="{{ $label }}">Timezone</label><input type="text" wire:model="bhTimezone" list="bh-zones" class="{{ $field }}">
                    <datalist id="bh-zones">
                        @foreach (['Africa/Lagos', 'Africa/Accra', 'Africa/Nairobi', 'Africa/Johannesburg', 'Africa/Cairo', 'UTC', 'Europe/London', 'America/New_York', 'America/Los_Angeles'] as $tz)<option value="{{ $tz }}">@endforeach
                    </datalist>
                    @error('bhTimezone') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4 space-y-2">
                @foreach ($bhDays as $day => $row)
                    <div class="flex items-center gap-3" wire:key="bh-{{ $day }}">
                        <label class="flex w-20 items-center gap-2 text-sm font-semibold uppercase text-[rgb(var(--nx-text))] ">
                            <input type="checkbox" wire:model="bhDays.{{ $day }}.on" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))]"> {{ $day }}
                        </label>
                        <input type="time" wire:model="bhDays.{{ $day }}.start" class="{{ $field }} max-w-[8rem]">
                        <span class="text-xs text-[rgb(var(--nx-text-2))]">to</span>
                        <input type="time" wire:model="bhDays.{{ $day }}.end" class="{{ $field }} max-w-[8rem]">
                    </div>
                @endforeach
            </div>
            <div class="mt-4"><label class="{{ $label }}">Closed all day on (YYYY-MM-DD, comma-separated)</label><input type="text" wire:model="bhClosedDates" placeholder="2026-12-25, 2027-01-01" class="{{ $field }}"></div>
            <button type="button" wire:click="saveSchedule" wire:loading.attr="disabled" wire:target="saveSchedule" class="{{ $btn }} mt-4"><x-icon name="check" class="h-4 w-4" /> Save hours</button>
        </div>

        <div class="mt-4 flex justify-end"><button type="button" wire:click="goto('steps')" class="{{ $btn }}">Next: call steps <x-icon name="chevron-right" class="h-4 w-4" /></button></div>
    @endif

    {{-- 3 · Steps --}}
    @if ($step === 'steps' && $flow)
        <div class="{{ $card }} mb-4">
            <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))] ">Your steps</h2>
            @forelse ($nodes as $n)
                <div class="flex items-center justify-between gap-3 border-b border-[rgb(var(--nx-line))] py-3 last:border-0 " wire:key="node-{{ $n->id }}">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-[rgb(var(--nx-text))] ">{{ $nodeName($n) }}
                            @if ($flow->root_node_id === $n->id)<span class="ml-1 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 text-[10px] font-bold uppercase text-[rgb(var(--nx-teal-ink))] ">Starts here</span>@endif
                        </p>
                        <p class="truncate text-xs text-[rgb(var(--nx-text-2))] ">{{ $typeLabels[$n->type] ?? $n->type }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        @if ($flow->root_node_id !== $n->id)<button type="button" wire:click="makeRoot({{ $n->id }})" class="{{ $ghost }}">Start here</button>@endif
                        <button type="button" wire:click="editNode({{ $n->id }})" class="flex h-8 w-8 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))] hover:text-[rgb(var(--nx-teal-ink))] "><x-icon name="edit" class="h-4 w-4" /></button>
                        <button type="button" wire:click="deleteNode({{ $n->id }})" wire:confirm="Delete this step? Links to it are removed." class="flex h-8 w-8 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-bad)/0.1)] hover:text-[rgb(var(--nx-bad))] "><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))] ">No steps yet. Add the first one below — it becomes where every call starts.</p>
            @endforelse
        </div>

        <div class="{{ $card }}">
            <h2 class="mb-4 text-sm font-bold text-[rgb(var(--nx-text))] ">{{ $editingNodeId ? 'Edit step' : 'Add a step' }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="{{ $label }}">Type</label>
                    <select wire:model.live="nodeType" @disabled($editingNodeId) class="{{ $field }}">
                        @foreach ($nodeTypes as $t)<option value="{{ $t }}">{{ $typeLabels[$t] }}</option>@endforeach
                    </select>
                </div>
                <div><label class="{{ $label }}">Label (optional)</label><input type="text" wire:model="nodeLabel" placeholder="e.g. Main menu" class="{{ $field }}"></div>
            </div>

            <div class="mt-4 space-y-3">
                @if ($nodeType === 'menu')
                    <div><label class="{{ $label }}">What callers hear</label><textarea rows="2" wire:model="nodeCfg.prompt_text" placeholder="Thanks for calling Acme. Press 1 for sales, 2 for support." class="{{ $field }}"></textarea></div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'] as $d)
                            <div wire:key="digit-{{ $d }}"><label class="{{ $label }}">Key {{ $d }}</label>
                                <select wire:model="nodeCfg.digits.{{ $d }}" class="{{ $field }}"><option value="">—</option>@foreach ($nodes->where('id', '!=', $editingNodeId) as $n)<option value="{{ $n->id }}">{{ $nodeName($n) }}</option>@endforeach</select></div>
                        @endforeach
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="{{ $label }}">If they press something else</label><select wire:model="nodeCfg.invalid_retry_node_id" class="{{ $field }}"><option value="">Replay this menu</option>@foreach ($nodes as $n)<option value="{{ $n->id }}">{{ $nodeName($n) }}</option>@endforeach</select></div>
                        <div><label class="{{ $label }}">Seconds to wait for a key</label><input type="number" min="2" max="30" wire:model="nodeCfg.timeout_seconds" class="{{ $field }}"></div>
                        @if ($schedule)
                            <div><label class="{{ $label }}">Business hours</label><select wire:model="nodeCfg.business_hours_schedule_id" class="{{ $field }}"><option value="">Always open</option><option value="{{ $schedule->id }}">{{ $schedule->name }}</option></select></div>
                            <div><label class="{{ $label }}">Outside hours, go to</label><select wire:model="nodeCfg.closed_node_id" class="{{ $field }}"><option value="">—</option>@foreach ($nodes->where('id', '!=', $editingNodeId) as $n)<option value="{{ $n->id }}">{{ $nodeName($n) }}</option>@endforeach</select></div>
                        @endif
                    </div>
                @elseif ($nodeType === 'queue')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="{{ $label }}">Ring group</label><select wire:model="nodeCfg.ring_group_id" class="{{ $field }}"><option value="">Choose…</option>@foreach ($ringGroups as $rg)<option value="{{ $rg->id }}">{{ $rg->name }} ({{ $rg->strategy }})</option>@endforeach</select></div>
                        <div><label class="{{ $label }}">Ring for up to (seconds)</label><input type="number" min="5" max="120" wire:model="nodeCfg.max_wait_seconds" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}">If nobody answers, go to</label><select wire:model="nodeCfg.overflow_node_id" class="{{ $field }}"><option value="">Say goodbye</option>@foreach ($nodes->where('id', '!=', $editingNodeId) as $n)<option value="{{ $n->id }}">{{ $nodeName($n) }}</option>@endforeach</select></div>
                        <div><label class="{{ $label }}">Hold music URL (optional, https)</label><input type="url" wire:model="nodeCfg.hold_music_url" class="{{ $field }}"></div>
                    </div>
                @elseif ($nodeType === 'forward')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="{{ $label }}">Forward to</label><input type="tel" wire:model="nodeCfg.target_number" placeholder="+2348012345678" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}">Fallback (optional)</label><input type="tel" wire:model="nodeCfg.fallback_number" placeholder="+2348012345678" class="{{ $field }}"></div>
                    </div>
                @elseif ($nodeType === 'ai_agent')
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">The assistant answers, takes callback requests and hands over to the ring group set in <a href="{{ route('business.assistant') }}" wire:navigate class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">AI voice assistant</a>. Callers are told they are speaking with an automated assistant.</p>
                    <div><label class="{{ $label }}">If the assistant is unavailable, go to</label><select wire:model="nodeCfg.overflow_node_id" class="{{ $field }}"><option value="">Say goodbye</option>@foreach ($nodes->where('id', '!=', $editingNodeId) as $n)<option value="{{ $n->id }}">{{ $nodeName($n) }}</option>@endforeach</select></div>
                @else
                    <div><label class="{{ $label }}">Greeting audio URL (optional, https)</label><input type="url" wire:model="nodeCfg.greeting_url" placeholder="https://…/greeting.mp3" class="{{ $field }}"></div>
                    <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))] "><input type="checkbox" wire:model="nodeCfg.transcribe" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))]"> Transcribe messages</label>
                @endif
            </div>

            <div class="mt-5 flex items-center gap-2">
                <button type="button" wire:click="saveNode" wire:loading.attr="disabled" wire:target="saveNode" class="{{ $btn }}"><x-icon name="check" class="h-4 w-4" /> {{ $editingNodeId ? 'Save step' : 'Add step' }}</button>
                @if ($editingNodeId)<button type="button" wire:click="cancelNode" class="{{ $ghost }}">Cancel</button>@endif
            </div>
        </div>

        <div class="mt-4 flex justify-end"><button type="button" wire:click="goto('review')" class="{{ $btn }}">Next: review <x-icon name="chevron-right" class="h-4 w-4" /></button></div>
    @endif

    {{-- 4 · Review / go live --}}
    @if ($step === 'review' && $flow)
        <div class="{{ $card }}">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-[rgb(var(--nx-text))] ">{{ $flow->virtualNumber?->phone_number }}</p>
                    <p class="text-xs {{ $flow->isActive() ? 'text-[rgb(var(--nx-ok))] ' : 'text-[rgb(var(--nx-text-2))]' }}">{{ $flow->isActive() ? 'Live — callers hear this flow' : 'Paused — calls use your regular forwarding' }}</p>
                </div>
                @if ($flow->isActive())
                    <button type="button" wire:click="pause" wire:loading.attr="disabled" wire:target="pause" class="{{ $ghost }}"><x-icon name="pause" class="h-3.5 w-3.5" /> Pause</button>
                @else
                    <button type="button" wire:click="activate" wire:loading.attr="disabled" wire:target="activate" class="{{ $btn }}"><x-icon name="play" class="h-4 w-4" /> Go live</button>
                @endif
            </div>
            <ol class="space-y-2">
                @forelse ($nodes as $n)
                    <li class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))] " wire:key="rev-{{ $n->id }}">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[10px] font-bold text-[rgb(var(--nx-teal-ink))] ">{{ $loop->iteration }}</span>
                        {{ $nodeName($n) }} <span class="text-xs text-[rgb(var(--nx-text-2))]">· {{ $typeLabels[$n->type] ?? $n->type }}</span>
                        @if ($flow->root_node_id === $n->id)<span class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 text-[10px] font-bold uppercase text-[rgb(var(--nx-teal-ink))] ">Start</span>@endif
                    </li>
                @empty
                    <li class="text-sm text-[rgb(var(--nx-text-2))] ">No steps yet — go back and add one.</li>
                @endforelse
            </ol>
        </div>
    @endif
</div>
</x-nx.page>
</div>

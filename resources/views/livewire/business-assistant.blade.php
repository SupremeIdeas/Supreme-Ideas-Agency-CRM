{{-- nx:converted (skin canvas + tokens) --}}
@php
    $card = 'mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6';
    $inp = 'w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3.5 py-2.5 text-sm text-[rgb(var(--nx-text))]';
    $lbl = 'mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]';
    $usd = fn ($v) => '$'.rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
@endphp
<div>
<x-nx.page class="px-0">
<div class="mx-auto max-w-3xl pb-28">
    <div class="mb-6 flex items-center gap-3">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="mic" class="h-5 w-5" /></span>
        <div>
            <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]">AI voice assistant</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">{{ $group->name }}. Answers calls, takes callback requests and hands over to your team.</p>
        </div>
    </div>

    @unless ($ready)<x-nx.note variant="warn" icon="alert-triangle" style="margin-bottom:16px">The assistant is not available on this platform yet. You can prepare your settings now.</x-nx.note>@endunless

    <section class="{{ $card }}" aria-labelledby="ai-set">
        <h2 id="ai-set" class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">Settings</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">About {{ $usd($perMinute) }} per minute of call, from your wallet; unused minutes are refunded. To use it, add an "AI voice assistant" step to a number in Call flows. Callers always hear that they are speaking with an automated assistant.</p>
        <form wire:submit="save" class="space-y-4">
            <label class="flex items-start gap-3 text-sm text-[rgb(var(--nx-text))]">
                <input type="checkbox" wire:model="enabled" class="mt-1 h-4 w-4 rounded"> <span><b>Let the assistant answer calls</b></span>
            </label>
            <div><label for="ai-name" class="{{ $lbl }}">Assistant name</label><input id="ai-name" type="text" wire:model="agentName" class="{{ $inp }}">@error('agentName')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror</div>
            <div>
                <label for="ai-greeting" class="{{ $lbl }}">Welcome</label>
                <input id="ai-greeting" type="text" wire:model.live.debounce.400ms="greeting" placeholder="Hello, thanks for calling {{ $group->name }}." class="{{ $inp }}">
                <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Callers hear: "{{ $previewGreeting }}"</p>
                @error('greeting')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="ai-persona" class="{{ $lbl }}">What the assistant may say about your business</label>
                <textarea id="ai-persona" rows="7" wire:model="persona" placeholder="Opening hours, services, prices you are happy to share, how to book, what to do about common questions. The assistant says it does not know anything that is not written here." class="{{ $inp }}"></textarea>
                @error('persona')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="ai-lang" class="{{ $lbl }}">Language</label><select id="ai-lang" wire:model="language" class="{{ $inp }}">@foreach ($languages as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
                <div><label for="ai-clar" class="{{ $lbl }}">Hand over after this many misunderstandings</label><input id="ai-clar" type="number" min="1" max="6" wire:model="maxClarifications" class="{{ $inp }}">@error('maxClarifications')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="ai-rg" class="{{ $lbl }}">Hand over to</label><select id="ai-rg" wire:model="ringGroupId" class="{{ $inp }}"><option value="">Nobody (take a callback request instead)</option>@foreach ($ringGroups as $rg)<option value="{{ $rg->id }}">{{ $rg->name }}</option>@endforeach</select></div>
                <div><label for="ai-bh" class="{{ $lbl }}">Team hours</label><select id="ai-bh" wire:model="scheduleId" class="{{ $inp }}"><option value="">Always available</option>@foreach ($schedules as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="ns-cta ns-cta--pill ns-cta--sm" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
                @if ($notice)<span role="status" class="text-xs text-[rgb(var(--nx-ok))]">{{ $notice }}</span>@endif
            </div>
        </form>
    </section>

    <section class="{{ $card }}" aria-labelledby="ai-calls">
        <h2 id="ai-calls" class="mb-4 text-sm font-bold text-[rgb(var(--nx-text))]">Recent calls</h2>
        @forelse ($sessions as $s)
            <div class="border-b border-[rgb(var(--nx-line))] py-3 last:border-0" wire:key="ais-{{ $s->id }}">
                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $s->created_at->format('j M Y, H:i') }} · {{ gmdate('i:s', $s->duration_seconds) }} · {{ str_replace('_', ' ', $s->outcome ?: $s->status) }}</p>
                @if ($s->summary)<p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $s->summary }}</p>@endif
                @if ($s->callback)<p class="text-xs text-[rgb(var(--nx-text))]">Callback wanted: {{ $s->callback['preferred_time'] ?? '' }}@if (! empty($s->callback['caller_name'])) · {{ $s->callback['caller_name'] }}@endif @if (! empty($s->callback['note'])) · {{ $s->callback['note'] }}@endif</p>@endif
            </div>
        @empty
            <x-nx.empty art="bs-aivoice" title="No assistant calls yet" text="Calls the assistant takes show up here, and in Call recordings with their transcripts." />
        @endforelse
    </section>

    <a href="{{ route('business.workspace') }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Back to the workspace</a>
</div>
</x-nx.page>
</div>

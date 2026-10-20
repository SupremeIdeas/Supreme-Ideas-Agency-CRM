{{-- nx:converted (skin canvas + tokens) --}}
@php
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4';
    $inp = 'w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lbl = 'mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]';
    $btn = 'rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]';
    $statusText = ['APPROVED' => 'Approved: you can send it', 'PENDING' => 'Pending: Meta is reviewing it', 'REJECTED' => 'Rejected: edit and send again', 'PAUSED' => 'Paused by Meta (low quality)', 'DISABLED' => 'Disabled by Meta', 'IN_APPEAL' => 'In appeal'];
    $v = fn ($n) => '{'.'{'.$n.'}'.'}';
@endphp
<div>
<x-nx.page class="px-0">
<div class="mx-auto max-w-4xl space-y-4 pb-28" data-respond-templates>
    <div class="flex items-center gap-3 px-1">
        <div class="min-w-0 flex-1">
            <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]">WhatsApp templates</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">WhatsApp only lets you message someone first, or after 24 hours of silence, with a template Meta has approved.</p>
        </div>
        <a href="{{ route('business.respond') }}" wire:navigate class="{{ $btn }}">Inbox</a>
    </div>

    @if ($ok)<p class="rounded-xl bg-[rgb(var(--nx-ok)/0.12)] px-3 py-2 text-sm text-[rgb(var(--nx-ok))]" role="status">{{ $ok }}</p>@endif
    @if ($error)<p class="rounded-xl bg-[rgb(var(--nx-bad)/0.1)] px-3 py-2 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif

    @if (count($numbers) === 0)
        <section class="{{ $card }}"><p class="text-sm text-[rgb(var(--nx-text-2))]">Connect a WhatsApp number first in <a class="font-semibold underline" href="{{ route('business.channels') }}" wire:navigate>Channels</a>.</p></section>
    @else
        @if (count($numbers) > 1)
            <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="WhatsApp numbers">
                @foreach ($numbers as $n)
                    <button type="button" role="tab" aria-selected="{{ $currentId === $n['id'] ? 'true' : 'false' }}" wire:click="choose({{ $n['id'] }})" wire:key="num-{{ $n['id'] }}"
                            @class(['rounded-full border px-3 py-1.5 text-xs font-semibold', 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.18)] text-[rgb(var(--nx-text))]' => $currentId === $n['id'], 'border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text-2))]' => $currentId !== $n['id']])>{{ $n['label'] }}</button>
                @endforeach
            </div>
        @endif

        @if ($needsWaba)
            <section class="{{ $card }}" data-needs-waba>
                <h2 class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">One more detail</h2>
                <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Templates live on your WhatsApp Business Account, not on the number. Paste its ID (about 15 digits): in your Meta app open WhatsApp, then API Setup, and copy "WhatsApp Business Account ID". It is an ID, not a password.</p>
                <form wire:submit="saveWaba" class="flex gap-2">
                    <input type="text" inputmode="numeric" wire:model="wabaId" aria-label="WhatsApp Business Account ID" class="{{ $inp }} flex-1">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveWaba" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="saveWaba">Save</span><span wire:loading wire:target="saveWaba">Checking...</span></button>
                </form>
            </section>
        @else
            <section class="{{ $card }}" aria-label="Your templates">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-[rgb(var(--nx-text))]">Your templates ({{ count($templates) }})</h2>
                    <button type="button" wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh" class="{{ $btn }}"><span wire:loading.remove wire:target="refresh">Refresh from Meta</span><span wire:loading wire:target="refresh">Updating...</span></button>
                </div>
                @forelse ($templates as $t)
                    <div class="border-b border-[rgb(var(--nx-line))] py-3 last:border-0" wire:key="t-{{ $t['id'] ?: $t['name'].$t['language'] }}" data-template="{{ $t['name'] }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <b class="text-sm text-[rgb(var(--nx-text))]">{{ $t['name'] }}</b>
                            <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $t['language'] }} · {{ strtolower($t['category']) }}</span>
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-[rgb(var(--nx-ok)/0.14)] text-[rgb(var(--nx-ok))]' => $t['status'] === 'APPROVED', 'bg-[rgb(var(--nx-warn)/0.14)] text-[rgb(var(--nx-warn))]' => in_array($t['status'], ['PENDING', 'IN_APPEAL', 'PAUSED'], true), 'bg-[rgb(var(--nx-bad)/0.12)] text-[rgb(var(--nx-bad))]' => in_array($t['status'], ['REJECTED', 'DISABLED'], true)]) data-template-status>{{ $statusText[$t['status']] ?? ucfirst(strtolower($t['status'])) }}</span>
                            @if ($t['quality'])<span class="text-xs text-[rgb(var(--nx-text-2))]" data-template-quality>Quality: {{ ['GREEN' => 'high', 'YELLOW' => 'medium', 'RED' => 'low'][$t['quality']] ?? strtolower($t['quality']) }}</span>@endif
                            <button type="button" wire:click="remove('{{ $t['name'] }}')" wire:confirm="Delete this template in every language? This cannot be undone." class="ms-auto text-xs font-semibold text-[rgb(var(--nx-bad))]">Delete</button>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-[rgb(var(--nx-text-2))]">{{ $t['body'] }}</p>
                        @if ($t['footer'])<p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $t['footer'] }}</p>@endif
                        @if ($t['buttons'])<p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Buttons: {{ implode(' | ', $t['buttons']) }}</p>@endif
                        @if ($t['reason'])<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">Meta's reason: {{ $t['reason'] }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">No templates yet. Write one below.</p>
                @endforelse
            </section>

            <section class="{{ $card }}" aria-label="New template" data-new-template>
                <h2 class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">New template</h2>
                <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Use {{ $v(1) }}, {{ $v(2) }} for the parts that change (a name, an order number). Give an example for each, because Meta uses them to review it. Do not start or end the text with a variable.</p>
                <form wire:submit="create" class="space-y-3">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div><label for="tp-name" class="{{ $lbl }}">Name</label><input id="tp-name" type="text" wire:model="name" placeholder="order_update" class="{{ $inp }}"></div>
                        <div><label for="tp-cat" class="{{ $lbl }}">Kind</label>
                            <select id="tp-cat" wire:model="category" class="{{ $inp }}">@foreach ($categories as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                        <div><label for="tp-lang" class="{{ $lbl }}">Language</label><input id="tp-lang" type="text" wire:model="language" placeholder="en" class="{{ $inp }}"></div>
                    </div>
                    <div><label for="tp-body" class="{{ $lbl }}">Message text</label><textarea id="tp-body" rows="4" maxlength="1024" wire:model="body" class="{{ $inp }}" placeholder="Hi {{ $v(1) }}, your order {{ $v(2) }} has shipped and arrives in two days."></textarea></div>
                    <div class="grid gap-2 sm:grid-cols-4">
                        @foreach ([0, 1, 2, 3] as $i)<div><label class="{{ $lbl }}">Example for {{ $v($i + 1) }}</label><input type="text" wire:model="examples.{{ $i }}" class="{{ $inp }}"></div>@endforeach
                    </div>
                    <div><label for="tp-foot" class="{{ $lbl }}">Footer (optional)</label><input id="tp-foot" type="text" maxlength="60" wire:model="footer" class="{{ $inp }}"></div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ([0, 1, 2] as $i)<div><label class="{{ $lbl }}">Quick-reply button {{ $i + 1 }} (optional)</label><input type="text" maxlength="25" wire:model="buttons.{{ $i }}" class="{{ $inp }}"></div>@endforeach
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="create" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="create">Send to Meta for review</span><span wire:loading wire:target="create">Sending...</span></button>
                </form>
            </section>
        @endif
    @endif
</div>
</x-nx.page>
</div>

{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Who pays?</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">One router decides how every message and call travels and which balance pays. Naara chats use NaaraCredit. SMS and calls to real numbers use the main wallet. The two never cross, and a Naara ID is never sent as SMS. Try any case below; nothing is sent or charged.</p>
    </div>

    <form wire:submit="simulate" class="{{ $card }} space-y-3">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Simulator</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="{{ $lb }}">Who is sending (account email)</label><input type="email" wire:model="actorEmail" class="{{ $in }}">@error('actorEmail')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">To (Naara ID, @username or phone number)</label><input type="text" wire:model="target" placeholder="NR-... or +234..." class="{{ $in }}">@error('target')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
        </div>
        <div class="flex flex-wrap items-center gap-4 text-sm text-[rgb(var(--nx-text))]">
            <label class="flex items-center gap-2">Intent <select wire:model="intent" class="{{ $in }} !w-auto"><option value="message">Message</option><option value="call">Call</option></select></label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="viaSms"> Person chose SMS / phone call</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="media"> Photo, voice or video</label>
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="simulate" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="simulate">Show the route</span><span wire:loading wire:target="simulate">Working...</span></button>
        @if ($error)<p class="text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif
        @if ($plan)
            <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3 text-sm text-[rgb(var(--nx-text))]" data-router-result>
                <p class="font-semibold">{{ $plan['allowed'] ? 'Allowed' : 'Not allowed yet' }}: {{ str_replace('_', ' ', $plan['channel']) }} paid from {{ $plan['payer'] }}@if ($plan['price'] !== null) at {{ $plan['payer'] === 'credit' ? rtrim(rtrim(number_format($plan['price'], 2), '0'), '.').' credits' : '$'.number_format($plan['price'], 4) }} per {{ $plan['unit'] }}@endif</p>
                <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Target: {{ $plan['target']['name'] ?? 'not on Naara' }}@if ($plan['target']['naara_id']) · {{ $plan['target']['naara_id'] }}@endif · Sender balances: {{ $plan['balances']['credit'] }} credits, ${{ number_format($plan['balances']['wallet_usd'], 2) }} wallet</p>
                @if ($plan['requires_user_choice'])<p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">The person must choose this channel first.</p>@endif
                @if ($plan['suggestion'])<p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">{{ $plan['suggestion'] }}</p>@endif
                @foreach ($plan['blocker_text'] as $t)<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $t }}</p>@endforeach
            </div>
        @endif
    </form>

    <form wire:submit="saveRules" class="{{ $card }} space-y-3">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Destination rules (SMS and calls to real numbers)</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">Two-letter country codes, comma separated, for example NG, GH, KE. Denied countries are always blocked. If the allowed list is not empty, only those countries are open. Rules on international SMS vary by country; check consent and sender rules before opening a destination.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="{{ $lb }}">Denied countries</label><input type="text" wire:model="denied" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Allowed countries (empty = all not denied)</label><input type="text" wire:model="allowed" class="{{ $in }}"></div>
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="saveRules" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="saveRules">Save rules</span><span wire:loading wire:target="saveRules">Saving...</span></button>
    </form>
</div>

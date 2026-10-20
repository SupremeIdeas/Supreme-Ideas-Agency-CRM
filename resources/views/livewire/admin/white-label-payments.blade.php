{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm';
    $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $btn = 'rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-50';
    $btn2 = 'rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-2 text-xs font-semibold text-[rgb(var(--nx-text))] transition hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-50';
@endphp
@php $tab_now = $tab; $tabClass = fn ($t) => 'rounded-lg px-3 py-2 text-sm font-semibold '.($tab_now === $t ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'border border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]'); @endphp
<div class="mx-auto max-w-4xl space-y-6 pb-12">
    <x-admin.wl-hub active="setup" />
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">White Label payment setup</h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">The accounts buyers pay into, and the rates used to turn a USD price into the amount they send. Change an account any time; each change needs your password and a reason, and is logged.</p>
        </div>
        <a href="{{ route('admin.white-label', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">White-label oversight</a>
    </div>

    @if ($message)<div class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $message }}</div>@endif
    @if ($error)<div class="rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</div>@endif

    <div class="flex gap-2" role="tablist">
        <button type="button" wire:click="$set('tab','accounts')" class="{{ $tabClass('accounts') }}" role="tab" aria-selected="{{ $tab === 'accounts' ? 'true' : 'false' }}">Receiving accounts</button>
        <button type="button" wire:click="$set('tab','rates')" class="{{ $tabClass('rates') }}" role="tab" aria-selected="{{ $tab === 'rates' ? 'true' : 'false' }}">Rates</button>
    </div>

    @if ($tab === 'accounts')
        <section class="{{ $card }}" aria-labelledby="h-auth">
            <h2 id="h-auth" class="text-base font-bold text-[rgb(var(--nx-text))]">Confirm it is you</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Used for every save, turn on or off, and test confirmation below.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="block text-xs text-[rgb(var(--nx-text-2))]">Your password<input type="password" wire:model="password" autocomplete="current-password" class="{{ $inp }} mt-1"></label>
                <label class="block text-xs text-[rgb(var(--nx-text-2))]">Reason for the change<input type="text" wire:model="reason" maxlength="250" class="{{ $inp }} mt-1" placeholder="e.g. Replaced with the Grey business account"></label>
            </div>
        </section>

        <section class="{{ $card }}" aria-labelledby="h-acc">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="h-acc" class="text-base font-bold text-[rgb(var(--nx-text))]">Accounts</h2>
                <button type="button" class="{{ $btn }}" wire:click="newAccount">Add an account</button>
            </div>
            <ul class="mt-3 divide-y divide-[rgb(var(--nx-line))]">
                @forelse ($accounts as $a)
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3" wire:key="acc-{{ $a->id }}">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $a->label }} <span class="text-xs font-normal text-[rgb(var(--nx-text-2))]">{{ $a->currency }}{{ $a->network ? ' on '.$a->network : '' }}</span></p>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                <span class="{{ $a->enabled ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-text-2))]' }}">{{ $a->enabled ? 'Shown to buyers' : 'Hidden' }}</span>
                                @if ($a->test_confirmed_at)
                                    <span class="text-[rgb(var(--nx-ok))]">Test confirmed {{ $a->test_confirmed_at->diffForHumans() }}</span>
                                @else
                                    <span class="text-[rgb(var(--nx-warn))]">{{ $a->isCrypto() ? 'Needs a test transaction before it can be turned on' : 'No test transfer confirmed yet' }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="{{ $btn2 }}" wire:click="edit({{ $a->id }})">Edit</button>
                            @unless ($a->test_confirmed_at)
                                <button type="button" class="{{ $btn2 }}" wire:click="confirmTest({{ $a->id }})" wire:loading.attr="disabled" wire:target="confirmTest({{ $a->id }})">I sent a test and it arrived</button>
                            @endunless
                            <button type="button" class="{{ $btn }}" wire:click="toggle({{ $a->id }})" wire:loading.attr="disabled" wire:target="toggle({{ $a->id }})">{{ $a->enabled ? 'Hide' : 'Show to buyers' }}</button>
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No accounts yet. Add the account buyers should pay into.</li>
                @endforelse
            </ul>
        </section>

        @if ($formOpen)
            <section class="{{ $card }}" aria-labelledby="h-form">
                <h2 id="h-form" class="text-base font-bold text-[rgb(var(--nx-text))]">{{ $editingId ? 'Edit account' : 'New account' }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block text-xs text-[rgb(var(--nx-text-2))]">Type
                        <select wire:model.live="kind" class="{{ $inp }} mt-1" @disabled($editingId)><option value="bank">Bank account</option><option value="crypto">Crypto wallet</option></select></label>
                    <label class="block text-xs text-[rgb(var(--nx-text-2))]">Currency
                        <input type="text" wire:model.live.debounce.400ms="currency" maxlength="5" class="{{ $inp }} mt-1" placeholder="USD, GBP, EUR, USDT"></label>
                    @if ($kind === 'crypto')
                        <label class="block text-xs text-[rgb(var(--nx-text-2))]">Network
                            <select wire:model="network" class="{{ $inp }} mt-1">@foreach ($networks as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach</select></label>
                    @endif
                    <label class="block text-xs text-[rgb(var(--nx-text-2))] sm:col-span-2">Name shown to buyers
                        <input type="text" wire:model="label" maxlength="120" class="{{ $inp }} mt-1" placeholder="e.g. USD bank transfer (ACH / Wire)"></label>
                    <label class="block text-xs text-[rgb(var(--nx-text-2))] sm:col-span-2">Who the buyer is paying (shown above the details; leave empty for none)
                        <input type="text" wire:model="payeeNote" maxlength="300" class="{{ $inp }} mt-1" placeholder="e.g. You are paying Supreme Ideas Agency directly. Send so the full amount arrives."></label>
                </div>
                <div class="mt-4 space-y-2">
                    <p class="text-xs font-semibold text-[rgb(var(--nx-text))]">Details buyers will see</p>
                    @foreach ($rows as $i => $row)
                        <div class="grid grid-cols-[1fr_1.4fr_auto] gap-2" wire:key="row-{{ $i }}">
                            <input type="text" wire:model="rows.{{ $i }}.label" class="{{ $inp }}" aria-label="Detail name {{ $i + 1 }}" placeholder="Name">
                            <input type="text" wire:model="rows.{{ $i }}.value" class="{{ $inp }}" aria-label="Detail value {{ $i + 1 }}" placeholder="Value" autocomplete="off" spellcheck="false">
                            @if ($kind !== 'crypto')<button type="button" class="{{ $btn2 }}" wire:click="removeRow({{ $i }})" aria-label="Remove detail {{ $i + 1 }}">Remove</button>@endif
                        </div>
                    @endforeach
                    @if ($kind !== 'crypto')<button type="button" class="{{ $btn2 }}" wire:click="addRow">Add a detail</button>@endif
                </div>
                @if ($kind === 'crypto')
                    <p class="mt-3 rounded-lg bg-[rgb(var(--nx-warn)/0.12)] p-3 text-xs text-[rgb(var(--nx-warn))]">The address is checked for the right format and checksum. Crypto transfers cannot be reversed: after any change the address is switched off until you send a test transaction and confirm it.</p>
                @endif
                <div class="mt-4 flex gap-2">
                    <button type="button" class="{{ $btn }}" wire:click="save" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Save account</span><span wire:loading wire:target="save">Saving…</span></button>
                    <button type="button" class="{{ $btn2 }}" wire:click="cancel">Cancel</button>
                </div>
            </section>
        @endif

        <section class="{{ $card }}" aria-labelledby="h-log">
            <h2 id="h-log" class="text-base font-bold text-[rgb(var(--nx-text))]">Recent changes</h2>
            <ul class="mt-2 space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                @forelse ($changes as $c)
                    <li>{{ $c->created_at->format('d M Y H:i') }} · {{ str_replace('_', ' ', $c->action) }} · {{ $c->user?->name ?? 'system' }} · {{ $c->reason }}</li>
                @empty
                    <li>Nothing yet.</li>
                @endforelse
            </ul>
        </section>
    @else
        <section class="{{ $card }}" aria-labelledby="h-rs">
            <h2 id="h-rs" class="text-base font-bold text-[rgb(var(--nx-text))]">Rate settings</h2>
            <label class="mt-3 flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="useLive"> Use Grey live rates {{ $greyConfigured ? '' : '(Grey key not set yet)' }}</label>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="block text-xs text-[rgb(var(--nx-text-2))]">Oldest manual rate allowed (hours)<input type="number" min="1" max="168" wire:model="maxAgeHours" class="{{ $inp }} mt-1"></label>
                <label class="block text-xs text-[rgb(var(--nx-text-2))]">Quote lock, bank currencies (min)<input type="number" min="5" max="240" wire:model="lockFiat" class="{{ $inp }} mt-1"></label>
                <label class="block text-xs text-[rgb(var(--nx-text-2))]">Quote lock, USDT/USDC (min)<input type="number" min="5" max="240" wire:model="lockStable" class="{{ $inp }} mt-1"></label>
            </div>
            <button type="button" class="{{ $btn }} mt-3" wire:click="saveRateSettings">Save settings</button>
            <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">If Grey cannot be reached, the newest manual rate within the age limit is used. With neither, no payment amount is shown (it never guesses).</p>
        </section>

        <section class="{{ $card }}" aria-labelledby="h-live">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="h-live" class="text-base font-bold text-[rgb(var(--nx-text))]">Live rates from Grey</h2>
                <button type="button" class="{{ $btn }}" wire:click="checkLive" wire:loading.attr="disabled" wire:target="checkLive"><span wire:loading.remove wire:target="checkLive">Check now</span><span wire:loading wire:target="checkLive">Checking…</span></button>
            </div>
            @if ($liveCheck)
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($liveCheck as $c => $q)
                        <li class="rounded-lg border border-[rgb(var(--nx-line))] p-3 text-sm text-[rgb(var(--nx-text))]">1 {{ $c }} = {{ $q ? '$'.number_format($q['rate'], 6) : 'unavailable' }} <span class="text-xs text-[rgb(var(--nx-text-2))]">(after Grey's fee)</span></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="{{ $card }}" aria-labelledby="h-man">
            <h2 id="h-man" class="text-base font-bold text-[rgb(var(--nx-text))]">Manual rate (backup)</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <label class="block text-xs text-[rgb(var(--nx-text-2))]">Currency<input type="text" wire:model="manualCurrency" maxlength="5" class="{{ $inp }} mt-1"></label>
                <label class="block text-xs text-[rgb(var(--nx-text-2))]">Value of 1 unit in USD<input type="text" inputmode="decimal" wire:model="manualRate" class="{{ $inp }} mt-1" placeholder="1.3036"></label>
                <button type="button" class="{{ $btn }}" wire:click="saveManualRate">Save rate</button>
            </div>
            <ul class="mt-3 space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                @foreach ($manualRates as $r)<li>{{ $r->from_currency }}: {{ rtrim(rtrim(number_format((float) $r->rate, 8, '.', ''), '0'), '.') }} · {{ $r->as_of->diffForHumans() }}</li>@endforeach
            </ul>
        </section>
    @endif
</div>

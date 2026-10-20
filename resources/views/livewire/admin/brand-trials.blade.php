{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Naara Pro trials</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">A free Pro trial for verified brands. Nobody is charged during the trial; at the end the plan they picked is charged once from their wallet. A short wallet pauses the listing and we retry, then close the trial.</p>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach (['running' => 'Running', 'unfunded' => 'Wallet short', 'converted' => 'Converted', 'ended' => 'Ended'] as $k => $label)
            <div class="{{ $card }}"><p class="text-2xl font-bold text-[rgb(var(--nx-text))]">{{ $counts[$k] }}</p><p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $label }}</p></div>
        @endforeach
    </div>

    <form wire:submit="save" class="{{ $card }} space-y-3">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Settings</h2>
        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="enabled"> Offer the free trial on Get Listed</label>
        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="connectEnabled"> Brand Connect: owners can approve connections and send a welcome message (paid from the owner's NaaraCredit)</label>
        <div class="grid gap-3 sm:grid-cols-3">
            <div><label class="{{ $lb }}" for="ref-prec">Referral attribution</label><select id="ref-prec" wire:model="precedence" class="{{ $in }}">@foreach (\App\Support\BusinessCardReferralPrecedence::MODES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select><p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">When a visitor follows more than one referral link before signing up, this decides who gets the credit.</p>@error('precedence')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">Trial length (days)</label><input type="number" wire:model="days" min="1" max="30" class="{{ $in }}">@error('days')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">Days between retries</label><input type="number" wire:model="retryDays" min="1" max="14" class="{{ $in }}">@error('retryDays')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">Retries before closing</label><input type="number" wire:model="maxRetries" min="0" max="10" class="{{ $in }}">@error('maxRetries')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
    </form>

    <div class="{{ $card }}">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Trials</h2>
        <div class="mt-3 space-y-3">
            @forelse ($rows as $r)
                <div wire:key="t-{{ $r->id }}" class="rounded-xl border border-[rgb(var(--nx-line))] p-3 text-sm">
                    <p class="font-medium text-[rgb(var(--nx-text))]">{{ $r->brandPartner?->brand_name }} <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $r->brandPartner?->owner?->email }}</span></p>
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">
                        {{ $r->converted_at ? 'Converted '.$r->converted_at->format('d M Y') : ($r->status === 'past_due' ? 'Wallet short since '.$r->unfunded_since?->format('d M Y').' · retry '.$r->retry_count : ($r->status === 'trialing' ? 'Ends '.$r->trial_ends_at?->format('d M Y H:i') : 'Ended'.($r->end_reason ? ' ('.str_replace('_', ' ', $r->end_reason).')' : ''))) }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if (in_array($r->status, ['trialing', 'past_due'], true) && ! $r->converted_at)
                            <button type="button" wire:click="end({{ $r->id }})" wire:confirm="End this trial without a charge?" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">End trial</button>
                        @endif
                        <button type="button" wire:click="reset_({{ $r->brand_partner_id }})" wire:confirm="Let this person take another trial?" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">Allow another trial</button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No trials yet.</p>
            @endforelse
        </div>
    </div>
</div>

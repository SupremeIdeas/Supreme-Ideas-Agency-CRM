{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <x-admin.wl-hub active="invoices" />
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">White Label invoice settings</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Who the seller is on invoices and receipts. Both logos come from Admin > Branding. Use your true legal seller details; they are copied into each document when it is issued.</p>
    </div>
    <form wire:submit="save" class="{{ $card }} space-y-4">
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="{{ $lb }}">Legal seller name</label><input type="text" wire:model="sellerName" class="{{ $in }}" placeholder="The company or person that issues the invoice">@error('sellerName')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2"><label class="{{ $lb }}">Address</label><textarea wire:model="sellerAddress" rows="3" class="{{ $in }}"></textarea></div>
            <div><label class="{{ $lb }}">Registration number</label><input type="text" wire:model="sellerRegistration" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Tax / VAT ID</label><input type="text" wire:model="sellerTaxId" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Billing email</label><input type="email" wire:model="sellerEmail" class="{{ $in }}">@error('sellerEmail')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">Payment terms (days, for invoices with a balance)</label><input type="number" wire:model="termsDays" min="1" max="120" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Tax label</label><input type="text" wire:model="taxLabel" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Tax rate % (0 = none, added on top)</label><input type="number" step="0.01" wire:model="taxRate" min="0" max="100" class="{{ $in }}"></div>
            <div class="sm:col-span-2"><label class="{{ $lb }}">Terms printed on documents</label><textarea wire:model="terms" rows="3" class="{{ $in }}"></textarea></div>
            <div class="sm:col-span-2"><label class="{{ $lb }}">Notes printed on documents</label><textarea wire:model="notes" rows="2" class="{{ $in }}"></textarea></div>
        </div>
        <fieldset class="grid gap-3 sm:grid-cols-2"><legend class="{{ $lb }}">Number formats ({YYYY} is the year, {000000} the counter; example: {{ $example }})</legend>
            @foreach (['INV' => 'Invoices', 'RCT' => 'Receipts', 'PRO' => 'Proformas', 'CN' => 'Credit notes'] as $k => $name)
                <div><label class="{{ $lb }}">{{ $name }}</label><input type="text" wire:model="formats.{{ $k }}" class="{{ $in }}">@error('formats.'.$k)<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            @endforeach
        </fieldset>
        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="autoSend"> Email each document to the buyer when it is issued</label>
        <div class="flex flex-wrap gap-2">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
            <a href="{{ route('admin.white-label.invoice-preview') }}" target="_blank" rel="noopener" class="ns-cta ns-cta--pill ns-cta--ghost">Preview a sample PDF</a>
        </div>
    </form>
</div>

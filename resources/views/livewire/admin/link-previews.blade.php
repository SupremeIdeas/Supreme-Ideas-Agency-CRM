{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Link previews</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">The image shown when someone pastes a Naara link into WhatsApp, Slack, iMessage, or any other chat app. Each context below is independent — change one without affecting the others.</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        @foreach ([
            ['field' => 'default_image', 'context' => 'default', 'label' => 'Site-wide default', 'help' => 'Used for any page that doesn\'t set its own preview image.'],
            ['field' => 'invoice_image', 'context' => 'invoice', 'label' => 'Invoice links', 'help' => 'Shown when a merchant\'s invoice link (/i/...) is shared with a client.'],
            ['field' => 'referral_image', 'context' => 'referral', 'label' => 'Referral links', 'help' => 'Shown when the homepage is opened via a referral link.'],
        ] as $slot)
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $slot['label'] }}</h2>
                <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $slot['help'] }}</p>

                <div class="mt-3 flex items-center gap-4">
                    <img src="{{ $this->current[$slot['context']] }}" alt="" class="h-20 w-36 shrink-0 rounded-lg border border-[rgb(var(--nx-line))] object-cover">
                    <div class="min-w-0 flex-1">
                        <input type="file" wire:model="{{ $slot['field'] }}" accept="image/webp,image/jpeg,image/png"
                            class="block w-full text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-[rgb(var(--nx-teal-ink))]">
                        @error($slot['field']) <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                        @if ($this->{$slot['field']})
                            <img src="{{ $this->{$slot['field']}->temporaryUrl() }}" alt="" class="mt-2 h-16 w-28 rounded-lg border border-[rgb(var(--nx-line))] object-cover">
                        @endif
                    </div>
                    <button type="button" wire:click="resetContext('{{ $slot['context'] }}')" wire:confirm="Revert to the default banner for this context?"
                        class="shrink-0 rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                        Reset
                    </button>
                </div>
            </div>
        @endforeach

        <button type="submit" wire:loading.attr="disabled" wire:target="save"
            class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-medium text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
            <span wire:loading.remove wire:target="save">Save changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </form>
</div>

{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div>
    @if ($sent)
        <x-ui.alert variant="info" title="{{ $c['sent_title'] ?? '' }}" icon="badge-check">
            {{ $c['success'] ?? '' }}
        </x-ui.alert>
    @elseif (! $this->canDeliver)
        <x-ui.alert variant="warning" title="Email us directly" icon="mail">
            Our contact form is being set up. Reach us directly at
            <a href="mailto:{{ config('naara.support.email') }}" class="font-semibold underline">{{ config('naara.support.email') }}</a>
            @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
                or on <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener" class="font-semibold underline">WhatsApp</a>
            @endif
            — a human will reply fast.
        </x-ui.alert>
    @else
        <form wire:submit="send" class="space-y-4">
            {{-- Honeypot (hidden from humans). --}}
            <div class="hidden" aria-hidden="true"><input type="text" wire:model="website" tabindex="-1" autocomplete="off"></div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $c['label_name'] ?? '' }}</label>
                    <input type="text" wire:model="name" placeholder="{{ $c['ph_name'] ?? '' }}"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                    @error('name') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $c['label_email'] ?? '' }}</label>
                    <input type="email" wire:model="email" placeholder="{{ $c['ph_email'] ?? '' }}"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                    @error('email') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $c['label_subject'] ?? '' }}</label>
                <select wire:model="subject" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                    @foreach ($subjects as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $c['label_message'] ?? '' }}</label>
                <textarea wire:model="message" rows="5" placeholder="{{ $c['ph_message'] ?? '' }}"
                          class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm"></textarea>
                @error('message') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $c['disclaimer'] ?? '' }}</p>
                <x-ui.btn variant="primary" target="send" icon="send">{{ $c['button'] ?? '' }}</x-ui.btn>
            </div>
        </form>
    @endif
</div>

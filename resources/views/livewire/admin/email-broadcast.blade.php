{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl px-4 py-6" wire:key="email-broadcast">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Send a broadcast</h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Email an audience segment. Sends are queued in batches and logged.</p>
        </div>
        <a href="{{ route('admin.email-studio') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-[rgb(var(--nx-line))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">
            <x-icon name="mail" class="h-4 w-4" /> Template editor
        </a>
    </div>

    @if ($sent)
        <div class="mb-4 rounded-xl border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] px-4 py-2.5 text-sm font-medium text-[rgb(var(--nx-ok))]">{{ $sent }}</div>
    @endif

    <div class="space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Subject</span>
            <input type="text" wire:model="subject" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            @error('subject') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        </label>
        {{-- AI-assisted composer (content/billing/hot-menu blueprint Phase C) --}}
        <div class="flex items-center justify-between gap-3">
            <p class="text-[11px] text-[rgb(var(--nx-text-2))]">
                @unless ($aiEnabled)
                    Add an Anthropic API key (Integrations) to draft copy with Claude.
                @else
                    Let Claude draft a few alternate takes on the subject and message below.
                @endunless
            </p>
            <button type="button" wire:click="refine" @disabled(! $aiEnabled)
                    wire:loading.attr="disabled" wire:target="refine"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[rgb(var(--nx-teal))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] transition hover:bg-[rgb(var(--nx-cta-b)/0.1)] disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="zap" class="h-3.5 w-3.5" wire:loading.remove wire:target="refine" />
                <x-ui.spinner class="h-3.5 w-3.5" wire:loading wire:target="refine" />
                <span wire:loading.remove wire:target="refine">Refine with Claude</span>
                <span wire:loading wire:target="refine">Writing…</span>
            </button>
        </div>

        @if ($aiError)
            <p class="-mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $aiError }}</p>
        @endif

        @if (! empty($variations))
            <div class="space-y-2 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Variations</p>
                @foreach ($variations as $i => $variation)
                    <div wire:key="var-{{ $i }}" class="flex items-start justify-between gap-3 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $variation['subject'] }}</p>
                            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ \Illuminate\Support\Str::limit(strip_tags($variation['body']), 160) }}</p>
                        </div>
                        <button type="button" wire:click="applyVariation({{ $i }})"
                                class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-[rgb(var(--nx-cta-b))] px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">
                            <x-icon name="check" class="h-3.5 w-3.5" /> Apply
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Body</span>
            <div wire:ignore
                 x-data="emailComposer({
                     uploadUrl: @js(route('admin.email-broadcast.inline-image', ['adminGateway' => request()->route('adminGateway')])),
                     deepLinks: @js($deepLinks),
                 })">
                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                    <label class="text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Link selected text/image to:</label>
                    <select x-model="pickedLink" @change="applyDeepLink()"
                            class="rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-xs">
                        <option value="">Choose an in-app page…</option>
                        <template x-for="link in deepLinks" :key="link.key">
                            <option :value="link.url" x-text="link.label"></option>
                        </template>
                    </select>
                    <span x-show="linkHint" x-text="linkHint" x-cloak class="text-[11px] text-[rgb(var(--nx-warn))]"></span>
                </div>
                <input type="hidden" id="email-body-input" value="{{ $body }}">
{{-- nx:allow:start Rich-text editor: the typography plugin owns its own light/dark prose colours. --}}
                <trix-editor input="email-body-input" class="prose prose-sm max-w-none rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm dark:prose-invert"></trix-editor>
{{-- nx:allow:end --}}
            </div>
            <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Drop or attach an image to embed it inline. Sanitized before it's ever sent.</p>
            @error('body') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Audience</span>
                <select wire:model.live="audienceType" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @foreach ($types as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            @if ($audienceType !== 'all')
                <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Which</span>
                    <select wire:model.live="audienceValue" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        <option value="">Choose…</option>
                        @foreach (($audienceType === 'role' ? $roles : ($audienceType === 'account' ? $accounts : $segments)) as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('audienceValue') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </label>
            @endif
        </div>

        @if (! $confirming)
            <button type="button" wire:click="review" class="inline-flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">
                <x-icon name="send" class="h-4 w-4" /> Review &amp; send
            </button>
        @else
            <div class="rounded-xl border border-[rgb(var(--nx-gold)/0.3)] bg-[rgb(var(--nx-gold)/0.1)] p-4">
                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">
                    This will email <span class="text-[rgb(var(--nx-gold-ink))]">{{ number_format($this->recipientCount()) }}</span> {{ \Illuminate\Support\Str::plural('recipient', $this->recipientCount()) }}
                    ({{ \App\Support\BroadcastAudience::label($audienceType, $audienceValue) }}).
                </p>
                <div class="mt-3 flex items-center gap-2">
                    <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send"
                            class="inline-flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="send">Confirm &amp; queue send</span>
                        <span wire:loading wire:target="send">Queuing…</span>
                    </button>
                    <button type="button" wire:click="$set('confirming', false)" class="rounded-xl border border-[rgb(var(--nx-line))] px-4 py-2.5 text-sm font-medium text-[rgb(var(--nx-text-2))]">Cancel</button>
                </div>
            </div>
        @endif
    </div>

    {{-- Send history --}}
    @if ($history->isNotEmpty())
        <div class="mt-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Recent broadcasts</h2>
            <div class="overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))]">
                <table class="w-full text-sm">
                    <thead class="bg-[rgb(var(--nx-surface-2))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                        <tr><th class="px-4 py-2">Subject</th><th class="px-4 py-2">Audience</th><th class="px-4 py-2">Recipients</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Sent</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                        @foreach ($history as $b)
                            <tr class="text-[rgb(var(--nx-text))]">
                                <td class="px-4 py-2 font-medium text-[rgb(var(--nx-text))]">{{ \Illuminate\Support\Str::limit($b->subject, 40) }}</td>
                                <td class="px-4 py-2">{{ $b->audience_label }}</td>
                                <td class="px-4 py-2">{{ number_format($b->recipient_count) }}</td>
                                <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $b->status === 'sent' ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">{{ ucfirst($b->status) }}</span></td>
                                <td class="px-4 py-2 text-[rgb(var(--nx-text-2))]">{{ $b->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

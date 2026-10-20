{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Marketing Copy Studio</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">
            Fill the brand brief once, then generate on-brand copy variations for any marketing section — powered by Claude.
        </p>
    </div>

    @unless ($aiEnabled)
        <div class="mb-5 flex items-start gap-2 rounded-2xl border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-4 text-sm text-[rgb(var(--nx-warn))]">
            <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>Add an <strong>Anthropic API key</strong> under <a href="{{ route('admin.integrations') }}" wire:navigate class="underline">Integrations</a> to enable copy generation. You can still edit the brand brief now.</span>
        </div>
    @endunless

    {{-- Brand brief — the AI "trains" from this for every generation. --}}
    <form wire:submit="saveBrief" class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Brand brief</h2>
        <p class="mb-4 mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">Every generation writes in this voice. The brand name defaults to your white-label word.</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Brand name</label>
                <input type="text" wire:model="brand" placeholder="Naara"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @error('brand') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">One-liner — what it does</label>
                <input type="text" wire:model="one_liner" placeholder="eSIM data + real phone numbers for travellers across Africa"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Audience</label>
                <input type="text" wire:model="audience" placeholder="Frequent travellers, remote workers, diaspora"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Voice / tone</label>
                <input type="text" wire:model="tone" placeholder="Confident, warm, plain-spoken — no jargon"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Themes / keywords to lean on</label>
                <input type="text" wire:model="keywords" placeholder="No borders, no SIM swaps, instant, one app for data + numbers"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
            </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">Save brief</button>
            <span wire:loading wire:target="saveBrief" class="text-xs text-[rgb(var(--nx-text-2))]"><x-ui.spinner class="mr-1 inline h-3 w-3" /> Saving…</span>
        </div>
    </form>

    {{-- Page picker --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Page</span>
        @foreach ($pages as $key => $label)
            <button type="button" wire:click="selectPage('{{ $key }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition {{ $page === $key ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'border border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($error)
        <div class="mb-4 rounded-xl bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">{{ $error }}</div>
    @endif

    {{-- Sections on this page --}}
    <div class="space-y-4">
        @forelse ($sections as $s)
            <div wire:key="sec-{{ $s['id'] }}" class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">{{ $s['label'] }}</span>
                        <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ count($s['copy']) }} copy field{{ count($s['copy']) === 1 ? '' : 's' }}</span>
                    </div>
                    @if (count($s['copy']))
                        <button type="button" wire:click="generate({{ $s['id'] }})" @disabled(! $aiEnabled)
                                wire:loading.attr="disabled" wire:target="generate({{ $s['id'] }})"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-50">
                            <x-icon name="zap" class="h-4 w-4" wire:loading.remove wire:target="generate({{ $s['id'] }})" />
                            <x-ui.spinner class="h-4 w-4" wire:loading wire:target="generate({{ $s['id'] }})" />
                            <span wire:loading.remove wire:target="generate({{ $s['id'] }})">Generate</span>
                            <span wire:loading wire:target="generate({{ $s['id'] }})">Writing…</span>
                        </button>
                    @endif
                </div>

                {{-- Current copy --}}
                @if (count($s['copy']))
                    <dl class="mt-3 space-y-1.5">
                        @foreach ($s['copy'] as $path => $text)
                            <div class="flex gap-2 text-xs">
                                <dt class="w-32 shrink-0 truncate font-mono text-[rgb(var(--nx-text-2))]" title="{{ $path }}">{{ $path }}</dt>
                                <dd class="min-w-0 flex-1 text-[rgb(var(--nx-text-2))]">{{ \Illuminate\Support\Str::limit($text, 160) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">No editable copy in this section.</p>
                @endif

                {{-- Generated variations --}}
                @if (! empty($variations[$s['id']]))
                    <div class="mt-4 space-y-3 border-t border-[rgb(var(--nx-line))] pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Variations</p>
                        @foreach ($variations[$s['id']] as $i => $variation)
                            <div wire:key="var-{{ $s['id'] }}-{{ $i }}" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-[rgb(var(--nx-text-2))]">Option {{ $i + 1 }}</span>
                                    <button type="button" wire:click="apply({{ $s['id'] }}, {{ $i }})"
                                            class="inline-flex items-center gap-1 rounded-lg bg-[rgb(var(--nx-cta-b))] px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">
                                        <x-icon name="check" class="h-3.5 w-3.5" /> Apply
                                    </button>
                                </div>
                                <dl class="space-y-1">
                                    @foreach ($variation['preview'] as $path => $text)
                                        <div class="flex gap-2 text-xs">
                                            <dt class="w-32 shrink-0 truncate font-mono text-[rgb(var(--nx-text-2))]">{{ $path }}</dt>
                                            <dd class="min-w-0 flex-1 text-[rgb(var(--nx-text))]">{{ $text }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endforeach
                        <button type="button" wire:click="discard({{ $s['id'] }})" class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline hover:text-[rgb(var(--nx-text-2))]">Discard these</button>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-[rgb(var(--nx-line-strong))] p-8 text-center text-sm text-[rgb(var(--nx-text-2))]">
                This page has no builder sections yet. Add some in <a href="{{ route('admin.builder') }}" wire:navigate class="text-[rgb(var(--nx-teal-ink))] hover:underline">Page Builder</a>, then come back to write their copy.
            </div>
        @endforelse
    </div>

    @if ($sections->isNotEmpty())
        <p class="mt-5 text-center text-xs text-[rgb(var(--nx-text-2))]">
            Applied copy lands in the page’s <strong>draft</strong>. Publish it in <a href="{{ route('admin.builder') }}" wire:navigate class="text-[rgb(var(--nx-teal-ink))] hover:underline">Page Builder</a> to go live.
        </p>
    @endif
</div>

{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">API keys</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        Paste each provider, payment gateway and integration key here. Keys are encrypted at rest and take
        effect immediately — no file editing and no redeploy. A product stays <span class="font-medium">Coming Soon</span>
        until its key is saved, then flips <span class="font-medium text-[rgb(var(--nx-ok))]">Active</span>.
        Leave a field blank to keep the key you already saved.
    </p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Live status of every provider, driven by the real config. --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Provider status</h2>
        <div class="flex flex-wrap gap-2">
            @foreach ($statuses as $provider => $label)
                @php $active = $label === 'Active'; @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium{{ $active
                        ? 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]'
                        : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $active ? 'bg-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-line-strong))]' }}"></span>
                    {{ ucfirst($provider) }} — {{ $label }}
                </span>
            @endforeach
        </div>
    </div>

    {{-- eSIM catalogue sync — last result per provider + force a fetch now. --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">eSIM catalogue sync</h2>
        <div class="space-y-2">
            @foreach (['esimgo' => 'eSIM Go', 'airalo' => 'Airalo', 'quibity' => 'Quibity', 'zendit' => 'Zendit (Naara Connect + data)', 'oneglobal' => '1GLOBAL (Naara Connect)', 'montymobile' => 'Monty Mobile (Naara Connect)', 'gigs' => 'Gigs (Naara Connect)'] as $prov => $label)
                @php $st = $syncStatus[$prov] ?? null; @endphp
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2">
                    <span class="w-24 shrink-0 text-sm font-medium text-[rgb(var(--nx-text))]">{{ $label }}</span>
                    @if ($st === null)
                        <span class="text-xs text-[rgb(var(--nx-text-2))]">Never synced</span>
                    @elseif ($st['skipped'] ?? false)
                        <span class="inline-flex items-center gap-1 text-xs text-[rgb(var(--nx-text-2))]"><x-icon name="info" class="h-3.5 w-3.5" /> Not configured — no API key set</span>
                    @elseif ($st['ok'])
                        <span class="inline-flex items-center gap-1 text-xs text-[rgb(var(--nx-ok))]"><x-icon name="badge-check" class="h-3.5 w-3.5" /> {{ $st['count'] }} plans</span>
                        <span class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ \Illuminate\Support\Carbon::parse($st['at'])->diffForHumans() }}</span>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs text-[rgb(var(--nx-bad))]"><x-icon name="x" class="h-3.5 w-3.5" /> failed</span>
                        <span class="max-w-md truncate text-[11px] text-[rgb(var(--nx-text-2))]" title="{{ $st['error'] }}">{{ $st['error'] }}</span>
                        <span class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ \Illuminate\Support\Carbon::parse($st['at'])->diffForHumans() }}</span>
                    @endif
                    <button type="button" wire:click="syncNow('{{ $prov }}')" wire:loading.attr="disabled" wire:target="syncNow"
                            class="ml-auto shrink-0 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                        <span wire:loading.remove wire:target="syncNow">Sync now</span>
                        <span wire:loading wire:target="syncNow" class="inline-flex items-center gap-1"><x-ui.spinner class="h-3.5 w-3.5" /> Syncing…</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Media storage — which object store serves uploads (BUILD-11 §4). --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Primary media store</h2>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-surface-3))] px-3 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))]">
                <span class="h-1.5 w-1.5 rounded-full bg-[rgb(var(--nx-ok))]"></span>
                Serving from: {{ $activeMediaDisk }}
            </span>
        </div>
        <p class="mb-3 text-[11px] leading-relaxed text-[rgb(var(--nx-text-2))]">
            <span class="font-medium">Auto</span> prefers Cloudflare R2, then Wasabi, then the server’s own disk — using
            whichever is configured. Pick a specific store to pin it. An option that isn’t configured safely falls back to
            Auto, so uploads never break.
            @unless ($r2Ready)
                <span class="text-[rgb(var(--nx-warn))]">R2 isn’t configured yet — add its keys below to enable it.</span>
            @endunless
        </p>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="primary-disk" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Serve media from</label>
                <select id="primary-disk" wire:model="primaryDisk"
                        class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]">
                    <option value="auto">Auto (R2 → Wasabi → server)</option>
                    <option value="r2">Cloudflare R2</option>
                    <option value="wasabi">Wasabi</option>
                    <option value="public">Server disk</option>
                </select>
            </div>
            <button type="button" wire:click="savePrimaryDisk" wire:loading.attr="disabled" wire:target="savePrimaryDisk"
                    class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line-strong))] px-4 py-2.5 text-sm font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                <span wire:loading.remove wire:target="savePrimaryDisk">Save store</span>
                <span wire:loading wire:target="savePrimaryDisk" class="inline-flex items-center gap-1"><x-ui.spinner class="h-3.5 w-3.5" /> Saving…</span>
            </button>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        @foreach ($schema as $groupKey => $group)
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
                <h2 class="mb-4 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $group['label'] }}</h2>
                <div class="space-y-4">
                    @foreach ($group['fields'] as $field => $meta)
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label for="key-{{ $field }}" class="block text-xs font-medium text-[rgb(var(--nx-text-2))]">
                                    {{ $meta['label'] }}
                                    @if ($meta['secret'])
                                        <span class="ml-1 inline-flex items-center gap-0.5 text-[10px] font-normal text-[rgb(var(--nx-text-2))]">
                                            <x-icon name="shield" class="h-3 w-3" /> secret
                                        </span>
                                    @endif
                                </label>
                                @if ($previews[$field])
                                    <span class="font-mono text-[11px] text-[rgb(var(--nx-text-2))]">saved: {{ $previews[$field] }}</span>
                                @else
                                    <span class="text-[11px] text-[rgb(var(--nx-text-2))]">not set</span>
                                @endif
                            </div>
                            <input id="key-{{ $field }}"
                                   type="{{ $meta['secret'] ? 'password' : 'text' }}"
                                   autocomplete="off" spellcheck="false"
                                   wire:model="inputs.{{ $field }}"
                                   placeholder="{{ $previews[$field] ? 'Leave blank to keep current' : 'Paste key…' }}"
                                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 font-mono text-sm text-[rgb(var(--nx-text))] placeholder:font-sans placeholder:text-[rgb(var(--nx-text-2))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]">
                            <p class="mt-1 text-[11px] leading-relaxed text-[rgb(var(--nx-text-2))]">
                                <span class="font-mono">{{ $meta['env'] }}</span> — {{ $meta['hint'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex items-center justify-end gap-3">
            <span wire:loading wire:target="save" class="text-sm text-[rgb(var(--nx-text-2))]">Saving…</span>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save keys
            </button>
        </div>
    </form>
</div>

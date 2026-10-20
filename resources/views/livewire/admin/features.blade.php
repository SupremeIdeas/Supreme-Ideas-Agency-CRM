{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Features</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Switch platform features on or off — even before their API keys are in. A feature that’s switched on but missing keys stays “Coming soon” until you add them.</p>
    </div>

    @php($groups = collect($features)->groupBy('group'))
    @foreach ($groups as $group => $items)
        <h2 class="mb-2 mt-6 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $group }}</h2>
        <div class="space-y-2">
            @foreach ($items as $f)
                <div wire:key="feat-{{ $f['key'] }}" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]">
                            <x-icon :name="$f['icon']" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $f['name'] }}</p>
                                {{-- Live-state badge --}}
                                @if ($f['enabled'])
                                    <span class="rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-ok))]">Live</span>
                                @elseif ($f['admin_enabled'] && $f['needs_keys'] && ! $f['configured'])
                                    <span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-warn))]">Coming soon · needs keys</span>
                                @else
                                    <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-text-2))]">Off</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">{{ $f['description'] }}</p>

                            @if ($f['needs_keys'])
                                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                                    <span class="inline-flex items-center gap-1 {{ $f['configured'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-text-2))]' }}">
                                        <x-icon :name="$f['configured'] ? 'badge-check' : 'x'" class="h-3.5 w-3.5" />
                                        {{ $f['configured'] ? 'API keys set' : 'API keys not set' }}
                                    </span>
                                    @if (! empty($f['guide']))
                                        <button wire:click="showGuide('{{ $f['key'] }}')" class="font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                                            {{ $openGuide === $f['key'] ? 'Hide setup guide' : 'Setup guide' }}
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- On/off toggle (works even without keys) --}}
                        <button type="button" wire:click="toggle('{{ $f['key'] }}')" role="switch" :aria-checked="'{{ $f['admin_enabled'] ? 'true' : 'false' }}'"
                                class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition {{ $f['admin_enabled'] ? 'bg-[rgb(var(--nx-cta-b))]' : 'bg-[rgb(var(--nx-line-strong))]' }}">
                            <span class="inline-block h-5 w-5 transform rounded-full bg-[rgb(var(--nx-surface))] shadow transition {{ $f['admin_enabled'] ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                        </button>
                    </div>

                    {{-- Inline setup guide --}}
                    @if ($openGuide === $f['key'] && ! empty($f['guide']))
                        <div class="mt-3 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3 text-xs text-[rgb(var(--nx-text-2))]">
                            @if ($f['guide'] === 'webpush')
                                <p class="mb-2 font-semibold text-[rgb(var(--nx-text))]">Enable Naara Push (self-hosted, no third-party service)</p>
                                <ol class="list-inside list-decimal space-y-1.5">
                                    <li>On your server, from the app root, generate a VAPID key pair:
                                        <code class="mt-1 block rounded bg-black/80 px-2 py-1 font-mono text-[11px] text-white">php artisan webpush:vapid</code>
                                    </li>
                                    <li>Copy the two printed lines into your <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1 font-mono">.env</code> file:
                                        <code class="mt-1 block rounded bg-black/80 px-2 py-1 font-mono text-[11px] text-white">VAPID_PUBLIC_KEY=…<br>VAPID_PRIVATE_KEY=…</code>
                                    </li>
                                    <li>Optionally set <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1 font-mono">VAPID_SUBJECT</code> to a <code>mailto:</code> or your site URL (defaults to <code>{{ $appUrl }}</code>).</li>
                                    <li>Clear config cache: <code class="rounded bg-black/80 px-2 py-1 font-mono text-[11px] text-white">php artisan config:clear</code>, then keep this feature switched on. Users will see the “Turn on notifications” prompt.</li>
                                </ol>
                                <p class="mt-2 text-[11px] text-[rgb(var(--nx-text-2))]">The private key is a secret — it lives only in .env, never in code or the browser.</p>
                            @elseif ($f['guide'] === 'anthropic')
                                <p>Add your Anthropic API key under <a href="{{ route('admin.api-keys') }}" wire:navigate class="font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">Provider keys</a>. The AI agent lights up the moment a valid key is saved.</p>
                            @elseif ($f['guide'] === 'elevenlabs')
                                <p>Add your ElevenLabs API key under <a href="{{ route('admin.api-keys') }}" wire:navigate class="font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">Provider keys</a> to enable spoken replies.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>

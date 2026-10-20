{{-- Respond setup guide (MASTER-ONLY): ordered steps for Meta, Telegram, web chat, email, AI key and go-live. Skin tokens only. The samples are FAKE and only show the shape of a credential. --}}
<div data-omni-setup-guide>
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Respond setup guide</h1>
        <p class="mt-1 max-w-3xl text-sm text-[rgb(var(--nx-text-2))]">Everything you need to connect Meta (WhatsApp, Instagram, Messenger), Telegram, website chat, email and the AI key, in the order to do it. Provider dashboards move their menus, so each step says what to look for. Guide checked on {{ $verifiedOn }}.</p>
    </div>

    @if (! $https)
        <p class="mb-4 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]" role="alert" data-https-warning>APP_URL is <b>{{ $appUrl ?: 'empty' }}</b>. Meta and Telegram only accept an https address on your real domain. Fix this first (section 1) or every callback address below will be refused.</p>
    @endif

    <div class="grid gap-4 lg:grid-cols-[260px_1fr]">
        <nav class="space-y-1" aria-label="Setup sections">
            @foreach ($order as $k)
                <button type="button" wire:click="open('{{ $k }}')" data-guide-section="{{ $k }}" aria-current="{{ $currentKey === $k ? 'page' : 'false' }}"
                        class="flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm font-semibold {{ $currentKey === $k ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.14)] text-[rgb(var(--nx-text))]' : 'border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))]' }}">
                    <span>{{ $sections[$k]['title'] }}</span><small class="shrink-0 font-normal opacity-70">{{ $sections[$k]['minutes'] }} min</small>
                </button>
            @endforeach

            <section class="mt-4 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3" aria-label="Check a pasted value" data-format-checker>
                <h2 class="text-sm font-bold text-[rgb(var(--nx-text))]">Check what you copied</h2>
                <p class="mb-2 text-xs text-[rgb(var(--nx-text-2))]">Paste a token, ID or secret to see what it is and fix slips before you save it. It is checked on this page only: it is not saved, logged or sent to anyone.</p>
                <form wire:submit="checkValue" class="space-y-2">
                    <input type="password" wire:model="probe" autocomplete="off" spellcheck="false" aria-label="Value to check" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <button type="submit" wire:loading.attr="disabled" wire:target="checkValue" class="ns-cta ns-cta--pill ns-cta--sm">Check</button>
                </form>
                @if ($result)
                    <div class="mt-2 rounded-lg p-2 text-xs {{ $result['ok'] ? 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-warn)/0.14)] text-[rgb(var(--nx-text))]' }}" role="status" data-check-result>
                        @if ($result['kind'])<b>{{ $result['kind'] }}</b>{{ $result['secret'] ? ' (secret)' : '' }}<br>@endif
                        {{ $result['message'] }}
                        @foreach ($result['fixes'] as $f)<br>- {{ $f }}@endforeach
                    </div>
                @endif
            </section>
        </nav>

        <div class="space-y-4" data-guide-body="{{ $currentKey }}">
            <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <h2 class="text-lg font-bold text-[rgb(var(--nx-text))]">{{ $current['title'] }}</h2>
                <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">{{ $current['intro'] }}</p>
                @if ($current['needs'])
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">You need</p>
                    <ul class="mt-1 list-disc pl-5 text-sm text-[rgb(var(--nx-text))]">@foreach ($current['needs'] as $n)<li>{{ $n }}</li>@endforeach</ul>
                @endif
            </section>

            <ol class="space-y-3">
                @foreach ($current['steps'] as $i => $s)
                    <li class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" data-guide-step>
                        <div class="flex items-start gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-teal)/0.18)] text-sm font-bold text-[rgb(var(--nx-text))]">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-bold text-[rgb(var(--nx-text))]">{{ $s['title'] }}</h3>
                                <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">{{ str_replace('{APP_URL}', $appUrl, $s['body']) }}</p>
                                @if (! empty($s['url']))
                                    <div class="mt-2 rounded-lg bg-[rgb(var(--nx-surface-2))] p-2 text-xs" x-data="{ done: false }">
                                        <span class="text-[rgb(var(--nx-text-2))]">Callback URL:</span>
                                        <code class="break-all text-[rgb(var(--nx-text))]" data-callback-url>{{ $urls[$s['url']] }}</code>
                                        @if (! str_contains($urls[$s['url']], '{'))
                                            <button type="button" class="ml-2 rounded-full border border-[rgb(var(--nx-line-strong))] px-2 py-0.5 font-semibold text-[rgb(var(--nx-text))]" x-on:click="navigator.clipboard.writeText(@js($urls[$s['url']])); done = true; setTimeout(() => done = false, 1500)"><span x-text="done ? 'Copied' : 'Copy'"></span></button>
                                        @endif
                                    </div>
                                @endif
                                @if (! empty($s['copy']))
                                    <div class="mt-2 rounded-lg bg-[rgb(var(--nx-surface-2))] p-2 text-xs" x-data="{ done: false }">
                                        <code class="break-all text-[rgb(var(--nx-text))]">{{ $s['copy'] }}</code>
                                        <button type="button" class="ml-2 rounded-full border border-[rgb(var(--nx-line-strong))] px-2 py-0.5 font-semibold text-[rgb(var(--nx-text))]" x-on:click="navigator.clipboard.writeText(@js($s['copy'])); done = true; setTimeout(() => done = false, 1500)"><span x-text="done ? 'Copied' : 'Copy'"></span></button>
                                    </div>
                                @endif
                                @if (! empty($s['sample']) && isset($samples[$s['sample']]))
                                    <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">It looks like: <code class="break-all text-[rgb(var(--nx-text))]">{{ $samples[$s['sample']]['fake'] }}</code> <em>(a made-up example)</em></p>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($current['mistakes'])
                <section class="rounded-xl border border-[rgb(var(--nx-warn)/0.5)] bg-[rgb(var(--nx-warn)/0.08)] p-4" data-guide-mistakes>
                    <h3 class="text-sm font-bold text-[rgb(var(--nx-text))]">Mistakes that break this setup</h3>
                    <ul class="mt-1 list-disc pl-5 text-sm text-[rgb(var(--nx-text))]">@foreach ($current['mistakes'] as $m)<li>{{ $m }}</li>@endforeach</ul>
                </section>
            @endif

            @if ($current['samples'])
                <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" data-guide-samples>
                    <h3 class="mb-2 text-sm font-bold text-[rgb(var(--nx-text))]">What the credentials look like (examples are fake)</h3>
                    <div class="space-y-3">
                        @foreach ($current['samples'] as $sk)
                            @php($sm = $samples[$sk])
                            <div class="rounded-lg bg-[rgb(var(--nx-surface-2))] p-3 text-xs">
                                <b class="text-[rgb(var(--nx-text))]">{{ $sm['label'] }}</b>
                                <code class="mt-1 block break-all text-[rgb(var(--nx-text))]">{{ $sm['fake'] }}</code>
                                <p class="mt-1 text-[rgb(var(--nx-text-2))]">{{ $sm['shape'] }}</p>
                                <p class="text-[rgb(var(--nx-text-2))]">Goes in: {{ $sm['env'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($current['verify'])
                <section class="rounded-xl border border-[rgb(var(--nx-ok)/0.4)] bg-[rgb(var(--nx-ok)/0.07)] p-4" data-guide-verify>
                    <h3 class="text-sm font-bold text-[rgb(var(--nx-text))]">How to know it worked</h3>
                    <ul class="mt-1 list-disc pl-5 text-sm text-[rgb(var(--nx-text))]">@foreach ($current['verify'] as $v)<li>{{ $v }}</li>@endforeach</ul>
                </section>
            @endif

            @if ($currentKey === 'meta_where' || $currentKey === 'meta_app' || $currentKey === 'basics' || $currentKey === 'anthropic')
                <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" data-guide-status>
                    <h3 class="mb-2 text-sm font-bold text-[rgb(var(--nx-text))]">Where your credentials stand right now</h3>
                    <div class="divide-y divide-[rgb(var(--nx-line)/0.6)] text-sm">
                        @foreach ($status as $r)
                            <div class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                                <span class="text-[rgb(var(--nx-text))]">{{ $r['label'] }} <code class="text-xs text-[rgb(var(--nx-text-2))]">{{ $r['env'] }}</code></span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $r['status'] === 'MISSING' ? 'bg-[rgb(var(--nx-bad)/0.12)] text-[rgb(var(--nx-bad))]' : 'bg-[rgb(var(--nx-ok)/0.14)] text-[rgb(var(--nx-ok))]' }}">{{ $r['status'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">DEFAULT means a safe public value is already filled in (override it in .env if it is wrong). Values are never shown here.</p>
                </section>
            @endif
        </div>
    </div>
    <section class="mt-6 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" data-upload-rules>
        <h2 class="text-sm font-bold text-[rgb(var(--nx-text))]">Upload rules for business knowledge</h2>
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">What users may upload to teach their assistant. A higher minimum keeps thin, low-value files out so replies stay accurate. Safe limits are enforced whatever you type.</p>
        <form wire:submit="saveRules" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach (['min_bytes' => 'Smallest file (bytes)', 'min_words' => 'Fewest words of real text', 'max_mb' => 'Largest file (MB)', 'max_files' => 'Files per upload', 'max_sources' => 'Sources per business'] as $k => $label)
                <label class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $label }}
                    <input type="number" wire:model="ruleValues.{{ $k }}" data-rule="{{ $k }}" class="mt-1 w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error('ruleValues.'.$k)<span class="text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</span>@enderror
                </label>
            @endforeach
            <div class="sm:col-span-2 lg:col-span-5 flex flex-wrap items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="saveRules" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="saveRules">Save rules</span><span wire:loading wire:target="saveRules">Saving...</span></button>
                @if ($rulesSaved)<span class="text-xs text-[rgb(var(--nx-ok))]" role="status">{{ $rulesSaved }}</span>@endif
            </div>
        </form>
    </section>
</div>

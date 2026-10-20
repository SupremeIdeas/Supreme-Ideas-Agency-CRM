{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Setup health</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">What the platform has done for you, and what is only waiting on you. Everything marked "waiting" needs a login to a third-party site, so it cannot be pre-filled.</p>
    </div>

    @if ($ready)
        <div class="mb-5 rounded-2xl border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] p-5">
            <p class="flex items-center gap-2 font-semibold text-[rgb(var(--nx-ok))]"><x-icon name="check" class="h-5 w-5" /> No critical problems</p>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Anything marked "waiting" below is expected on a fresh install.</p>
        </div>
    @else
        <div class="mb-5 rounded-2xl border border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] p-5">
            <p class="flex items-center gap-2 font-semibold text-[rgb(var(--nx-bad))]"><x-icon name="info" class="h-5 w-5" /> Not ready for production</p>
            <p class="mt-1 text-sm text-[rgb(var(--nx-bad))]">Fix the items marked "Fix now" first.</p>
        </div>
    @endif

    {{-- Checks --}}
    <section class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        <h2 class="border-b border-[rgb(var(--nx-line))] px-5 py-3 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Checklist</h2>
        <ul class="divide-y divide-[rgb(var(--nx-line))]">
            @foreach ($checks as $c)
                <li class="flex items-start gap-3 px-5 py-3">
                    <span class="mt-0.5 inline-flex shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold
                        {{ ['ok' => 'bg-[rgb(var(--nx-ok)/0.14)] text-[rgb(var(--nx-ok))]', 'pending' => 'bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]', 'warn' => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]', 'fail' => 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]'][$c['status']] }}">
                        {{ ['ok' => 'Done', 'pending' => 'Waiting on you', 'warn' => 'Review', 'fail' => 'Fix now'][$c['status']] }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-[rgb(var(--nx-text))]">{{ $c['label'] }}</p>
                        @if ($c['status'] !== 'ok' && $c['fix'] !== '')
                            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $c['fix'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Bootstrap --}}
    <section class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Defaults applied by bootstrap</h2>
                <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Safe to run any time. It only adds what is missing; anything you edited stays exactly as you left it.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="previewBootstrap" wire:loading.attr="disabled" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-sm font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-60">Preview</button>
                <button type="button" wire:click="runBootstrap" wire:loading.attr="disabled" wire:confirm="Add any missing defaults now? Nothing you edited will be changed." class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Run bootstrap</button>
            </div>
        </div>
        @if ($ranMessage)<p class="mt-3 text-sm text-[rgb(var(--nx-ok))]">{{ $ranMessage }}</p>@endif
        @if ($preview)
            <p class="mt-3 text-xs font-medium text-[rgb(var(--nx-text-2))]">Would add {{ count($preview) }}:</p>
            <ul class="mt-1 max-h-48 overflow-auto text-xs text-[rgb(var(--nx-text-2))]">
                @foreach ($preview as $p)<li class="py-0.5"><span class="font-mono">{{ $p['item'] }}</span> <span class="opacity-80">{{ $p['note'] }}</span></li>@endforeach
            </ul>
        @endif
        @if ($manifest->isNotEmpty())
            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($manifest as $m)
                    <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3">
                        <dt class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ str_replace('-', ' ', $m->step) }}</dt>
                        <dd class="mt-1 text-lg font-bold text-[rgb(var(--nx-text))]">{{ $m->total }}</dd>
                        <dd class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ (int) $m->edited }} edited by you</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    {{-- System checks: the same read-only checks as `php artisan naara:verify`, stored per run. --}}
    <section class="mb-8">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">System checks: {{ $verifyRows->where('status', 'pass')->count() }} of {{ $verifyRows->count() }} pass</h2>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Read-only. Runs daily on its own; "Not built yet" means that area's automated check arrives in a later phase.</p>
            </div>
            <button type="button" wire:click="runChecks" wire:loading.attr="disabled" wire:target="runChecks" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-sm font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-60">
                <span wire:loading.remove wire:target="runChecks">Run all checks</span><span wire:loading wire:target="runChecks">Running...</span>
            </button>
        </div>
        @if ($verifyRows->isEmpty())
            <p class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-5 py-3 text-sm text-[rgb(var(--nx-text-2))]">No check has run yet. Press "Run all checks".</p>
        @else
            <ul class="divide-y divide-[rgb(var(--nx-line))] rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                @foreach ($verifyRows as $v)
                    <li class="flex flex-wrap items-start justify-between gap-2 px-5 py-2.5 text-sm">
                        <span class="min-w-0"><span class="font-medium text-[rgb(var(--nx-text))]">{{ $v->check }}</span> <code class="ml-1 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $v->area }}</code>
                            <span class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $v->detail }}</span></span>
                        @if ($v->status === 'fail')
                            <span class="rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-2 py-0.5 text-xs font-semibold text-[rgb(var(--nx-bad))]">Fail</span>
                        @elseif ($v->status === 'warn')
                            <span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 text-xs font-semibold text-[rgb(var(--nx-warn))]">{{ str_starts_with((string) $v->detail, 'No automated check yet') ? 'Not built yet' : 'Attention' }}</span>
                        @else
                            <span class="rounded-full bg-[rgb(var(--nx-ok)/0.14)] px-2 py-0.5 text-xs font-semibold text-[rgb(var(--nx-ok))]">Pass</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Browser errors in the last 24 hours (the beacon in resources/js/client-errors.js): the real cause behind "it hangs until I refresh". --}}
    <section class="mb-8">
        <div class="mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Browser errors, last 24 hours: {{ $clientErrors->sum('hits') }}</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Grouped by message. No IP address, query string, token or email is stored.</p>
        </div>
        @if ($clientErrors->isEmpty())
            <p class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-5 py-3 text-sm text-[rgb(var(--nx-text-2))]">No browser errors reported.</p>
        @else
            <ul class="divide-y divide-[rgb(var(--nx-line))] rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                @foreach ($clientErrors as $e)
                    <li class="px-5 py-2.5 text-sm">
                        <span class="font-medium text-[rgb(var(--nx-text))]">{{ \Illuminate\Support\Str::limit($e->message, 140) }}</span>
                        <span class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $e->hits }}x · {{ $e->kind }} · {{ $e->route ?: 'unknown page' }}@if ($e->component) · {{ $e->component }}@endif · last {{ \Illuminate\Support\Carbon::parse($e->last_at)->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Scheduled jobs: last run + overdue, from the heartbeats every scheduled command already writes. --}}
    <section class="mb-8">
        <div class="mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Scheduled jobs: {{ count(array_filter($jobs, fn ($j) => ! $j['overdue'])) }} of {{ count($jobs) }} on time</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Each one writes a heartbeat when it runs. "Overdue" with nothing recorded usually means the cron entry is not set up yet.</p>
        </div>
        <details class="group rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
            <summary class="flex cursor-pointer list-none items-center gap-2 px-5 py-3 text-sm font-semibold text-[rgb(var(--nx-text))]">
                <x-icon name="chevron-right" class="h-4 w-4 text-[rgb(var(--nx-text-2))] transition group-open:rotate-90" /> Show all {{ count($jobs) }} jobs
            </summary>
            <ul class="divide-y divide-[rgb(var(--nx-line))] border-t border-[rgb(var(--nx-line))]">
                @foreach ($jobs as $j)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5 text-sm">
                        <span class="min-w-0"><span class="font-medium text-[rgb(var(--nx-text))]">{{ $j['label'] }}</span> <code class="ml-1 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $j['name'] }}</code></span>
                        <span class="flex items-center gap-2 text-xs">
                            <span class="text-[rgb(var(--nx-text-2))]">{{ $j['ago'] ?? 'never run' }}</span>
                            @if ($j['outcome'] === 'failed')
                                <span class="rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-2 py-0.5 font-semibold text-[rgb(var(--nx-bad))]">Last run failed</span>
                            @elseif ($j['overdue'])
                                <span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 font-semibold text-[rgb(var(--nx-warn))]">Overdue</span>
                            @else
                                <span class="rounded-full bg-[rgb(var(--nx-ok)/0.14)] px-2 py-0.5 font-semibold text-[rgb(var(--nx-ok))]">On time</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </details>
        @if ($unwatchedJobs)
            <p class="mt-3 rounded-xl border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-3 text-xs text-[rgb(var(--nx-text))]">Scheduled but not watched (no overdue alert): {{ implode(', ', $unwatchedJobs) }}</p>
        @endif
    </section>

    {{-- Credentials --}}
    <section class="mb-8">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Integrations: {{ $saved }} of {{ $total }} connected</h2>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Paste keys on <a href="{{ route('admin.api-keys') }}" class="font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">API keys</a>. The addresses below are what you paste into each provider's own console; they are built from your APP_URL.</p>
            </div>
        </div>
        @unless (str_starts_with((string) config('app.url'), 'https://'))
            <p class="mb-3 rounded-xl border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-3 text-xs text-[rgb(var(--nx-text))]">APP_URL is {{ config('app.url') }}, which is not a public https address. Providers will refuse these URLs until it is.</p>
        @endunless
        <div class="space-y-3">
            @foreach ($byGroup as $group => $slots)
                <details class="group rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3">
                        <span class="flex items-center gap-2 font-semibold text-[rgb(var(--nx-text))]"><x-icon name="chevron-right" class="h-4 w-4 text-[rgb(var(--nx-text-2))] transition group-open:rotate-90" /> {{ $group }}</span>
                        <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ count(array_filter($slots, fn ($s) => $s['status'] === 'saved')) }} / {{ count($slots) }} connected</span>
                    </summary>
                    <ul class="divide-y divide-[rgb(var(--nx-line))] border-t border-[rgb(var(--nx-line))]">
                        @foreach ($slots as $s)
                            <li class="px-5 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-[rgb(var(--nx-text))]">{{ $s['label'] }}</span>
                                    <span class="flex items-center gap-2">
                                        @if ($s['status'] === 'saved')
                                            <button type="button" wire:click="testProvider('{{ $s['id'] }}')" wire:loading.attr="disabled" wire:target="testProvider('{{ $s['id'] }}')"
                                                    class="nx-btn nx-btn--ghost px-3 py-1 text-xs disabled:opacity-60">
                                                <span wire:loading.remove wire:target="testProvider('{{ $s['id'] }}')">Test connection</span>
                                                <span wire:loading wire:target="testProvider('{{ $s['id'] }}')">Testing</span>
                                            </button>
                                        @endif
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $s['status'] === 'saved' ? 'bg-[rgb(var(--nx-ok)/0.14)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]' }}">{{ $s['status'] === 'saved' ? 'Credentials saved' : 'Waiting on you' }}</span>
                                    </span>
                                </div>
                                @if (isset($tests[$s['id']]))
                                    @php($t = $tests[$s['id']])
                                    <p role="status" class="mt-2 flex items-start gap-2 rounded-lg px-3 py-2 text-xs {{ $t['status'] === 'ok' ? 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]' : ($t['status'] === 'fail' ? 'bg-[rgb(var(--nx-bad)/0.12)] text-[rgb(var(--nx-bad))]' : 'bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]') }}">
                                        <x-icon :name="$t['status'] === 'ok' ? 'check-circle' : ($t['status'] === 'fail' ? 'alert-triangle' : 'info')" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                        <span>{{ $t['message'] }}</span>
                                    </p>
                                @endif
                                @foreach ($s['webhooks'] as $w)
                                    <div class="mt-2 flex items-center gap-2" x-data="{ done: false }">
                                        <span class="w-40 shrink-0 text-xs text-[rgb(var(--nx-text-2))]">{{ $w['label'] }}</span>
                                        <code class="min-w-0 flex-1 truncate rounded bg-[rgb(var(--nx-surface-2))] px-2 py-1 text-xs text-[rgb(var(--nx-text))]">{{ $w['url'] }}</code>
                                        <button type="button" class="shrink-0 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline"
                                                x-on:click="navigator.clipboard.writeText(@js($w['url'])).then(() => { done = true; setTimeout(() => done = false, 1500) })"><span x-text="done ? 'Copied' : 'Copy'">Copy</span></button>
                                    </div>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </div>
        <div class="mt-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-5 py-3 text-xs text-[rgb(var(--nx-text-2))]">
            <p class="mb-1 font-semibold text-[rgb(var(--nx-text))]">Set up on their own screens</p>
            @foreach ($elsewhere as $p => $where)<p><span class="font-mono">{{ $p }}</span>: {{ $where }}</p>@endforeach
        </div>
    </section>
</div>

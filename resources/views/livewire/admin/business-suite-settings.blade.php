{{-- nx:converted (skin tokens only) --}}
@php
    $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $tabs = ['settings' => 'Settings', 'readiness' => 'Setup and health', 'workspaces' => 'Workspaces', 'activity' => 'Activity'];
@endphp
<div class="mx-auto max-w-4xl space-y-4 p-4 pb-48 lg:pb-28">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Business Suite</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">Master only. The control panel for team workspaces, shared numbers and call flows, and for the modules that follow.</p>
        </div>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $workspaceCount }} business workspace(s), {{ $liveFlowCount }} live call flow(s)</p>
    </div>

    <div role="tablist" aria-label="Business Suite sections" class="flex gap-1 border-b border-[rgb(var(--nx-line))]">
        @foreach ($tabs as $id => $label)
            <button type="button" role="tab" id="bs-tab-{{ $id }}" aria-selected="{{ $tab === $id ? 'true' : 'false' }}" wire:click="setTab('{{ $id }}')"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold transition {{ $tab === $id ? 'border-[rgb(var(--nx-teal))] text-[rgb(var(--nx-teal-ink))]' : 'border-transparent text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text))]' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($saved)
        <div class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $saved }}</div>
    @endif

    @if ($tab === 'settings')
        @unless ($canWrite)
            <p class="rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm text-[rgb(var(--nx-text-2))]">You can see every setting here. Only a super admin can change them.</p>
        @endunless

        @foreach ($groups as $id => $g)
            <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm" aria-labelledby="bs-{{ $id }}">
                <h2 id="bs-{{ $id }}" class="text-base font-bold text-[rgb(var(--nx-text))]">{{ $g['title'] }}</h2>
                <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $g['blurb'] }}</p>

                <div class="mt-4 divide-y divide-[rgb(var(--nx-line))]">
                    @foreach ($g['fields'] as $f)
                        @php
                            $slot = \App\Livewire\Admin\BusinessSuiteSettingsPage::slot($f['key']);
                            $custom = \App\Support\Business\BusinessSettingsSchema::isCustom($f);
                            $dirtyNow = in_array($f['key'], $dirtyKeys, true);
                            $avail = $f['available'] ?? true;
                            $locked = ! $canWrite || ! $avail;
                        @endphp
                        <div class="grid gap-2 py-3 sm:grid-cols-[1fr_200px] sm:items-start" wire:key="f-{{ $slot }}">
                            <div>
                                <label for="f-{{ $slot }}" class="flex flex-wrap items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                                    {{ $f['label'] }}
                                    @if (! $avail)<span class="rounded-full bg-[rgb(var(--nx-teal)/0.14)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-teal-ink))]">Not released yet</span>@endif
                                    @if ($f['danger'] ?? false)<span class="rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-bad))]">Affects live customers</span>@endif
                                    @if ($custom && $avail)<span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-warn))]">Changed from default</span>@endif
                                    @if ($dirtyNow)<span class="rounded-full bg-[rgb(var(--nx-teal)/0.14)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-teal-ink))]">Unsaved</span>@endif
                                </label>
                                <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $f['help'] }}</p>
                                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Default: {{ is_bool($f['default']) ? ($f['default'] ? 'On' : 'Off') : (($f['options'][$f['default']] ?? null) ?: $f['default']) }}@if ($f['unit']) {{ $f['unit'] }}@endif
                                    @if ($custom && $avail && $canWrite) · <button type="button" wire:click="resetField('{{ $slot }}')" wire:confirm="Reset &quot;{{ $f['label'] }}&quot; to its default?" class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Reset</button>@endif</p>
                                @error('values.'.$slot)<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($f['type'] === 'bool')
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[rgb(var(--nx-text))]">
                                        <input id="f-{{ $slot }}" type="checkbox" wire:model.live="values.{{ $slot }}" @disabled($locked) class="h-5 w-9 cursor-pointer rounded-full disabled:cursor-not-allowed disabled:opacity-50">
                                        <span>{{ $avail ? (($values[$slot] ?? false) ? 'On' : 'Off') : 'Off' }}</span>
                                    </label>
                                @elseif ($f['type'] === 'select')
                                    <select id="f-{{ $slot }}" wire:model.live="values.{{ $slot }}" @disabled($locked) class="{{ $inp }} disabled:opacity-60">
                                        @foreach ($f['options'] as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                                    </select>
                                @elseif ($f['type'] === 'text')
                                    <input id="f-{{ $slot }}" type="text" wire:model.blur="values.{{ $slot }}" @disabled($locked) maxlength="200" class="{{ $inp }} disabled:opacity-60">
                                @else
                                    <input id="f-{{ $slot }}" type="number" step="{{ $f['type'] === 'float' ? 'any' : 1 }}" min="{{ $f['min'] }}" max="{{ $f['max'] }}" wire:model.live.debounce.400ms="values.{{ $slot }}" @disabled($locked) class="{{ $inp }} disabled:opacity-60">
                                    @if ($f['unit'])<span class="shrink-0 text-xs text-[rgb(var(--nx-text-2))]">{{ $f['unit'] }}</span>@endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($canWrite)
            <div class="fixed inset-x-0 bottom-24 z-30 border-t border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface)/0.96)] p-3 backdrop-blur lg:bottom-0 lg:left-64">
                <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-[rgb(var(--nx-text-2))]">
                        @if (count($dirtyKeys))
                            <span class="font-semibold">{{ count($dirtyKeys) }}</span> unsaved change{{ count($dirtyKeys) === 1 ? '' : 's' }}
                        @else
                            No unsaved changes
                        @endif
                        @if ($needsConfirm)
                            <label class="mt-1 flex items-center gap-2 text-xs text-[rgb(var(--nx-bad))]">
                                <input type="checkbox" wire:model.live="confirmed" class="rounded"> I understand these changes affect live customers.
                            </label>
                            @error('confirmed')<span class="text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</span>@enderror
                        @endif
                    </div>
                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" @disabled(! count($dirtyKeys)) class="ns-cta ns-cta--pill ns-cta--sm disabled:opacity-50">
                        <span wire:loading.remove wire:target="save">Save changes</span><span wire:loading wire:target="save">Saving...</span>
                    </button>
                </div>
            </div>
        @endif
    @endif

    @if ($tab === 'readiness')
        @php
            $tone = ['ok' => '--nx-ok', 'warn' => '--nx-warn', 'fail' => '--nx-bad'];
            $word = ['ok' => 'Ready', 'warn' => 'Check', 'fail' => 'Fix'];
            $pre = 'mt-2 overflow-x-auto rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3 text-xs text-[rgb(var(--nx-text))]';
            $btn = 'inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))] disabled:opacity-60';
            $dl = $ffState['dl'] ?? null;
            $pct = ($dl && ($dl['total'] ?? 0) > 0) ? (int) min(100, round(100 * $dl['done'] / $dl['total'])) : 0;
        @endphp

        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" aria-labelledby="bs-ready">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 id="bs-ready" class="text-base font-bold text-[rgb(var(--nx-text))]">Setup and health</h2>
                    <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">Everything the Business Suite needs from this server, in plain words. Each line says what it found and what to do. Nothing here sends a message to a customer.</p>
                    <p class="mt-2 text-sm text-[rgb(var(--nx-text))]" role="status">
                        <span class="font-bold">{{ $counts['ok'] }}</span> ready ·
                        <span class="font-bold">{{ $counts['check'] }}</span> to check ·
                        <span class="font-bold">{{ $counts['fix'] }}</span> to fix.
                        You are on <span class="font-semibold">{{ $hostMode === 'vps' ? 'a VPS (Redis and Horizon)' : 'shared hosting (database queue and cron)' }}</span>.
                    </p>
                </div>
                <button type="button" wire:click="$refresh" wire:loading.attr="disabled" wire:target="$refresh" class="{{ $btn }}">
                    <x-nx.icon name="refresh" class="h-4 w-4" /> <span wire:loading.remove wire:target="$refresh">Check again</span><span wire:loading wire:target="$refresh">Checking...</span>
                </button>
            </div>
            @if ($fixResult)
                <p class="mt-3 rounded-lg border p-3 text-sm text-[rgb(var(--nx-text))]" style="border-color: rgb(var({{ $fixResult['ok'] ? '--nx-ok' : '--nx-bad' }})); background: rgb(var({{ $fixResult['ok'] ? '--nx-ok' : '--nx-bad' }}) / .12)" role="status">{{ $fixResult['message'] }}</p>
            @endif
            @unless ($canWrite)
                <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">You can read everything here. Only a super admin can press the fix buttons.</p>
            @endunless
        </section>

        @foreach ($groups as $gid => $g)
            <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" aria-labelledby="sh-{{ $gid }}" wire:key="sh-{{ $gid }}">
                <h2 id="sh-{{ $gid }}" class="text-base font-bold text-[rgb(var(--nx-text))]">{{ $g['title'] }}</h2>
                <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $g['blurb'] }}</p>
                @foreach ($g['checks'] as $c)
                    <div class="mt-3 flex gap-3 border-t border-[rgb(var(--nx-line))] pt-3" wire:key="sc-{{ $gid }}-{{ $c['id'] }}">
                        <span class="mt-0.5 inline-flex h-6 shrink-0 items-center rounded-full border px-2 text-[11px] font-bold uppercase tracking-wide text-[rgb(var(--nx-text))]" style="background: rgb(var({{ $tone[$c['status']] ?? '--nx-text-2' }}) / .2); border-color: rgb(var({{ $tone[$c['status']] ?? '--nx-text-2' }}))">{{ $word[$c['status']] ?? 'Check' }}</span>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $c['label'] }}</p>
                            <p class="text-[rgb(var(--nx-text-2))]">{{ $c['detail'] }}</p>
                            @if ($c['fix'])<p class="mt-1 text-[rgb(var(--nx-text))]"><span class="font-semibold">What to do:</span> {{ $c['fix'] }}</p>@endif
                            @if (! empty($c['action']) && $canWrite && ($c['action']['id'] ?? '') !== 'ffmpeg_install')
                                <button type="button" wire:click="runFix('{{ $c['action']['id'] }}')" wire:loading.attr="disabled" wire:target="runFix('{{ $c['action']['id'] }}')" class="{{ $btn }} mt-2">
                                    <span wire:loading.remove wire:target="runFix('{{ $c['action']['id'] }}')">{{ $c['action']['label'] }}</span><span wire:loading wire:target="runFix('{{ $c['action']['id'] }}')">Working...</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($gid === 'media')
                    <div class="mt-4 rounded-xl border border-[rgb(var(--nx-line))] p-4" @if ($ffmpegInstalling) wire:poll.1500ms="ffmpegTick" @endif>
                        <h3 class="text-sm font-bold text-[rgb(var(--nx-text))]">ffmpeg media converter</h3>
                        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">
                            ffmpeg is a free program that converts video and audio. The Suite works without it, but with it every GIF, video and voice note reaches every channel in the right format.
                            The platform looks for it in this order: the path you type below, a copy it installs into storage/ itself, then the server's own copy. Nothing needs root access.
                        </p>
                        <dl class="mt-3 grid gap-x-6 gap-y-1 text-xs sm:grid-cols-2">
                            <div><dt class="inline font-semibold text-[rgb(var(--nx-text))]">Found: </dt><dd class="inline text-[rgb(var(--nx-text-2))]">{{ $ff['available'] ? 'yes, version '.$ff['version'] : 'no' }}</dd></div>
                            <div><dt class="inline font-semibold text-[rgb(var(--nx-text))]">Where: </dt><dd class="inline break-all text-[rgb(var(--nx-text-2))]">{{ $ff['path'] ?: 'nowhere yet' }}@if ($ff['source']) ({{ $ff['source'] }})@endif</dd></div>
                            <div><dt class="inline font-semibold text-[rgb(var(--nx-text))]">Can run programs: </dt><dd class="inline text-[rgb(var(--nx-text-2))]">{{ $ff['exec']['ok'] ? 'yes' : 'no. '.$ff['exec']['reason'] }}</dd></div>
                            <div><dt class="inline font-semibold text-[rgb(var(--nx-text))]">Formats: </dt><dd class="inline text-[rgb(var(--nx-text-2))]">H.264 {{ $ff['h264'] ? 'yes' : 'no' }} · AAC {{ $ff['aac'] ? 'yes' : 'no' }} · Opus {{ $ff['opus'] ? 'yes' : 'no' }}</dd></div>
                        </dl>

                        @if ($ffmpegInstalling || in_array($ffState['phase'] ?? 'idle', ['plan', 'download', 'extract', 'verify'], true))
                            <div class="mt-3" role="status" aria-live="polite">
                                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">Installing: {{ ['plan' => 'getting ready', 'download' => 'downloading', 'extract' => 'unpacking', 'verify' => 'testing it'][$ffState['phase'] ?? 'plan'] ?? 'working' }}@if ($pct) ({{ $pct }}%)@endif</p>
                                <div class="mt-1 h-2 overflow-hidden rounded-full bg-[rgb(var(--nx-line))]"><div class="h-full rounded-full bg-[rgb(var(--nx-teal))] transition-all" style="width: {{ max(4, $pct) }}%"></div></div>
                                <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Keep this page open. It takes a minute or two and carries on in small steps, so a shared host's time limit cannot stop it.</p>
                            </div>
                        @elseif (($ffState['phase'] ?? '') === 'failed')
                            <p class="mt-3 rounded-lg border p-3 text-sm text-[rgb(var(--nx-text))]" style="border-color: rgb(var(--nx-bad)); background: rgb(var(--nx-bad) / .12)" role="alert">The install did not finish: {{ $ffState['error'] ?? 'unknown reason' }} Try again, use the upload or the download link below, or the path box.</p>
                        @endif

                        @if (! empty($ffState['log']))
                            <details class="mt-2 text-xs text-[rgb(var(--nx-text-2))]"><summary class="cursor-pointer font-semibold text-[rgb(var(--nx-text))]">What happened (log)</summary><pre class="{{ $pre }}">{{ implode("\n", $ffState['log']) }}</pre></details>
                        @endif

                        @if ($canWrite)
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" wire:click="ffmpegStart" wire:loading.attr="disabled" wire:target="ffmpegStart" @disabled($ffmpegInstalling || ! $ff['can_install']) class="{{ $btn }}">
                                    <span wire:loading.remove wire:target="ffmpegStart">{{ $ff['managed'] ? 'Reinstall the managed copy' : 'Install ffmpeg now' }}</span><span wire:loading wire:target="ffmpegStart">Starting...</span>
                                </button>
                                @if ($ff['managed'])
                                    <button type="button" wire:click="ffmpegRemove" wire:confirm="Remove the copy the platform installed?" class="{{ $btn }}">Remove the managed copy</button>
                                @endif
                            </div>
                            @unless ($ff['can_install'])<p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $ff['install_blocker'] }}</p>@endunless

                            <div class="mt-4 grid gap-4 border-t border-[rgb(var(--nx-line))] pt-4 sm:grid-cols-2">
                                <form wire:submit="ffmpegSaveSettings" class="space-y-2">
                                    <p class="text-xs font-bold text-[rgb(var(--nx-text))]">Already have it, or use your own download link</p>
                                    <label class="block text-xs text-[rgb(var(--nx-text-2))]" for="ff-path">Full path to ffmpeg (optional)</label>
                                    <input id="ff-path" type="text" wire:model="ffmpegPath" placeholder="/usr/bin/ffmpeg" class="{{ $inp }}" autocomplete="off">
                                    @error('ffmpegPath')<p class="text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
                                    <label class="block text-xs text-[rgb(var(--nx-text-2))]" for="ff-mirror">Download link, used first (optional)</label>
                                    <input id="ff-mirror" type="url" wire:model="ffmpegMirror" placeholder="https://your-site.com/ffmpeg-release-amd64-static.tar.xz" class="{{ $inp }}" autocomplete="off">
                                    @error('ffmpegMirror')<p class="text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
                                    <p class="text-[11px] text-[rgb(var(--nx-text-2))]">Use the link if your host cannot reach the usual download sites: put the static build on a server you control.</p>
                                    <button type="submit" wire:loading.attr="disabled" wire:target="ffmpegSaveSettings" class="{{ $btn }}"><span wire:loading.remove wire:target="ffmpegSaveSettings">Save and check</span><span wire:loading wire:target="ffmpegSaveSettings">Saving...</span></button>
                                </form>
                                <form wire:submit="ffmpegUploadNow" class="space-y-2">
                                    <p class="text-xs font-bold text-[rgb(var(--nx-text))]">Host blocks downloads? Upload it</p>
                                    <p class="text-xs text-[rgb(var(--nx-text-2))]">Download a "static" Linux build from johnvansickle.com on your own computer, unpack it, and upload the single file named <span class="font-mono">ffmpeg</span> (about 70 MB). Your host's upload limit must allow it.</p>
                                    <input type="file" wire:model="ffmpegUpload" aria-label="ffmpeg program file" class="block w-full text-xs text-[rgb(var(--nx-text))]">
                                    @error('ffmpegUpload')<p class="text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
                                    <button type="submit" wire:loading.attr="disabled" wire:target="ffmpegUploadNow,ffmpegUpload" @disabled(! $ffmpegUpload) class="{{ $btn }}"><span wire:loading.remove wire:target="ffmpegUploadNow,ffmpegUpload">Install the uploaded file</span><span wire:loading wire:target="ffmpegUploadNow,ffmpegUpload">Working...</span></button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        @endforeach

        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" aria-labelledby="bs-guide">
            <h2 id="bs-guide" class="text-base font-bold text-[rgb(var(--nx-text))]">Set-up steps for your hosting</h2>
            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">Copy and paste. The paths below are filled in for this server. The full guide, with troubleshooting, is docs/business-suite/SETUP-AND-HOSTING.md.</p>
            @foreach (['shared' => 'Shared hosting (cPanel)', 'vps' => 'VPS or Cloudways'] as $mode => $title)
                <details class="mt-3 rounded-xl border border-[rgb(var(--nx-line))] p-3" @if ($mode === $hostMode) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $title }}@if ($mode === $hostMode) <span class="ml-1 text-xs font-normal text-[rgb(var(--nx-text-2))]">(this server)</span>@endif</summary>
                    @foreach ($guide[$mode] as $step)
                        <div class="mt-3 text-sm">
                            <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $step[0] }}</p>
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $step[1] }}</p>
                            @if (! empty($step[2]))<pre class="{{ $pre }}">{{ $step[2] }}</pre>@endif
                        </div>
                    @endforeach
                </details>
            @endforeach
            <details class="mt-3 rounded-xl border border-[rgb(var(--nx-line))] p-3">
                <summary class="cursor-pointer text-sm font-semibold text-[rgb(var(--nx-text))]">AI voice agent (VPS only)</summary>
                <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Only needed if you switch the AI voice agent on. Shared hosting cannot run it; everything else in the Suite works on shared hosting.</p>
                @foreach ($voiceGuide as $step)
                    <div class="mt-3 text-sm">
                        <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $step[0] }}</p>
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $step[1] }}</p>
                        @if (! empty($step[2]))<pre class="{{ $pre }}">{{ $step[2] }}</pre>@endif
                    </div>
                @endforeach
            </details>
            <p class="mt-4 text-xs text-[rgb(var(--nx-text-2))]">On a server you can reach by terminal, <span class="font-mono">php artisan business:doctor --fix</span> runs this whole check and repairs what it can.</p>
        </section>
    @endif

    @if ($tab === 'workspaces')
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" aria-labelledby="bs-ws">
            <h2 id="bs-ws" class="text-base font-bold text-[rgb(var(--nx-text))]">Business workspaces</h2>
            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">The most recent 100 on this install. Read only: a workspace's owner manages it, and spending always comes from the owner's own wallet.</p>
            @forelse ($workspaces as $w)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[rgb(var(--nx-line))] pt-3 text-sm" wire:key="ws-{{ $w['id'] }}">
                    <div>
                        <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $w['name'] }}</p>
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $w['owner'] }} · opened {{ $w['created']?->diffForHumans() }}@if ($w['verified']) · business email confirmed @endif</p>
                    </div>
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $w['seats'] }} seat(s) · {{ $w['numbers'] }} number(s) · {{ $w['live_flows'] }} live flow(s)</p>
                </div>
            @empty
                <p class="mt-4 rounded-xl border border-dashed border-[rgb(var(--nx-line-strong))] p-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No business workspaces yet.</p>
            @endforelse
        </section>
    @endif

    @if ($tab === 'activity')
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" aria-labelledby="bs-act">
            <h2 id="bs-act" class="text-base font-bold text-[rgb(var(--nx-text))]">Recent activity</h2>
            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">Setting changes, seat changes, numbers added and call flows going live or paused. The last 50.</p>
            @forelse ($activity as $a)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[rgb(var(--nx-line))] pt-3 text-sm" wire:key="au-{{ $a->id }}">
                    <p class="font-mono text-xs text-[rgb(var(--nx-text))]">{{ $a->action }}</p>
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">user #{{ $a->user_id ?? '-' }} · {{ $a->created_at?->diffForHumans() }}</p>
                </div>
            @empty
                <p class="mt-4 rounded-xl border border-dashed border-[rgb(var(--nx-line-strong))] p-6 text-center text-sm text-[rgb(var(--nx-text-2))]">Nothing yet.</p>
            @endforelse
        </section>
    @endif
</div>

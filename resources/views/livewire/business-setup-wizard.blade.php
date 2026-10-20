{{-- nx:converted (skin tokens only). NaaraBusiness Suite Setup Wizard (MASTER-ONLY): a resumable pop-up, one step at a time. --}}
<div data-business-setup-wizard>
@php($wizArt = ['profile' => 'bs-profile', 'channels' => 'bs-channels', 'team' => 'bs-team', 'call_flows' => 'bs-callflows', 'recording' => 'bs-recording', 'ai_voice' => 'bs-aivoice', 'integrations' => 'bs-integrations', 'done' => 'bs-done'])
@if ($open && $group)
    <x-nx.sheet title="Finish setup" :subtitle="$percent.'% complete'" icon="settings" :nia="true" closeAction="close" backLabel="Close" closeLabel="Close">
        {{-- Progress and the step list. A chip per step; locked steps explain what unlocks them. --}}
        <div class="mb-4">
            <div class="h-2 overflow-hidden rounded-full bg-[rgb(var(--nx-surface-3))]" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-[rgb(var(--nx-teal))] transition-all" style="width: {{ $percent }}%"></div></div>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                @foreach ($steps as $s)
                    <button type="button" wire:click="goTo('{{ $s['key'] }}')" data-wizard-step="{{ $s['key'] }}" aria-current="{{ $step === $s['key'] ? 'step' : 'false' }}"
                            class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold {{ $step === $s['key'] ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.14)] text-[rgb(var(--nx-teal-ink))]' : 'border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))]' }}">
                        <span class="h-2 w-2 rounded-full {{ match ($s['status']) { 'done' => 'bg-[rgb(var(--nx-ok))]', 'attention' => 'bg-[rgb(var(--nx-warn))]', 'locked' => 'bg-[rgb(var(--nx-line-strong))]', default => 'bg-[rgb(var(--nx-text-2)/0.5)]' } }}"></span>
                        {{ $s['title'] }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($ok)<p role="status" class="mb-3 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">{{ $ok }}</p>@endif
        @if ($error)<p role="alert" class="mb-3 rounded-lg bg-[rgb(var(--nx-bad)/0.12)] p-3 text-sm text-[rgb(var(--nx-bad))]">{{ $error }}</p>@endif

        @if ($step === 'done')
            <div class="space-y-3" data-wizard-done>
                <img src="{{ asset('images/illustrations/bs-done.svg') }}" alt="" width="240" height="180" class="mx-auto h-36 w-auto" data-wizard-art>
                <h3 class="text-base font-bold text-[rgb(var(--nx-text))]">Check that it all works</h3>
                <p class="text-sm text-[rgb(var(--nx-text-2))]">This asks each connected channel and each switched-on feature whether it really works, and tells you pass or fail in plain words.</p>
                <button type="button" wire:click="runSelfTest" wire:loading.attr="disabled" wire:target="runSelfTest" class="ns-cta !w-auto px-6"><span wire:loading.remove wire:target="runSelfTest">Run the check</span><span wire:loading wire:target="runSelfTest">Checking...</span></button>
                @foreach ($selfTest as $t)
                    <div class="flex items-start gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-3 text-sm" data-self-test="{{ $t['pass'] ? 'pass' : 'fail' }}">
                        <span class="mt-0.5 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $t['pass'] ? 'bg-[rgb(var(--nx-ok)/0.14)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' }}">{{ $t['pass'] ? 'Pass' : 'Check' }}</span>
                        <div><p class="font-semibold text-[rgb(var(--nx-text))]">{{ $t['label'] }}</p><p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $t['detail'] }}</p></div>
                    </div>
                @endforeach
            </div>
        @elseif ($current)
            <div class="space-y-4" wire:key="step-{{ $current['key'] }}">
                @if (isset($wizArt[$current['key']]))<img src="{{ asset('images/illustrations/'.$wizArt[$current['key']].'.svg') }}" alt="" width="240" height="180" class="mx-auto h-36 w-auto sm:h-40" data-wizard-art>@endif
                <div>
                    <h3 class="text-base font-bold text-[rgb(var(--nx-text))]">{{ $current['title'] }}@if ($current['optional']) <span class="text-xs font-normal text-[rgb(var(--nx-text-2))]">(optional)</span>@endif</h3>
                    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]"><b>What this enables:</b> {{ $current['enables'] }}</p>
                    <p class="mt-2 text-xs font-semibold {{ match ($current['status']) { 'done' => 'text-[rgb(var(--nx-ok))]', 'attention' => 'text-[rgb(var(--nx-warn))]', default => 'text-[rgb(var(--nx-text-2))]' } }}">{{ match ($current['status']) { 'done' => 'Connected', 'attention' => 'Needs attention', 'locked' => 'Locked', default => 'Not set up' } }}: {{ $current['detail'] }}</p>
                </div>

                @if ($current['status'] === 'locked')
                    <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-4 text-sm text-[rgb(var(--nx-text-2))]" data-wizard-locked>
                        <b class="text-[rgb(var(--nx-text))]">{{ $current['feature_label'] }}</b> is not part of your current Naara Pro plan. A plan that includes it unlocks this step; everything you have already set up stays as it is.
                    </div>

                @elseif ($current['key'] === 'profile')
                    <form wire:submit="saveProfile" class="space-y-3">
                        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Workspace name</label>
                            <input type="text" wire:model="name" maxlength="255" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">@error('name')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                        <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Business email</label>
                            <input type="email" wire:model="businessEmail" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">@error('businessEmail')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveProfile" class="ns-cta !w-auto px-6"><span wire:loading.remove wire:target="saveProfile">Save and continue</span><span wire:loading wire:target="saveProfile">Saving...</span></button>
                    </form>

                @elseif ($current['key'] === 'channels')
                    @foreach ($connections as $c)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-3 text-sm" wire:key="conn-{{ $c['id'] }}">
                            <div><p class="font-semibold text-[rgb(var(--nx-text))]">{{ $c['name'] }} {{ $c['label'] }}</p><p class="text-xs {{ $c['status'] === 'active' ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-warn))]' }}">{{ $c['status'] === 'active' ? 'Active' : ($c['error'] ?: ucfirst($c['status'])) }}</p></div>
                            <button type="button" wire:click="retest({{ $c['id'] }})" wire:loading.attr="disabled" wire:target="retest({{ $c['id'] }})" class="rounded-lg bg-[rgb(var(--nx-surface-3))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">Test again</button>
                        </div>
                    @endforeach
                    @foreach (['telegram' => ['telegramToken', null], 'whatsapp' => ['waNumberId', 'wa'], 'instagram' => ['igAccountId', 'ig']] as $ch => [$first, $pfx])
                        @php($g = $guides[$ch])
                        <details class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]" @if ($ch === 'telegram') open @endif data-wizard-channel="{{ $ch }}">
                            <summary class="cursor-pointer select-none px-4 py-3 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $g['title'] }}</summary>
                            <div class="space-y-3 border-t border-[rgb(var(--nx-line))] p-4">
                                <img src="{{ asset('images/illustrations/bs-'.($ch === 'whatsapp' ? 'whatsapp' : $ch).'.svg') }}" alt="" width="240" height="180" class="mx-auto h-28 w-auto">
                                <div class="rounded-lg bg-[rgb(var(--nx-surface-2))] p-3 text-xs text-[rgb(var(--nx-text-2))]">
                                    <p class="font-semibold text-[rgb(var(--nx-text))]">Where do I get this?</p>
                                    <p class="mt-1">{{ $g['where'] }} <a href="{{ $g['link'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[rgb(var(--nx-teal-ink))] underline">Open the official page</a></p>
                                    <ol class="mt-2 list-decimal space-y-1 pl-4">@foreach ($g['steps'] as $line)<li>{{ $line }}</li>@endforeach</ol>
                                </div>
                                <form wire:submit="connect{{ ucfirst($ch) }}" class="space-y-2">
                                    @if ($ch === 'telegram')
                                        <input type="password" autocomplete="off" wire:model="telegramToken" placeholder="Bot token" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                    @else
                                        <input type="text" autocomplete="off" wire:model="{{ $pfx }}{{ $ch === 'whatsapp' ? 'NumberId' : 'AccountId' }}" placeholder="{{ $ch === 'whatsapp' ? 'Phone number ID' : 'Instagram account ID' }}" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                        <input type="password" autocomplete="off" wire:model="{{ $pfx }}Token" placeholder="Access token" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                        <input type="password" autocomplete="off" wire:model="{{ $pfx }}Secret" placeholder="App secret" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                    @endif
                                    <button type="submit" wire:loading.attr="disabled" wire:target="connect{{ ucfirst($ch) }}" class="ns-cta !w-auto px-6"><span wire:loading.remove wire:target="connect{{ ucfirst($ch) }}">Test and connect</span><span wire:loading wire:target="connect{{ ucfirst($ch) }}">Testing...</span></button>
                                </form>
                            </div>
                        </details>
                    @endforeach

                @elseif ($current['key'] === 'team')
                    <form wire:submit="inviteSeat" class="grid gap-2 sm:grid-cols-[1fr_9rem_auto]">
                        <input type="email" wire:model="inviteEmail" placeholder="colleague@example.com" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                        <select wire:model="inviteRole" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">@foreach ($roles as $r)<option value="{{ $r }}">{{ ucfirst($r) }}</option>@endforeach</select>
                        <button type="submit" wire:loading.attr="disabled" wire:target="inviteSeat" class="ns-cta !w-auto px-5"><span wire:loading.remove wire:target="inviteSeat">Invite</span><span wire:loading wire:target="inviteSeat">Inviting...</span></button>
                    </form>
                    @error('inviteEmail')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror

                @else
                    {{-- Steps that live on their own page: the guide, then a button to open it. --}}
                    @php($g = $guides[$current['key']] ?? [])
                    @if (! empty($g['steps']))
                        <ol class="list-decimal space-y-1 rounded-lg bg-[rgb(var(--nx-surface-2))] p-3 pl-7 text-xs text-[rgb(var(--nx-text-2))]">@foreach ($g['steps'] as $line)<li>{{ $line }}</li>@endforeach</ol>
                    @endif
                    @if ($current['page'])<a href="{{ $current['page'] }}" wire:navigate class="ns-cta !w-auto px-6">Open {{ $current['title'] }}</a>@endif
                @endif
            </div>
        @endif

        <x-slot:foot>
            <div class="flex items-center justify-between gap-2">
                <button type="button" wire:click="back" class="rounded-lg px-3 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]">Back</button>
                @if ($step !== 'done')
                    <div class="flex items-center gap-2">
                        @if ($current && $current['optional'])<button type="button" wire:click="next" class="rounded-lg px-3 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]">Skip</button>@endif
                        @if (! $current || $current['key'] !== 'profile')<button type="button" wire:click="next" class="ns-cta !w-auto px-6">Next</button>@endif
                    </div>
                @else
                    <button type="button" wire:click="close" class="ns-cta !w-auto px-6">Finish</button>
                @endif
            </div>
        </x-slot:foot>
    </x-nx.sheet>
@endif
</div>

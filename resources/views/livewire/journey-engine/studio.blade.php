{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Journey Studio</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">The rules behind the "Today" cards members see. You can switch a rule off, change who it is for, and rewrite its words. The data it looks at, where it links, and the reason it shows stay fixed, so a rule can never read private content or appear without a reason.</p>
    </div>

    @if ($saved)
        <p role="status" class="mb-4 flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-ok)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-ok))]"><x-icon name="check" class="h-4 w-4 shrink-0" /> {{ $saved }}</p>
    @endif
    @if ($problemsInCatalog)
        <p class="mb-4 rounded-xl border border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] px-4 py-3 text-sm text-[rgb(var(--nx-bad))]">A rule is invalid and will be skipped: {{ implode('; ', $problemsInCatalog) }}</p>
    @endif

    <div class="space-y-3">
        @foreach ($rules as $key => $r)
            @php($protective = in_array($r['shipped']['priority'], ['critical', 'important'], true))
            <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]" wire:key="rule-{{ $key }}">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 font-semibold text-[rgb(var(--nx-text))]">
                            {{ $r['name'] }}
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $protective ? 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' : 'bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]' }}">{{ $protective ? 'Protective alert' : 'Suggestion' }} · {{ $r['priority'] }}</span>
                        </p>
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">
                            For: {{ in_array('*', $r['intents'], true) ? 'everyone' : implode(', ', array_map(fn ($i) => $catalog[$i]['label'] ?? $i, $r['intents'])) }}
                            · Evidence shown: {{ implode(', ', $r['evidence_fields'] ?? []) ?: 'none' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" role="switch" aria-checked="{{ $r['enabled'] ? 'true' : 'false' }}" aria-label="Switch {{ $r['name'] }} {{ $r['enabled'] ? 'off' : 'on' }}"
                                wire:click="toggle('{{ $key }}')" wire:loading.attr="disabled" wire:target="toggle('{{ $key }}')" class="ns-toggle {{ $r['enabled'] ? 'is-on' : '' }}"><i></i></button>
                        <button type="button" wire:click="estimate('{{ $key }}')" wire:loading.attr="disabled" wire:target="estimate('{{ $key }}')" class="nx-btn nx-btn--ghost px-3 py-1 text-xs disabled:opacity-60">
                            <span wire:loading.remove wire:target="estimate('{{ $key }}')">Who would see this</span><span wire:loading wire:target="estimate('{{ $key }}')">Counting</span>
                        </button>
                        <button type="button" wire:click="edit('{{ $key }}')" class="nx-btn nx-btn--ghost px-3 py-1 text-xs">Edit</button>
                    </div>
                </div>
                @if (isset($audience[$key]))
                    <p class="border-t border-[rgb(var(--nx-line))] px-5 py-2 text-xs text-[rgb(var(--nx-text-2))]">About {{ $audience[$key]['matching'] }} of the last {{ $audience[$key]['sampled'] }} active members would see this right now (a count only, before daily limits and the holdout).</p>
                @endif

                @if ($editing === $key)
                    <form wire:submit="save" class="space-y-4 border-t border-[rgb(var(--nx-line))] px-5 py-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]" for="pr-{{ $key }}">Priority</label>
                            <select id="pr-{{ $key }}" wire:model="priority" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                @foreach (\App\Services\JourneyEngine\RuleCatalog::allowedPriorities($r['shipped']['priority']) as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $protective ? 'A safety alert always stays a safety alert.' : 'A suggestion can be made quieter, never louder than the suggestion limits.' }}</p>
                        </div>
                        <fieldset>
                            <legend class="mb-1 text-xs font-medium text-[rgb(var(--nx-text-2))]">Who it is for</legend>
                            <label class="mb-1 flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model.live="everyone"> Everyone</label>
                            @unless ($everyone)
                                <div class="grid gap-1 sm:grid-cols-2">
                                    @foreach ($catalog as $ik => $i)
                                        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="intents" value="{{ $ik }}"> {{ $i['label'] }}</label>
                                    @endforeach
                                </div>
                            @endunless
                        </fieldset>
                        @foreach (['title' => 'Title', 'body' => 'Message', 'cta' => 'Button', 'why' => 'Why am I seeing this?'] as $f => $label)
                            <div>
                                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]" for="cp-{{ $key }}-{{ $f }}">{{ $label }}</label>
                                <input id="cp-{{ $key }}-{{ $f }}" type="text" wire:model="copy.{{ $f }}" maxlength="{{ ['title' => 80, 'body' => 240, 'cta' => 32, 'why' => 240][$f] }}"
                                       placeholder="{{ __('journey_engine.cards.'.$r['copy'].'.'.$f) }}"
                                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                            </div>
                        @endforeach
                        <p class="text-[11px] text-[rgb(var(--nx-text-2))]">Leave a field empty to keep the shipped wording (shown in grey). Plain text only. You can use {{ implode(' ', array_map(fn ($f) => ':'.$f, $r['evidence_fields'] ?? [])) ?: 'no placeholders' }} to insert the member's own numbers.</p>
                        @if ($problems)
                            <ul role="alert" class="space-y-1 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-xs text-[rgb(var(--nx-bad))]">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
                        @endif
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="nx-btn nx-btn--primary disabled:opacity-60">
                                <span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving</span>
                            </button>
                            <button type="button" wire:click="cancel" class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline">Cancel</button>
                            <button type="button" wire:click="resetRule('{{ $key }}')" wire:confirm="Go back to the shipped rule? Your edits to it will be removed." class="ml-auto text-xs font-medium text-[rgb(var(--nx-text-2))] underline hover:text-[rgb(var(--nx-bad))]">Reset to shipped</button>
                        </div>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
</div>

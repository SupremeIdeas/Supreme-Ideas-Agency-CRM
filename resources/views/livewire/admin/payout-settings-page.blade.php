{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php
    $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
@endphp
<div class="mx-auto max-w-4xl pb-48 lg:pb-24">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Payout settings</h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Every payout rule in one place. Each shows its default; "Reset" puts it back. Changes are logged and your other super admins are told.</p>
        </div>
        <a href="{{ route('admin.payouts', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Back to Payouts</a>
    </div>

    <div class="mt-4">
        <label for="ps-search" class="sr-only">Search settings</label>
        <input id="ps-search" type="search" wire:model.live.debounce.250ms="search" placeholder="Search settings (e.g. timeout, cap, country)" class="{{ $inp }}">
    </div>

    @if ($saved)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $saved }}</div>
    @endif

    @forelse ($groups as $id => $g)
        <section class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm" aria-labelledby="ps-{{ $id }}">
            <h2 id="ps-{{ $id }}" class="text-base font-bold text-[rgb(var(--nx-text))]">{{ $g['title'] }}</h2>
            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $g['blurb'] }}</p>

            <div class="mt-4 divide-y divide-[rgb(var(--nx-line))]">
                @foreach ($g['fields'] as $f)
                    @php
                        $slot = \App\Livewire\Admin\PayoutSettingsPage::slot($f['key']);
                        $custom = \App\Support\PayoutSettingsSchema::isCustom($f);
                        $dirtyNow = in_array($f['key'], $dirtyKeys, true);
                    @endphp
                    <div class="grid gap-2 py-3 sm:grid-cols-[1fr_220px] sm:items-start" wire:key="f-{{ $slot }}">
                        <div>
                            <label for="f-{{ $slot }}" class="flex flex-wrap items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                                {{ $f['label'] }}
                                @if ($f['danger'] ?? false)<span class="rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-bad))]">Affects money flow</span>@endif
                                @if ($custom)<span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-warn))]">Changed from default</span>@endif
                                @if ($dirtyNow)<span class="rounded-full bg-[rgb(var(--nx-teal)/0.14)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-teal-ink))]">Unsaved</span>@endif
                            </label>
                            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $f['help'] }}</p>
                            <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Default: {{ is_bool($f['default']) ? ($f['default'] ? 'On' : 'Off') : ($f['default'] === '' ? 'none' : $f['default']) }}@if ($f['unit']) {{ $f['unit'] }}@endif
                                @if ($custom) · <button type="button" wire:click="resetField('{{ $slot }}')" wire:confirm="Reset &quot;{{ $f['label'] }}&quot; to its default?" class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Reset</button>@endif</p>
                            @error('values.'.$slot)<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($f['type'] === 'bool')
                                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[rgb(var(--nx-text))]">
                                    <input id="f-{{ $slot }}" type="checkbox" wire:model.live="values.{{ $slot }}" class="h-5 w-9 cursor-pointer rounded-full">
                                    <span>{{ ($values[$slot] ?? false) ? 'On' : 'Off' }}</span>
                                </label>
                            @elseif ($f['type'] === 'select')
                                <select id="f-{{ $slot }}" wire:model.live="values.{{ $slot }}" class="{{ $inp }}">
                                    @foreach ($f['options'] as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                                </select>
                            @elseif ($f['type'] === 'int' || $f['type'] === 'float')
                                <input id="f-{{ $slot }}" type="number" step="{{ $f['type'] === 'int' ? 1 : 'any' }}" min="{{ $f['min'] }}" max="{{ $f['max'] }}" wire:model.live.debounce.400ms="values.{{ $slot }}" class="{{ $inp }}">
                                @if ($f['unit'])<span class="shrink-0 text-xs text-[rgb(var(--nx-text-2))]">{{ $f['unit'] }}</span>@endif
                            @elseif ($f['type'] === 'json')
                                <textarea id="f-{{ $slot }}" rows="2" wire:model.blur="values.{{ $slot }}" class="{{ $inp }} font-mono text-xs"></textarea>
                            @else
                                <input id="f-{{ $slot }}" type="text" wire:model.blur="values.{{ $slot }}" class="{{ $inp }}" placeholder="{{ $f['type'] === 'csv' ? 'e.g. KP, IR' : '' }}">
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <p class="mt-6 rounded-xl border border-dashed border-[rgb(var(--nx-line-strong))] p-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No settings match "{{ $search }}".</p>
    @endforelse

    {{-- Sticky save bar --}}
    <div class="fixed inset-x-0 bottom-[calc(6rem+env(safe-area-inset-bottom))] z-30 border-t border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface)/0.96)] p-3 backdrop-blur lg:bottom-0 lg:left-64">
        <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-[rgb(var(--nx-text-2))]">
                @if (count($dirtyKeys))
                    <span class="font-semibold">{{ count($dirtyKeys) }}</span> unsaved change{{ count($dirtyKeys) === 1 ? '' : 's' }}
                @else
                    No unsaved changes
                @endif
                @if ($needsConfirm)
                    <label class="mt-1 flex items-center gap-2 text-xs text-[rgb(var(--nx-bad))]">
                        <input type="checkbox" wire:model.live="confirmed" class="rounded"> I understand these changes affect how money moves.
                    </label>
                    @error('confirmed')<span class="text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</span>@enderror
                @endif
            </div>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" @disabled(! count($dirtyKeys))
                class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-50">
                <x-icon name="check" wire:loading.remove wire:target="save" class="h-4 w-4" />
                <x-ui.spinner wire:loading wire:target="save" class="h-4 w-4" />
                Save changes
            </button>
        </div>
    </div>
</div>

{{-- nx:converted (skin tokens; admin tool) --}}
@php($input = 'rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm text-[rgb(var(--nx-text))]')
<div class="mx-auto max-w-3xl">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">NaaraBrain</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">The assistant at <code>/brain</code> and its floating button. Needs the Anthropic key (Admin > Providers).</p>
        </div>
        <span class="flex gap-4"><a href="{{ route('admin.brain.ideas') }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] underline">Ideas library</a><a href="{{ route('admin.ai-costs') }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] underline">See what it costs</a></span>
    </div>

    <form wire:submit="save" class="space-y-6">
        <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))]">Switch and limits</h2>
            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="on" class="h-4 w-4"> NaaraBrain is on for members</label>
            <p class="mt-1 ms-6 text-xs text-[rgb(var(--nx-text-2))]">Off: <code>/brain</code> shows the Coming soon sheet, the widget and the menu entry disappear.</p>
        </section>

        <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">Access by plan</h2>
            <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">NaaraBrain follows Naara Pro. Members on a plan get the Naara Pro numbers below; a plan can set its own (a bigger plan, more questions and deeper answers) in <a href="{{ route('admin.dashboard') }}" class="underline">Brand directory &gt; the plan</a>. Anyone without a plan is "free". A lapsed plan drops back to free; nothing is deleted. Admins are never limited.</p>
            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="freeAccess" class="h-4 w-4"> Free members can use NaaraBrain</label>
            <p class="mt-1 ms-6 text-xs text-[rgb(var(--nx-text-2))]">Off: NaaraBrain is for Naara Pro only, and free members are invited to upgrade.</p>
            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Free members</p>
                    <label class="block text-sm text-[rgb(var(--nx-text))]">Questions per day <input type="number" min="1" max="1000" wire:model="daily" class="{{ $input }} ms-2 w-24"></label>
                    @error('daily')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach (['quick' => 'Quick', 'balanced' => 'Balanced', 'deep' => 'Deep'] as $dk => $dl)
                            <label class="flex items-center gap-1.5 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="freeDepths" value="{{ $dk }}" class="h-4 w-4"> {{ $dl }}</label>
                        @endforeach
                    </div>
                    @error('freeDepths')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">Pick at least one depth.</p>@enderror
                </div>
                <div>
                    <p class="mb-2 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Naara Pro (default for every plan)</p>
                    <label class="block text-sm text-[rgb(var(--nx-text))]">Questions per day <input type="number" min="1" max="100000" wire:model="proDaily" class="{{ $input }} ms-2 w-24"></label>
                    @error('proDaily')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach (['quick' => 'Quick', 'balanced' => 'Balanced', 'deep' => 'Deep'] as $dk => $dl)
                            <label class="flex items-center gap-1.5 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="proDepths" value="{{ $dk }}" class="h-4 w-4"> {{ $dl }}</label>
                        @endforeach
                    </div>
                    @error('proDepths')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">Pick at least one depth.</p>@enderror
                </div>
            </div>
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Deeper depths use a bigger model and cost more per answer: check the AI costs page before opening them to free members.</p>
        </section>

        <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">Floating button</h2>
            <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">A small NaaraBrain button above the bottom nav, so members reach it from where they already are. It is never shown on support, checkout, admin or inside NaaraBrain itself, and members can hide it for a while.</p>
            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model.live="widget" class="h-4 w-4"> Show the floating button</label>

            <div class="mt-5 grid gap-6 sm:grid-cols-[1fr_190px]" @class(['opacity-50' => ! $widget])>
                <div class="space-y-5">
                    <fieldset>
                        <legend class="mb-2 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Side</legend>
                        <div class="inline-flex gap-1 rounded-xl bg-[rgb(var(--nx-surface-3))] p-1" role="radiogroup" aria-label="Side">
                            @foreach (['left' => 'Left', 'right' => 'Right'] as $k => $l)
                                <label class="cursor-pointer rounded-lg px-4 py-1.5 text-sm font-semibold {{ $side === $k ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'text-[rgb(var(--nx-text))]' }}">
                                    <input type="radio" class="sr-only" wire:model.live="side" value="{{ $k }}"> {{ $l }}</label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Right already carries the Wizard, My Journey and WhatsApp, so NaaraBrain stacks above them there. Left is the default.</p>
                    </fieldset>
                    <label class="block text-sm text-[rgb(var(--nx-text))]">Height above the bottom edge on phones: <b>{{ $offset }}px</b>
                        <input type="range" min="60" max="320" step="4" wire:model.live="offset" class="mt-2 block w-full" aria-label="Height above the bottom edge">
                        <span class="text-xs text-[rgb(var(--nx-text-2))]">About 96px sits just above the bottom nav.</span></label>
                    @error('offset')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                    <fieldset>
                        <legend class="mb-2 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Show it in these sections</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($sectionList as $key => $s)
                                <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="sections" value="{{ $key }}" class="h-4 w-4"> {{ $s['label'] }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="block text-sm text-[rgb(var(--nx-text))]">When a member hides it, keep it hidden for
                        <input type="number" min="1" max="720" wire:model="hours" class="{{ $input }} mx-1 w-20"> hours</label>
                    @error('hours')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                </div>

                {{-- Live preview of the phone: bottom nav, and the button where it will sit. --}}
                <div class="mx-auto w-[170px]" aria-hidden="true">
                    <div class="relative h-[300px] overflow-hidden rounded-[26px] bg-[rgb(var(--nx-canvas))] shadow-[inset_0_0_0_2px_rgb(var(--nx-line-strong))]">
                        <div class="absolute inset-x-0 bottom-0 flex h-11 items-center justify-around bg-[rgb(var(--nx-surface))] shadow-[inset_0_1px_0_rgb(var(--nx-line))]"><i class="h-2 w-2 rounded-full bg-[rgb(var(--nx-text-2))]"></i><i class="h-2 w-2 rounded-full bg-[rgb(var(--nx-text-2))]"></i><i class="h-2 w-2 rounded-full bg-[rgb(var(--nx-cta-b))]"></i><i class="h-2 w-2 rounded-full bg-[rgb(var(--nx-text-2))]"></i></div>
                        @php($pv = min(300 - 40, (int) round($offset * 300 / 844)) + 0)
                        <span class="absolute flex h-9 w-9 items-center justify-center rounded-full bg-[rgb(var(--nx-surface))] shadow-[inset_0_0_0_1px_rgb(var(--nx-line)),0_4px_10px_rgb(0_0_0/.3)] transition-all {{ $side === 'right' ? 'right-2' : 'left-2' }}" style="bottom: {{ $pv + ($side === 'right' ? 62 : 0) }}px"><img src="{{ \App\Support\Brain\BrainSettings::logo('mark', true) }}" alt="" class="h-6 w-6 object-contain"></span>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">Save</button>
            @if ($saved)<span class="text-sm font-semibold text-[rgb(var(--nx-ok))]" role="status">{{ $saved }}</span>@endif
        </div>
    </form>
</div>

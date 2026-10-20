{{-- nx:converted (skin tokens; admin tool) --}}
@php($input = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-sm')
<details class="mt-3 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3">
    <summary class="cursor-pointer text-xs font-bold text-[rgb(var(--nx-text))]">NaaraBusiness Suite and Pro perks <span class="font-normal text-[rgb(var(--nx-text-2))]">({{ $count }} Suite {{ \Illuminate\Support\Str::plural('feature', $count) }} included)</span></summary>
    <div class="mt-3 space-y-4">
        <div>
            <p class="mb-2 text-xs font-semibold text-[rgb(var(--nx-text))]">Business Suite features this plan includes</p>
            @if ($live === [])
                <p class="text-xs text-[rgb(var(--nx-text-2))]">The Business Suite is off, so nothing can be assigned yet. Switch it on (and the modules you want) in Admin &gt; Business Suite.</p>
            @endif
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($catalogue as $key => $f)
                    @php($on = in_array($key, $live, true))
                    <label class="flex items-start gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-2 text-xs {{ $on ? '' : 'opacity-60' }}">
                        <input type="checkbox" value="{{ $key }}" wire:model="features" @disabled(! $on) class="mt-0.5">
                        <span><b class="text-[rgb(var(--nx-text))]">{{ $f['label'] }}</b>
                            @if (! $on)<span class="ml-1 rounded-full bg-[rgb(var(--nx-surface-3))] px-1.5 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-text-2))]">{{ in_array($key, $pausedStored, true) ? 'On this plan, paused: module is off' : 'Module is off' }}</span>@endif
                            <br><span class="text-[rgb(var(--nx-text-2))]">{{ $f['blurb'] }}</span></span>
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Ticking a feature also includes the ones it stands on (every feature needs the Team workspace; Call intelligence needs Call recording).</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-start gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-2 text-xs">
                <input type="checkbox" wire:model="premiumSkins" class="mt-0.5">
                <span><b class="text-[rgb(var(--nx-text))]">Premium dashboard skins</b><br><span class="text-[rgb(var(--nx-text-2))]">Members on this plan can use skins marked "pro" (only enforced while "Reserve premium skins for Naara Pro" is on in Business Suite settings).</span></span>
            </label>
            <label class="rounded-lg border border-[rgb(var(--nx-line))] p-2 text-xs">
                <b class="text-[rgb(var(--nx-text))]">Monthly NaaraCredit refill</b>
                <input type="number" min="0" max="100000" wire:model="monthlyCredits" class="{{ $input }} mt-1" placeholder="0 = none">
                <span class="text-[rgb(var(--nx-text-2))]">Credited to the plan owner every billing month while the plan is active.</span>
                @error('monthlyCredits')<span class="text-[rgb(var(--nx-bad))]"> {{ $message }}</span>@enderror
            </label>
        </div>

        <div>
            @if ($brainOn)
    <div class="mb-5 rounded-xl border border-[rgb(var(--nx-line))] p-4">
        <p class="mb-1 text-xs font-semibold text-[rgb(var(--nx-text))]">NaaraBrain on this plan <span class="font-normal text-[rgb(var(--nx-text-2))]">(blank = the Naara Pro defaults in Admin &gt; NaaraBrain)</span></p>
        <label class="block text-xs text-[rgb(var(--nx-text-2))]">Questions per day
            <input type="number" min="0" wire:model="brainDaily" class="{{ $input }} mt-1" placeholder="default" title="A bigger plan can mean more questions a day"></label>
        <fieldset class="mt-3"><legend class="mb-1 text-xs text-[rgb(var(--nx-text-2))]">Depths this plan opens (none ticked = the Pro default)</legend>
            <div class="flex flex-wrap gap-3">
                @foreach (['quick' => 'Quick', 'balanced' => 'Balanced', 'deep' => 'Deep'] as $dk => $dl)
                    <label class="flex items-center gap-1.5 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="brainDepths" value="{{ $dk }}" class="h-4 w-4"> {{ $dl }}</label>
                @endforeach
            </div>
        </fieldset>
        @error('brainDaily')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        <label class="mt-3 block text-xs text-[rgb(var(--nx-text-2))]">Inbox assistant actions per month (if this plan includes the NaaraBrain assistant)
            <input type="number" min="0" wire:model="brainBusinessActions" class="{{ $input }} mt-1" placeholder="platform default" title="Draft replies, summaries and follow-up lists a workspace on this plan may run each month"></label>
        @error('brainBusinessActions')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
    </div>
@endif
<p class="mb-1 text-xs font-semibold text-[rgb(var(--nx-text))]">Workspace limits for this plan <span class="font-normal text-[rgb(var(--nx-text-2))]">(blank = platform default; a plan can only raise a limit)</span></p>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($limitDefs as $name => $d)
                    <label class="text-xs text-[rgb(var(--nx-text-2))]">{{ $d['label'] }}
                        <input type="number" min="0" wire:model="limits.{{ $name }}" class="{{ $input }} mt-1" placeholder="default" title="{{ $d['blurb'] }}">
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save Suite and perks</button>
            @if ($saved)<span class="text-xs font-semibold text-[rgb(var(--nx-ok))]" role="status">{{ $saved }}</span>@endif
        </div>
    </div>

    @php($previewPlan = \App\Models\BrandSubscriptionPlan::find($planId))
    @if ($previewPlan)
        <div class="mt-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3" data-plan-preview>
            <p class="text-xs font-semibold text-[rgb(var(--nx-text))]">What customers see for this plan (saved settings, from the one Naara Pro catalogue)</p>
            <ul class="mt-2 grid gap-1 text-xs text-[rgb(var(--nx-text-2))] sm:grid-cols-2">
                @foreach (\App\Support\Business\NaaraProCatalogue::bullets($previewPlan) as $line)<li>{{ $line }}</li>@endforeach
            </ul>
        </div>
    @endif
</details>

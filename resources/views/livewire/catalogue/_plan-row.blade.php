{{-- One plan as a clean list row on the skin system: data + validity (+ Popular) and the plan name on the left, price on the right; the
     whole row opens the plan-detail view. Expects: $plan, $fmt. A div (not a button) because the tooltip trigger is itself a button. --}}
@php($price = $fmt((float) $plan->final_retail_usd))
<div wire:key="plan-{{ $plan->id }}" wire:click="openPlan({{ $plan->id }})" role="button" tabindex="0" @keydown.enter="$wire.openPlan({{ $plan->id }})"
     class="ns-row ns-ring ns-plan">
    <x-nx.icon name="bars" />
    <span class="ns-row__label" style="min-width:0">
        <span style="display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px">
            <b style="font-size:18px;font-weight:600">{{ $plan->data_mb ? rtrim(rtrim(number_format($plan->data_mb / 1024, 1), '0'), '.').' GB' : 'Unlimited' }}</b>
            <span class="ns-small">· {{ $plan->validity_days ? $plan->validity_days.' days' : 'flexible' }}</span>
            @if ($plan->is_featured)<x-nx.pill variant="best"><x-nx.icon name="zap" /> Popular</x-nx.pill>@endif
        </span>
        <span style="display:flex;align-items:center;gap:6px;margin-top:2px">
            <small class="ns-small" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ \App\Support\EsimCatalogue::cleanPlanName($plan->name) }}</small>
            @if ($plan->display_tooltip)
                <span x-data="{ open: false }" style="position:relative;flex:none" @click.stop>
                    <button type="button" @click="open = !open" @click.outside="open = false" aria-label="What is this plan?" class="ns-plan__info"><x-nx.icon name="info" /></button>
                    <span x-show="open" x-cloak x-transition class="ns-plan__tip" role="tooltip">{{ $plan->display_tooltip }}</span>
                </span>
            @endif
        </span>
    </span>
    <span class="ns-row__price" style="text-align:end">
        {{ $price['usd'] }}
        @if ($price['local'])<small class="ns-small" style="display:block;font-weight:400">≈ {{ $price['local'] }}</small>@endif
    </span>
    <x-nx.icon name="chevron-right" class="ns-chevron" />
</div>

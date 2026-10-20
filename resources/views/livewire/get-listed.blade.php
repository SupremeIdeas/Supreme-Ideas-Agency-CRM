{{-- List your brand: a business-facing marketing page on the skin system (S3 Batch 6). The hero is the same dashboard-home treatment as The Hunt (copy left, photo dissolving in from the top-right). --}}
<div>
<x-nx.page class="ns-pg ns-pg--wide">
    @if ($suite)
    <x-nx.hero kicker="NaaraBusiness Suite" first="Get found." rest="Stay reachable." size="md"
               :desc="'Get listed on '.\App\Support\BrandSettings::name().' brand directory lookups, with the verified Naara Pro seal and a guaranteed number of real follows every month. Then run your business from one workspace: shared inbox, call flows, team roles and automations, depending on your plan.'"
               :light="\App\Support\HuntHeroBackground::listedLight()" :dark="\App\Support\HuntHeroBackground::listedDark()" />
    @else
    <x-nx.hero kicker="For businesses" first="Grow your" rest="following." size="md"
               :desc="'List your brand on '.\App\Support\BrandSettings::name().'. Real users follow brands to earn credit: genuine engagement from people already motivated to follow, not passive impressions. Real follows on the exact handles you list, plus (on eligible plans) real watch-time on a featured video.'"
               :light="\App\Support\HuntHeroBackground::listedLight()" :dark="\App\Support\HuntHeroBackground::listedDark()" />
    @endif

    @if ($error)<div class="ns-pg__err" role="alert" style="margin-top:0">{{ $error }}</div>@endif

    @if ($alreadyListed)
        <div class="ns-pg__callout" style="margin-bottom:20px"><x-nx.icon name="info" /><div><b>You already have a listing.</b><p><a href="{{ route('brand.manage') }}" wire:navigate class="ns-linkink" style="font-weight:600;text-decoration:underline">Manage it here</a>.</p></div></div>
    @endif

    {{-- How it works: the value story in structure, not just prose. --}}
    <div class="ns-pg__cols3" style="margin-bottom:32px">
        <div class="ns-pg__card ns-ring" style="margin-top:0"><span class="ns-tile"><x-nx.icon name="users" /></span><h3 class="ns-pg__h2" style="margin-top:12px">Real people, already motivated</h3><p class="ns-pg__hint">{{ \App\Support\BrandSettings::name() }} users follow your handles to earn NaaraCredits: genuine accounts, genuine intent to follow.</p></div>
        <div class="ns-pg__card ns-ring" style="margin-top:0"><span class="ns-tile"><x-nx.icon name="pin" /></span><h3 class="ns-pg__h2" style="margin-top:12px">A guaranteed monthly target</h3><p class="ns-pg__hint">Every plan guarantees a real follower count per handle per month. Not a hope, a number you can hold us to.</p></div>
        <div class="ns-pg__card ns-ring" style="margin-top:0"><span class="ns-tile"><x-nx.icon name="bars" /></span><h3 class="ns-pg__h2" style="margin-top:12px">Short a target? You get boosted</h3><p class="ns-pg__hint">Miss the guarantee and your listing gets priority placement next cycle, automatically, until it catches up.</p></div>
    </div>

    {{-- Plan comparison: live from the plans table, never a hardcoded list. --}}
    @php($mostPopularIndex = $plans->count() > 2 ? 1 : null)
    <div class="ns-pg__plans">
        @foreach ($plans as $i => $plan)
            @php($cpf = $plan->costPerGuaranteedFollower())
            @php($popular = $i === $mostPopularIndex)
            <div class="ns-pg__plan ns-ring {{ $popular ? 'is-pop' : '' }}">
                @if ($popular)<x-nx.pill variant="gold" class="ns-pg__badge">Most popular</x-nx.pill>@endif
                <h3 class="ns-pg__h2" style="font-size:18px">{{ $plan->name }}</h3>
                <p class="ns-pg__price">${{ number_format($plan->price_usd_per_month, 0) }}<small>/mo</small></p>
                <ul class="ns-pg__ticks" data-pro-bullets>
                    @foreach (\App\Support\Business\NaaraProCatalogue::bullets($plan) as $line)
                        <li><x-nx.icon name="check" /> <span>{{ $line }}</span></li>
                    @endforeach
                </ul>
                @if ($cpf !== null)<p class="ns-small" style="margin:12px 0 0">≈ ${{ number_format($cpf, 3) }} per guaranteed follower</p>@endif
                <button type="button" wire:click="choose({{ $plan->id }})" wire:loading.attr="disabled" wire:target="choose({{ $plan->id }})" class="ns-cta {{ $popular ? '' : 'ns-cta--ghost' }}" style="margin-top:18px;height:48px;font-size:16px">
                    <span wire:loading.remove wire:target="choose({{ $plan->id }})" class="ns-cta__label">Choose {{ $plan->name }}</span>
                    <span wire:loading wire:target="choose({{ $plan->id }})" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> One moment…</span>
                </button>
                @if (($trial['available'] ?? false))
                    <button type="button" wire:click="askTrial({{ $plan->id }})" wire:loading.attr="disabled" wire:target="askTrial({{ $plan->id }})" class="ns-linkink" style="margin-top:12px;font-weight:600;text-decoration:underline;width:100%;text-align:center">Try free for {{ $trial['days'] }} days</button>
                @endif
            </div>
        @endforeach
    </div>

    <details class="ns-pg__card ns-ring" style="margin:24px auto 0;max-width:64rem" data-pro-compare-wrap>
        <summary style="cursor:pointer;font-weight:700;color:rgb(var(--nx-text))">Compare everything Naara Pro includes</summary>
        <div style="margin-top:14px"><x-naara-pro.compare :plans="$plans" compact /></div>
    </details>

    @if ($trialPlanId && ($trial['available'] ?? false))
        @php($tp = $plans->firstWhere('id', $trialPlanId))
        <div class="ns-pg__card ns-ring" style="margin:24px auto 0;max-width:42rem" role="group" aria-labelledby="trial-h">
            <h2 id="trial-h" class="ns-pg__h2" style="display:flex;align-items:center;gap:8px"><x-nx.icon name="sparkles" /> Start your {{ $trial['days'] }}-day free trial of {{ $tp?->name }}</h2>
            <p class="ns-sub" style="margin-top:8px">You pay nothing today. When the {{ $trial['days'] }} days end, <strong>${{ number_format((float) $tp?->price_usd_per_month, 2) }}</strong> is taken from your wallet automatically and {{ $tp?->name }} continues monthly. If your wallet cannot cover it, nothing is charged and your listing pauses until you top up. Cancel before then and you are never charged. One trial per person.</p>
            <label class="ns-pg__check" style="display:flex;gap:10px;align-items:flex-start;margin-top:14px"><input type="checkbox" wire:model.live="trialAccept" style="margin-top:3px"><span>I understand my wallet will be charged ${{ number_format((float) $tp?->price_usd_per_month, 2) }} when the trial ends unless I cancel.</span></label>
            <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
                <button type="button" wire:click="startTrial" wire:loading.attr="disabled" wire:target="startTrial" @disabled(! $trialAccept) class="ns-cta" style="height:48px;font-size:16px;min-width:12rem">
                    <span wire:loading.remove wire:target="startTrial" class="ns-cta__label">Start free trial</span>
                    <span wire:loading wire:target="startTrial" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Starting…</span>
                </button>
                <button type="button" wire:click="$set('trialPlanId', null)" class="ns-cta ns-cta--ghost" style="height:48px">Not now</button>
            </div>
        </div>
    @elseif ($trial && $trial['blocked'])
        <p class="ns-small" style="text-align:center;margin-top:16px">{{ $trial['blocked'] }}</p>
    @endif

    @if ($suite)
        <p class="ns-sub" style="text-align:center;margin-top:20px"><a href="{{ route('business') }}" wire:navigate class="ns-linkink" style="font-weight:600;text-decoration:underline">See everything NaaraBusiness Suite and Naara Pro give you</a></p>
    @endif

    {{-- The guarantee, explained honestly: an accountability statement, not fine print. --}}
    <div class="ns-pg__card ns-ring" style="margin:32px auto 0;max-width:42rem">
        <h2 class="ns-pg__h2" style="display:flex;align-items:center;gap:8px"><x-nx.icon name="shield" /> Our follower guarantee</h2>
        <p class="ns-sub" style="margin-top:8px">If a handle doesn't hit its guaranteed follows in a month, your listing gets <strong>boosted priority placement</strong> the next cycle, automatically, until it catches up. This is a real, accountable system, not a flat fee for a static listing. The charge renews monthly from your wallet; you can pause or cancel anytime.</p>
    </div>
</x-nx.page>
</div>

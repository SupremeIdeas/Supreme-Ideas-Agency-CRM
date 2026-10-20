{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    @php($input = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-sm')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Brand directory</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Subscription plans and the self-service brand listings. Admin-placed brands (via Social Hunt) are always featured and sit above these.</p>
    </div>

    @if ($saved)<div class="mb-4 rounded-lg border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] px-4 py-2.5 text-sm text-[rgb(var(--nx-ok))]">{{ $saved }}</div>@endif

    {{-- Plans --}}
    <section class="mb-8 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))]">Subscription plans</h2>
        <div class="space-y-3">
            @foreach ($planModels as $p)
                <div wire:key="plan-{{ $p->id }}" class="rounded-lg border border-[rgb(var(--nx-line))] p-3">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-7">
                        <input wire:model="plans.{{ $p->id }}.name" class="{{ $input }} sm:col-span-2" placeholder="Name">
                        <input wire:model="plans.{{ $p->id }}.price_usd_per_month" type="number" step="1" class="{{ $input }}" placeholder="$/mo">
                        <input wire:model="plans.{{ $p->id }}.handles_included" type="number" class="{{ $input }}" placeholder="Handles">
                        <input wire:model="plans.{{ $p->id }}.guaranteed_followers_per_handle_per_month" type="number" class="{{ $input }}" placeholder="Guar./handle">
                        <input wire:model="plans.{{ $p->id }}.video_previews_allowed" type="number" class="{{ $input }}" placeholder="Videos">
                        <input wire:model="plans.{{ $p->id }}.credit_reward_per_follow" type="number" step="0.5" class="{{ $input }}" placeholder="Credits/follow" title="NaaraCredits earned per handle-follow for brands on this plan">
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <button wire:click="savePlan({{ $p->id }})" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save</button>
                        <button wire:click="togglePlan({{ $p->id }})" class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $p->is_active ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">{{ $p->is_active ? 'Active' : 'Archived' }}</button>
                    </div>
                    {{-- NaaraBusiness Suite features + Pro perks for this plan (master only: the component does not exist in a white-label install). --}}
                    @if (class_exists(\App\Livewire\Admin\BusinessPlanCapabilities::class) && \App\Support\FeatureEntitlements::isMaster())
                        <livewire:admin.business-plan-capabilities :plan-id="$p->id" :key="'caps-'.$p->id" />
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-[rgb(var(--nx-line))] pt-4 sm:grid-cols-7">
            <input wire:model="newPlan.name" class="{{ $input }} sm:col-span-2" placeholder="New plan name">
            <input wire:model="newPlan.price_usd_per_month" type="number" class="{{ $input }}" placeholder="$/mo">
            <input wire:model="newPlan.handles_included" type="number" class="{{ $input }}" placeholder="Handles">
            <input wire:model="newPlan.guaranteed_followers_per_handle_per_month" type="number" class="{{ $input }}" placeholder="Guar./handle">
            <input wire:model="newPlan.video_previews_allowed" type="number" class="{{ $input }}" placeholder="Videos">
            <input wire:model="newPlan.credit_reward_per_follow" type="number" step="0.5" class="{{ $input }}" placeholder="Credits/follow">
            <button wire:click="addPlan" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))] sm:col-span-7">Add plan</button>
        </div>
    </section>

    {{-- Grant Naara Pro --}}
    <section class="mb-8 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-bold text-[rgb(var(--nx-text))]"><x-naara-pro-badge size="md" /> Grant Naara Pro</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Let a member list their brand without paying and earn the Naara Pro verified badge on their brand profile. The plan you pick sets their handle and video limits. Members who subscribe to list their brand get the badge automatically.</p>
        <form wire:submit="grantPro" class="grid gap-2 sm:grid-cols-7">
            <div class="sm:col-span-2">
                <input wire:model="grantEmail" type="email" autocomplete="off" class="{{ $input }}" placeholder="Member email">
                @error('grantEmail')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
            </div>
            <div>
                <select wire:model="grantPlanId" class="{{ $input }}">
                    <option value="">Plan…</option>
                    @foreach ($planModels->where('is_active', true) as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('grantPlanId')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
            </div>
            <select wire:model="grantTerm" class="{{ $input }}" aria-label="Term"><option value="lifetime">Lifetime</option><option value="1y">1 year</option></select>
            <input wire:model="grantNote" class="{{ $input }} sm:col-span-2" placeholder="Note (optional, e.g. partner deal)" maxlength="191">
            <button type="submit" wire:loading.attr="disabled" wire:target="grantPro" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="grantPro">Grant Pro</span><span wire:loading wire:target="grantPro">Granting…</span>
            </button>
        </form>
    </section>

    {{-- Self-service brands --}}
    <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))]">Self-service listings</h2>
        <div class="space-y-2">
            @forelse ($brands as $b)
                <div wire:key="brand-{{ $b->id }}" class="flex items-center gap-3 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1.5 truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $b->brand_name }} @if ($b->isNaaraPro())<x-naara-pro-badge size="sm" />@endif <span class="text-xs text-[rgb(var(--nx-text-2))]">· {{ $b->owner?->email }}</span></p>
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $b->plan?->name ?? '—' }} · {{ ucwords(str_replace('_',' ',$b->listing_status)) }}@if (class_exists(\App\Services\Brands\BrandVisibility::class)) · <span title="{{ \App\Services\Brands\BrandVisibility::message($b) }}">{{ str_replace('_', ' ', \App\Services\Brands\BrandVisibility::reason($b)) }}</span>@endif · priority {{ $b->priority_score }}
                            @if ($b->subscription) · next {{ $b->subscription->next_billing_at?->format('d M') }} · {{ $b->subscription->status }}@endif
                            @if ($b->hasProGrant()) · Pro granted {{ $b->pro_granted_at->format('d M Y') }} · {{ $b->pro_grant_expires_at ? 'until '.$b->pro_grant_expires_at->format('d M Y') : 'lifetime' }}@if ($b->pro_grant_note) ({{ $b->pro_grant_note }})@endif @endif
                        </p>
                    </div>
                    @if ($b->hasProGrant())
                        <button wire:click="revokePro({{ $b->id }})" wire:confirm="Revoke Naara Pro? The badge goes, and the listing is switched off unless they pay." class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Revoke Pro</button>
                    @endif
                    @if ($b->listing_status === 'disabled')
                        <button wire:click="restoreBrand({{ $b->id }})" class="rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-ok))]">Restore</button>
                    @else
                        <button wire:click="suspendBrand({{ $b->id }})" wire:confirm="Suspend this listing (policy override)?" class="rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-bad))]">Suspend</button>
                    @endif
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No self-service brands yet.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $brands->links() }}</div>
    </section>
</div>

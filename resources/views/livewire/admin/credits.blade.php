{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">NaaraCredits</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">The loyalty currency and rewards economy. Tune it so it always stays profitable — the notes below each field explain how.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] p-3 text-sm text-[rgb(var(--nx-teal-ink))]">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{-- Core economy --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <label class="flex items-start justify-between gap-4">
                <span>
                    <span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Rewards programme</span>
                    <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">Turn the whole NaaraCredits system on or off.</span>
                </span>
                <x-ui.switch wire:model="enabled" label="Rewards programme" class="mt-1" />
            </label>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Credits per $1</label>
                    <input wire:model="per_usd" type="number" min="1" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">The exchange rate. Default 100 credits = $1. Higher = credits feel “bigger” but are worth less each.</p>
                    @error('per_usd') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Max % of a purchase payable by credits</label>
                    <input wire:model="max_redeem_pct" type="number" min="0" max="100" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Credits can only cover this share of any order — you always collect real money on top, and the price can never fall below wholesale + profit.</p>
                    @error('max_redeem_pct') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        {{-- Earn amounts --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <h2 class="mb-4 text-sm font-semibold text-[rgb(var(--nx-text))]">How users earn</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Sign-up bonus (credits)</label>
                    <input wire:model="signup_bonus" type="number" min="0" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Granted once, on registration. 0 = off.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">First-purchase bonus (credits)</label>
                    <input wire:model="first_purchase_bonus" type="number" min="0" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Granted once, after the first eSIM or number order.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Daily check-in (credits)</label>
                    <input wire:model="checkin_daily" type="number" min="0" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">A small daily habit reward. 0 = off.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Check-in cooldown (hours)</label>
                    <input wire:model="checkin_cooldown_hours" type="number" min="1" max="168" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">How long between check-ins. 24 = once a day.</p>
                </div>
            </div>
        </section>

        {{-- Rewarded ads --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <label class="flex items-start justify-between gap-4">
                <span>
                    <span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Watch &amp; earn (rewarded ads)</span>
                    <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">Opt-in only — ads appear solely in the Rewards area, never elsewhere on the platform.</span>
                </span>
                <x-ui.switch wire:model="ads_enabled" label="Rewarded ads" class="mt-1" />
            </label>

            <div class="mt-4 rounded-xl border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-3 text-xs leading-relaxed text-[rgb(var(--nx-warn))]">
                <strong>Use a rewarded-ad / offerwall network, NOT Google AdSense.</strong> AdSense forbids paying users to watch/click ads and will ban the account. Compliant options that allow “watch/complete → earn”: <em>AdGate Media, AdGem, BitLabs, CPX Research, Pollfish, Tapjoy, Adscend</em>. They pay you per verified completion and confirm it with a server postback — so rewards are always genuine and profitable.
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Provider name</label>
                    <input wire:model="ad_provider" type="text" placeholder="e.g. AdGate" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Just a label shown to you — pick any of the networks above.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Daily earn cap per user (credits)</label>
                    <input wire:model="ad_daily_cap" type="number" min="0" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Protects the economy from a bad feed. 0 = no cap.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Offerwall URL</label>
                    <input wire:model="ad_offerwall_url" type="url" placeholder="https://wall.adgatemedia.com/…" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">In your network dashboard, create a wall/placement and copy its URL. We append <code class="font-mono">?user=…&amp;sig=…</code> so the network can attribute the reward to the right person.</p>
                    @error('ad_offerwall_url') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-[rgb(var(--nx-line))] p-4 text-xs">
                <p class="font-semibold text-[rgb(var(--nx-text))]">Two credentials to finish setup:</p>
                <ol class="mt-2 list-decimal space-y-1.5 pl-4 text-[rgb(var(--nx-text-2))]">
                    <li><strong>Postback secret</strong> — set <code class="font-mono">OFFERWALL_POSTBACK_SECRET</code> in your environment. In the network dashboard this is the “security/secret key” used to sign postbacks.
                        Status: @if ($postbackConfigured) <span class="inline-flex items-center gap-1 font-semibold text-[rgb(var(--nx-ok))]"><x-icon name="check" class="h-3.5 w-3.5" /> set</span> @else <span class="font-semibold text-[rgb(var(--nx-warn))]">not set</span> @endif
                    </li>
                    <li><strong>Postback URL</strong> — paste this into your network’s “server postback / callback URL” field:<br>
                        <code class="mt-1 block break-all rounded bg-[rgb(var(--nx-surface-3))] px-2 py-1 font-mono text-[11px] text-[rgb(var(--nx-text))]">{{ $postbackUrl }}?user={USER_ID}&amp;amount={REWARD}&amp;txn={TRANSACTION_ID}&amp;signature={SIGNATURE}</code>
                        <span class="mt-1 block">where <code class="font-mono">signature = HMAC_SHA256("{USER_ID}:{REWARD}:{TRANSACTION_ID}", secret)</code>. Most networks let you build this from their macros.</span>
                    </li>
                </ol>
            </div>
        </section>

        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Save settings</span>
            <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
        </button>
    </form>
</div>

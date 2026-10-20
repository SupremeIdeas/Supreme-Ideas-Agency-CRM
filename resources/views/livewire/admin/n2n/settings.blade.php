{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">N2N (Naara to Naara)</h1>
    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Master only. In-network identity, messaging and calling between Naara members. Off by default; nothing here exists on white-label installs.</p>

    @if ($saved)<div class="mt-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $saved }}</div>@endif

    <section class="mt-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Naara chat logo</h2>
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Shown in the chat rail, the mobile header and the quick chat button. Square, transparent PNG, WebP or SVG, up to 1 MB. The intro animation is set separately in Preloader Studio (page type "Naara Chat").</p>
        <div class="mt-3 flex flex-wrap items-center gap-4">
            <img src="{{ \App\Support\N2N\N2NSettings::logoUrl() }}" alt="Current Naara chat logo" class="h-14 w-14 rounded-xl bg-[rgb(var(--nx-surface-2))] object-contain p-1">
            <input type="file" wire:model="logoUpload" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="text-sm text-[rgb(var(--nx-text))]">
            <button type="button" wire:click="uploadLogo" wire:loading.attr="disabled" wire:target="uploadLogo,logoUpload" class="ns-cta ns-cta--pill ns-cta--sm">Upload</button>
            <button type="button" wire:click="resetLogo" wire:loading.attr="disabled" wire:target="resetLogo" class="nc__chip">Use the shipped logo</button>
        </div>
        @error('logoUpload')<p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
    </section>

    <form wire:submit="save" class="mt-5 space-y-5">
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <label class="flex items-center justify-between gap-4">
                <span><span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Enable N2N</span>
                    <span class="block text-xs text-[rgb(var(--nx-text-2))]">When off, every N2N page and API answers 404.</span></span>
                <input type="checkbox" wire:model="enabled" class="h-5 w-9 cursor-pointer rounded-full">
            </label>
        </div>

        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <label class="flex items-center justify-between gap-4">
                <span><span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Quick Naara chat button</span>
                    <span class="block text-xs text-[rgb(var(--nx-text-2))]">A small floating button on the pages below. It never shows on the dashboard home, in chat, checkout, support or admin.</span></span>
                <input type="checkbox" wire:model="floatingChat" class="h-5 w-9 cursor-pointer rounded-full">
            </label>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2"><label for="n2n-fab-routes" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Show on these pages (route names, comma separated, * allowed)</label>
                    <input id="n2n-fab-routes" type="text" wire:model="floatingRoutes" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">@error('floatingRoutes')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                <div><label for="n2n-fab-hours" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Hide for (hours) after someone closes it</label>
                    <input id="n2n-fab-hours" type="number" wire:model="floatingHours" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></div>
            </div>
        </div>

        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Realtime</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <div><label for="n2n-driver" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Transport</label>
                    <select id="n2n-driver" wire:model="driver" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                        <option value="poll">Polling (works everywhere)</option><option value="reverb">Reverb (VPS, instant)</option><option value="null">Off</option></select></div>
                <div><label for="n2n-pv" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Poll interval, tab visible (ms)</label>
                    <input id="n2n-pv" type="number" wire:model="pollVisibleMs" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">@error('pollVisibleMs')<span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror</div>
                <div><label for="n2n-ph" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Poll interval, tab hidden (ms)</label>
                    <input id="n2n-ph" type="number" wire:model="pollHiddenMs" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">@error('pollHiddenMs')<span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror</div>
            </div>
            <dl class="mt-4 grid gap-2 text-sm text-[rgb(var(--nx-text))] sm:grid-cols-3">
                <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Active transport</dt><dd>{{ $health['driver'] }}@if ($health['reverb_reachable'] !== null) ({{ $health['reverb_reachable'] ? 'reachable' : 'NOT reachable' }})@endif</dd></div>
                <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Events (5 min / logged)</dt><dd>{{ $health['events_last_5m'] }} / {{ $health['events_logged'] }}</dd></div>
                <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Poll requests (this / last minute)</dt><dd>{{ $health['polls_this_minute'] }} / {{ $health['polls_last_minute'] }}</dd></div>
            </dl>
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">{{ $health['capacity'] }} Open-file limit: {{ $health['fd_limit'] ?? 'unknown' }}. Reverb and calling need a VPS; on shared cPanel hosting use polling.</p>
        </div>

        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Identity and discovery</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                @foreach ([['cooldownDays', 'Username change cooldown (days)'], ['otpPerUserDay', 'OTP per user / day'], ['otpPerNumberDay', 'OTP per number / day'], ['otpPerIpDay', 'OTP per IP / day'], ['searchPerMin', 'Lookups per minute'], ['importMax', 'Max contacts per import'], ['maxPinnedChats', 'Pinned chats per member'], ['exportMaxMessages', 'Messages in a chat export']] as [$f, $l])
                    <div><label for="n2n-{{ $f }}" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $l }}</label>
                        <input id="n2n-{{ $f }}" type="number" wire:model="{{ $f }}" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">@error($f)<span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror</div>
                @endforeach
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><label for="n2n-res" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Extra reserved usernames (comma separated)</label>
                    <input id="n2n-res" type="text" wire:model="reserved" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></div>
                <div><label for="n2n-from" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">SMS sender for verification codes</label>
                    <input id="n2n-from" type="text" wire:model="otpFrom" placeholder="+1..." class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></div>
            </div>
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Measured phone-verification cost: @if ($otpCost['avg_per_send_usd'] !== null) ${{ $otpCost['avg_per_send_usd'] }} per code sent, @if ($otpCost['per_activation_usd'] !== null) ${{ $otpCost['per_activation_usd'] }} per linked number @endif @else not measured yet (it fills in from real sends). @endif</p>
        </div>

        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">NaaraCredit economy</h2>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Usage credit is bought with wallet money and spent only inside N2N. It never converts back and is never redeemable at checkout or withdrawable. Prices are charged server-side; clients cannot supply sizes or durations.</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                @foreach ([['priceMessage', 'Price per message (credits)'], ['storageUsdPerMb', 'Storage cost, USD per MB (from your real bill)'], ['mediaMarkup', 'Media markup (x)'], ['minMarginPct', 'Minimum margin on media (%)'], ['callUsdPerMin', 'Call, USD per minute'], ['speakerUsdPerMin', 'Group-call speaker, USD per minute'],
                    ['shareMin', 'Share: minimum (credits)'], ['shareMax', 'Share: maximum (credits)'], ['shareCapDay', 'Share: daily cap per sender'], ['shareCapRecipient', 'Share: daily cap per recipient'], ['shareMinAgeDays', 'Share: minimum account age (days)'], ['giftHours', 'Pending gift lifetime (hours)'],
                    ['giftMaxPerDay', 'Gifts per sender per day'], ['purchaseMinUsd', 'Buy: minimum (USD)'], ['purchaseMaxUsd', 'Buy: maximum (USD)'], ['lowBalance', 'Low-balance alert below (credits)']] as [$f, $l])
                    <div><label for="n2n-{{ $f }}" class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $l }}</label>
                        <input id="n2n-{{ $f }}" type="number" step="any" wire:model="{{ $f }}" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">@error($f)<span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror</div>
                @endforeach
            </div>
            <label class="mt-4 flex items-center gap-3 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="purchaseIos" class="h-4 w-4"> Allow buying credit inside the native iOS app (leave off until you have decided on in-app purchase rules)</label>
            <div class="mt-4 rounded-lg bg-[rgb(var(--nx-surface-2))] p-3 text-xs text-[rgb(var(--nx-text))]">
                <p class="font-semibold">Worked example with today's numbers</p>
                <p class="mt-1">Peg: {{ $example['peg'] }} · Message: {{ $example['message'] }} · A 5 MB file: {{ $example['media_5mb'] }} · A call minute: {{ $example['call_minute'] }}</p>
                <p class="mt-1 text-[rgb(var(--nx-text-2))]">Media basis: {{ $example['media_basis'] }}. The default storage cost is a placeholder: set it from your actual storage invoice and review it monthly.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Liability and abuse (last 30 days)</h2>
            <dl class="mt-3 grid gap-3 text-sm text-[rgb(var(--nx-text))] sm:grid-cols-3">
                <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Outstanding credit</dt><dd>{{ $accounting['outstanding_credits'] }} (about ${{ number_format($accounting['outstanding_usd'], 2) }})</dd></div>
                <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">In 24h gift escrow</dt><dd>{{ $accounting['escrow_credits'] }} (about ${{ number_format($accounting['escrow_usd'], 2) }})</dd></div>
                <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Consumed</dt><dd>{{ $accounting['consumed_30d_credits'] }} (about ${{ number_format($accounting['consumed_30d_usd'], 2) }})</dd></div>
                <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Bought with wallet money</dt><dd>${{ number_format($accounting['purchased_30d_usd'], 2) }}</dd></div>
            </dl>
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">{{ $accounting['note'] }}</p>
            @forelse ($flags as $f)
                <p class="mt-2 text-sm text-[rgb(var(--nx-warn))]">Flag: user #{{ $f['user_id'] }}, {{ str_replace('_', ' ', $f['kind']) }} ({{ $f['detail'] }})</p>
            @empty
                <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">No gift-abuse flags.</p>
            @endforelse
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill">Save settings</button>
    </form>
</div>

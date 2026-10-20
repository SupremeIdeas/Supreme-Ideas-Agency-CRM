{{-- Wallet card (Theme Batch 2 §2): the balance hero. USD is the one spendable balance; NGN is its live-rate equivalent, never the
     frozen legacy ngn_balance column (Unified USD Wallet, Part B). --}}
@if ($wallet)
    <x-nx.balance-hero :href="route('wallet')" icon="wallet" label="Wallet balance" :amount="'$'.number_format((float) $wallet->usd_balance, 2)"
                       :sub="'≈ '.app(\App\Services\Pricing\CurrencyService::class)->format((float) $wallet->usd_balance, 'NGN')" style="margin-top:20px">
        <span class="ns-btn ns-btn--solid" style="background:rgb(2 20 32 / .55);box-shadow:inset 0 0 0 1px rgb(255 255 255 / .3)">Top up <x-nx.icon name="chevron-right" /></span>
    </x-nx.balance-hero>
    <p class="ns-small" style="margin:6px 4px 0;font-size:11.5px">{{ auth()->user()->name }} · {{ \App\Support\BrandSettings::name() }}</p>
@endif

{{-- First-visit value showcase (Theme Batch 2 §2): one spaced card per product, only for a not-yet-active account. Same routes as before.
     Each card's image + opacity + size come from Admin → Bento icons (BentoIcons), keyed by the product's key. --}}
@unless ($hasAny)
    @php($rows = [
        ['naara-connect', 'sim', 'Naara Connect', 'eSIM', 'One eSIM for calls, SMS and high-speed data anywhere in the world.', route('catalogue', ['tab' => 'full']), 'See Full eSIMs'],
        ['esim-data-plans', 'globe', 'Naara Data', null, 'Local data in 190+ countries — installed before you fly, connected when you land.', route('catalogue'), 'Browse plans'],
        ['verification-numbers', 'shield-check', 'Naara Verify', null, 'Receive one-time codes for WhatsApp, Google, Facebook and more — in seconds.', route('numbers'), 'Get a number'],
        ['virtual-numbers', 'phone', 'Naara Rent', null, 'A permanent second line for calls and SMS, without a second phone.', route('numbers'), 'Explore numbers'],
    ])
    @if (\App\Support\FeatureFlags::adminEnabled('naara_gift'))
        @php($giftLive = \App\Support\FeatureFlags::configured('naara_gift'))
        @php($rows[] = ['naara-gift', 'gift', 'Naara Gift', $giftLive ? null : 'Soon', 'Send gift cards for 1,000+ brands — delivered instantly by email or WhatsApp.', route('gift-cards'), $giftLive ? 'Browse gifts' : 'Coming soon'])
    @endif

    <div class="ns-showcase-grid">
        @foreach ($rows as [$bkey, $icon, $title, $badge, $text, $url, $cta])
            <x-nx.showcase :bkey="$bkey" :href="$url" :icon="$icon" :title="$title" :badge="$badge" :text="$text" :cta="$cta" />
        @endforeach
    </div>
@endunless

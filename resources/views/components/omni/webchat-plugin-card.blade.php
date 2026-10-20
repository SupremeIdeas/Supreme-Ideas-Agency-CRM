{{-- The WordPress plugin download banner on a web chat card (MASTER-ONLY, Naara Respond). Says plainly what the download is, what is inside, what it never does, and how to install it. --}}
@props(['href', 'info'])
@php
    $svg = (string) @file_get_contents(base_path('resources/omni/wordpress/naara-respond-chat/assets/chat-flow.svg'));
    $u = 'nrc'.substr(md5(uniqid('', true)), 0, 6);
    $svg = str_replace(['nrc-t', 'nrc-d', 'nrc-bg', 'nrc-teal', 'nrc-gold', 'nrc-ig', 'nrc-flow', 'nrc-sh', 'nrc-ar'], [$u.'t', $u.'d', $u.'bg', $u.'teal', $u.'gold', $u.'ig', $u.'flow', $u.'sh', $u.'ar'], $svg);
@endphp
<aside {{ $attributes->merge(['class' => 'mt-3 overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]']) }} aria-label="WordPress plugin" data-wp-plugin-card>
    @if ($svg !== '')<div class="[&>svg]:block [&>svg]:h-auto [&>svg]:w-full" aria-hidden="false">{!! $svg !!}</div>@endif
    <div class="space-y-3 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">WordPress plugin</p>
                <h3 class="font-display text-lg font-extrabold text-[rgb(var(--nx-text))]">{{ $info['name'] }}</h3>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Version {{ $info['version'] }} · about {{ $info['kb'] }} KB · free · needs WordPress {{ $info['requires']['wp'] }} and PHP {{ $info['requires']['php'] }} or newer</p>
            </div>
            <a href="{{ $href }}" class="ns-cta ns-cta--pill" data-wp-plugin download>Download the plugin (.zip)</a>
        </div>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Adds this chat bubble to every page of your WordPress site. Visitor messages arrive in your Naara inbox, next to WhatsApp, Instagram, Telegram, SMS and email. It is already set up for this chat, so there is nothing to paste.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-[rgb(var(--nx-ok)/0.08)] p-3">
                <p class="mb-1 text-xs font-bold text-[rgb(var(--nx-ok))]">What it does</p>
                <ul class="list-disc space-y-0.5 ps-4 text-xs text-[rgb(var(--nx-text))]">
                    <li>Loads one script from your own Naara address.</li>
                    <li>Adds a Settings, Naara Chat page: switch it off, or hide it while you are logged in as an admin.</li>
                    <li>Stores one setting in your WordPress database (your address and chat key).</li>
                </ul>
            </div>
            <div class="rounded-xl bg-[rgb(var(--nx-bad)/0.07)] p-3">
                <p class="mb-1 text-xs font-bold text-[rgb(var(--nx-bad))]">What it never does</p>
                <ul class="list-disc space-y-0.5 ps-4 text-xs text-[rgb(var(--nx-text))]">
                    <li>Contains no password, API key or secret.</li>
                    <li>Stores no visitor messages or personal data on your WordPress server.</li>
                    <li>Makes no change to your theme, your other plugins or your content.</li>
                </ul>
            </div>
        </div>
        <ol class="list-decimal space-y-0.5 ps-5 text-sm text-[rgb(var(--nx-text))]">
            <li>Download the zip above (do not unzip it).</li>
            <li>In WordPress open <b>Plugins, Add New, Upload Plugin</b>, choose the zip, then <b>Activate</b>.</li>
            <li>Make sure your site address is in this chat's allowed websites, then open your site and say hello.</li>
        </ol>
        <details class="rounded-xl border border-[rgb(var(--nx-line))] p-3 text-xs text-[rgb(var(--nx-text-2))]" data-wp-plugin-files>
            <summary class="cursor-pointer font-semibold text-[rgb(var(--nx-text))]">What is inside the zip, and how to check it</summary>
            <ul class="mt-2 space-y-1">
                @foreach ($info['files'] as $file => $what)<li><code class="text-[rgb(var(--nx-text))]">naara-respond-chat/{{ $file }}</code>: {{ $what }}</li>@endforeach
            </ul>
            <p class="mt-2">Fingerprint of the plugin files (SHA-256): <code class="break-all text-[rgb(var(--nx-text))]" data-wp-plugin-fingerprint>{{ $info['fingerprint'] }}</code></p>
            <p class="mt-1">The fingerprint changes only when the plugin's code changes, so you can tell a new version from the one you already installed.</p>
        </details>
    </div>
</aside>

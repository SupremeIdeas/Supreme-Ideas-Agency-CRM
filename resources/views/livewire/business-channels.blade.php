{{-- nx:converted (skin canvas + tokens) --}}
@php
    $card = 'mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6';
    $inp = 'w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3.5 py-2.5 text-sm text-[rgb(var(--nx-text))]';
    $lbl = 'mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]';
@endphp
<div>
<x-nx.page class="px-0">
<div class="mx-auto max-w-3xl pb-28">
    <div class="mb-6 flex items-center gap-3">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="message-circle" class="h-5 w-5" /></span>
        <div>
            <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]">Inbox channels</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">{{ $group->name }}. Bring WhatsApp, Telegram and Instagram messages into Messages, next to SMS. Switch to this workspace to see them.</p>
        </div>
    </div>

    @if ($error)<div role="alert" class="mb-4 rounded-xl bg-[rgb(var(--nx-bad)/0.12)] p-3 text-sm text-[rgb(var(--nx-bad))]">{{ $error }}</div>@endif
    @if ($ok)<div role="status" class="mb-4 rounded-xl bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">{{ $ok }}</div>@endif

    <section class="{{ $card }}" aria-labelledby="ch-list">
        <h2 id="ch-list" class="mb-4 text-sm font-bold text-[rgb(var(--nx-text))]">Connected</h2>
        @forelse ($connections as $c)
            <div class="border-b border-[rgb(var(--nx-line))] py-3 last:border-0" wire:key="conn-{{ $c['id'] }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $c['name'] }} <span class="font-normal text-[rgb(var(--nx-text-2))]">{{ $c['label'] }}</span></p>
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $c['status'] === 'active' ? 'On' : 'Paused' }}@if ($c['last']) · last message {{ $c['last']->diffForHumans() }}@else · no messages yet @endif</p>
                        @if ($c['error'])<p class="text-xs text-[rgb(var(--nx-warn))]">{{ $c['error'] }}</p>@endif
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="pause({{ $c['id'] }})" class="rounded-full px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-teal)/0.1)]">{{ $c['status'] === 'active' ? 'Pause' : 'Resume' }}</button>
                        <button type="button" wire:click="disconnect({{ $c['id'] }})" wire:confirm="Disconnect this channel? Past conversations stay in your inbox." wire:loading.attr="disabled" wire:target="disconnect({{ $c['id'] }})" aria-label="Disconnect" class="flex h-8 w-8 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-bad)/0.1)] hover:text-[rgb(var(--nx-bad))] disabled:opacity-60"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                </div>
                @if ($c['snippet'])
                    <div class="mt-3 rounded-xl bg-[rgb(var(--nx-teal)/0.06)] p-3 text-xs text-[rgb(var(--nx-text-2))]">
                        <p class="mb-1 font-semibold text-[rgb(var(--nx-text))]">Paste this before &lt;/body&gt; on your website</p>
                        <code class="block break-all text-[rgb(var(--nx-text))]">{{ $c['snippet'] }}</code>
                    </div>
                    @if ($c['plugin'])<x-omni.webchat-plugin-card :href="$c['plugin']" :info="$pluginInfo" />@endif
                @endif
                @if ($c['inbound'])
                    <div class="mt-3 rounded-xl bg-[rgb(var(--nx-teal)/0.06)] p-3 text-xs text-[rgb(var(--nx-text-2))]">
                        <p class="mb-1 font-semibold text-[rgb(var(--nx-text))]">Forward your inbound email here</p>
                        <p>Address: <code class="break-all text-[rgb(var(--nx-text))]">{{ $c['inbound'] }}</code></p>
                        <p>Signing secret: <code class="break-all text-[rgb(var(--nx-text))]">{{ $c['inboundSecret'] }}</code></p>
                        <p class="mt-1">Post each email as JSON (message_id, from.email, from.name, subject, text) with the header X-Naara-Signature: sha256=HMAC of the body.</p>
                    </div>
                @endif
                @if ($c['embedded'])
                    <p class="mt-3 rounded-xl bg-[rgb(var(--nx-teal)/0.06)] p-3 text-xs text-[rgb(var(--nx-text-2))]">Connected through Meta. Nothing to paste: the webhook was set up for you.</p>
                @endif
                @if ($c['meta'])
                    <div class="mt-3 rounded-xl bg-[rgb(var(--nx-teal)/0.06)] p-3 text-xs text-[rgb(var(--nx-text-2))]">
                        <p class="mb-1 font-semibold text-[rgb(var(--nx-text))]">Paste these into your Meta app's webhook settings</p>
                        <p>Callback URL: <code class="break-all text-[rgb(var(--nx-text))]">{{ $c['callback'] }}</code></p>
                        <p>Verify token: <code class="break-all text-[rgb(var(--nx-text))]">{{ $c['verify'] }}</code></p>
                        <p class="mt-1">Subscribe to the "messages" field for this {{ $c['name'] }} account.</p>
                    </div>
                @endif
            </div>
        @empty
            <x-nx.empty art="bs-channels" title="Nothing connected yet" text="Connect a channel below." />
        @endforelse
    </section>

    <section class="{{ $card }}" aria-labelledby="ch-tg">
        <h2 id="ch-tg" class="mb-1 flex items-center gap-2 text-sm font-bold text-[rgb(var(--nx-text))]"><span class="bz-chan__dot" data-ch="telegram" aria-hidden="true"><x-business.channel-logo channel="telegram" /></span>Telegram</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">Create a bot with BotFather, then paste its token. We register the webhook for you. Free, no approval needed.</p>
        <form wire:submit="connectTelegram" class="space-y-3">
            <div><label for="tg-token" class="{{ $lbl }}">Bot token</label><input id="tg-token" type="password" autocomplete="off" wire:model="telegramToken" class="{{ $inp }}"></div>
            <button type="submit" class="ns-cta ns-cta--pill ns-cta--sm" wire:loading.attr="disabled" wire:target="connectTelegram"><span wire:loading.remove wire:target="connectTelegram">Connect Telegram</span><span wire:loading wire:target="connectTelegram">Checking...</span></button>
        </form>
    </section>

    @if ($webchatAvailable)
    <section class="{{ $card }}" aria-labelledby="ch-wc">
        <h2 id="ch-wc" class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">Website chat</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">A chat bubble for your website. Visitors write, your team answers from Respond. Free, nothing to approve.</p>
        <form wire:submit="connectWebChat" class="space-y-3">
            <div><label for="wc-label" class="{{ $lbl }}">Name</label><input id="wc-label" type="text" wire:model="wcLabel" class="{{ $inp }}" placeholder="Website chat"></div>
            <div><label for="wc-origins" class="{{ $lbl }}">Website address(es)</label><input id="wc-origins" type="text" wire:model="wcOrigins" class="{{ $inp }}" placeholder="https://yourshop.com"></div>
            <div><label for="wc-greet" class="{{ $lbl }}">Greeting</label><input id="wc-greet" type="text" maxlength="160" wire:model="wcGreeting" class="{{ $inp }}" placeholder="Hi! How can we help?"></div>
            <div><label for="wc-color" class="{{ $lbl }}">Chat colour</label><input id="wc-color" type="text" wire:model="wcColor" class="{{ $inp }}" placeholder="Six-digit hex"></div>
            <button type="submit" class="ns-cta ns-cta--pill ns-cta--sm" wire:loading.attr="disabled" wire:target="connectWebChat"><span wire:loading.remove wire:target="connectWebChat">Create web chat</span><span wire:loading wire:target="connectWebChat">Creating...</span></button>
        </form>
    </section>
    @endif

    @if ($emailAvailable)
    <section class="{{ $card }}" aria-labelledby="ch-em">
        <h2 id="ch-em" class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">Email</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">Answer your support email from Respond. Customers' emails arrive in the inbox, and your replies leave from your own support address.</p>
        <form wire:submit="connectEmail" class="space-y-3">
            <div><label for="em-label" class="{{ $lbl }}">Name</label><input id="em-label" type="text" wire:model="emLabel" class="{{ $inp }}" placeholder="Support email"></div>
            <div><label for="em-from" class="{{ $lbl }}">Support address</label><input id="em-from" type="email" wire:model="emFrom" class="{{ $inp }}" placeholder="support@yourshop.com"></div>
            <div><label for="em-reply" class="{{ $lbl }}">Reply-to (optional)</label><input id="em-reply" type="email" wire:model="emReplyTo" class="{{ $inp }}"></div>
            <button type="submit" class="ns-cta ns-cta--pill ns-cta--sm" wire:loading.attr="disabled" wire:target="connectEmail"><span wire:loading.remove wire:target="connectEmail">Connect email</span><span wire:loading wire:target="connectEmail">Connecting...</span></button>
        </form>
    </section>
    @endif

    @if ($metaSignup)
    <script>
        window.metaSignup = window.metaSignup || ((cfg) => ({
                busy: false, phoneNumberId: '', wabaId: '', finished: false,
                init() {
                    window.addEventListener('message', (event) => {
                        if (!event.origin || !/^https:\/\/(www|web)\.facebook\.com$/.test(event.origin)) return;
                        try {
                            const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                            if (data && data.type === 'WA_EMBEDDED_SIGNUP') {
                                if (data.event === 'FINISH' || data.event === 'FINISH_ONLY_WABA' || data.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') {
                                    this.phoneNumberId = String((data.data && data.data.phone_number_id) || '');
                                    this.wabaId = String((data.data && data.data.waba_id) || '');
                                } else if (data.event === 'CANCEL') {
                                    this.busy = false; this.$wire.metaSignupFailed('cancelled');
                                }
                            }
                        } catch (e) { /* not a Meta message */ }
                    });
                },
                load() {
                    return new Promise((resolve) => {
                        if (window.FB) { resolve(); return; }
                        window.fbAsyncInit = () => { window.FB.init({ appId: cfg.appId, autoLogAppEvents: true, xfbml: false, version: cfg.version }); resolve(); };
                        const s = document.createElement('script'); s.async = true; s.defer = true; s.crossOrigin = 'anonymous';
                        s.src = 'https://connect.facebook.net/en_US/sdk.js'; document.body.appendChild(s);
                    });
                },
                async start() {
                    this.busy = true; this.phoneNumberId = ''; this.wabaId = '';
                    await this.load();
                    window.FB.login((response) => {
                        const code = response && response.authResponse && response.authResponse.code;
                        if (!code) { this.busy = false; this.$wire.metaSignupFailed('cancelled'); return; }
                        // Meta posts the picked number a moment before or after the code; wait briefly for both.
                        let tries = 0;
                        const wait = setInterval(() => {
                            tries++;
                            if (this.phoneNumberId && this.wabaId) {
                                clearInterval(wait);
                                this.$wire.finishMetaSignup(code, this.phoneNumberId, this.wabaId).finally(() => { this.busy = false; });
                            } else if (tries > 40) {
                                clearInterval(wait); this.busy = false; this.$wire.metaSignupFailed('');
                            }
                        }, 250);
                    }, { config_id: cfg.configId, response_type: 'code', override_default_response_type: true, extras: { setup: {}, featureType: '', sessionInfoVersion: '3' } });
                },
            }));
    </script>
    <section class="{{ $card }}" aria-labelledby="ch-meta" data-meta-signup
        x-data="metaSignup(@js($metaSignup))">
        <h2 id="ch-meta" class="mb-1 flex items-center gap-2 text-sm font-bold text-[rgb(var(--nx-text))]"><span class="bz-chan__dot" data-ch="whatsapp" aria-hidden="true"><x-business.channel-logo channel="whatsapp" /></span>Connect WhatsApp with Meta (one click)</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">The easiest way. A Meta window opens: sign in to Facebook, pick or create your WhatsApp Business account and number, and finish. We switch the number on for you. No tokens or webhook settings to copy.</p>
        <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" x-on:click="start()" x-bind:disabled="busy" data-meta-signup-button>
            <span x-show="!busy">Continue with Meta</span><span x-show="busy" x-cloak>Waiting for Meta...</span>
        </button>
        <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">You can also connect by hand below if you bring your own Meta app.</p>
    </section>
    @endif

    <section class="{{ $card }}" aria-labelledby="ch-wa">
        <h2 id="ch-wa" class="mb-1 flex items-center gap-2 text-sm font-bold text-[rgb(var(--nx-text))]"><span class="bz-chan__dot" data-ch="whatsapp" aria-hidden="true"><x-business.channel-logo channel="whatsapp" /></span>WhatsApp Business</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">Use the WhatsApp Business number in your own Meta app. You can reply within 24 hours of a customer's last message; after that WhatsApp only allows pre-approved templates. Meta bills you directly for conversations.</p>
        <form wire:submit="connectWhatsApp" class="space-y-3">
            <div><label for="wa-id" class="{{ $lbl }}">Phone number ID</label><input id="wa-id" type="text" inputmode="numeric" wire:model="waNumberId" class="{{ $inp }}"></div>
            <div><label for="wa-token" class="{{ $lbl }}">Access token</label><input id="wa-token" type="password" autocomplete="off" wire:model="waToken" class="{{ $inp }}"></div>
            <div><label for="wa-secret" class="{{ $lbl }}">App secret</label><input id="wa-secret" type="password" autocomplete="off" wire:model="waSecret" class="{{ $inp }}"></div>
            <button type="submit" class="ns-cta ns-cta--pill ns-cta--sm" wire:loading.attr="disabled" wire:target="connectWhatsApp"><span wire:loading.remove wire:target="connectWhatsApp">Connect WhatsApp</span><span wire:loading wire:target="connectWhatsApp">Checking...</span></button>
        </form>
    </section>

    <section class="{{ $card }}" aria-labelledby="ch-ig">
        <h2 id="ch-ig" class="mb-1 flex items-center gap-2 text-sm font-bold text-[rgb(var(--nx-text))]"><span class="bz-chan__dot" data-ch="instagram" aria-hidden="true"><x-business.channel-logo channel="instagram" /></span>Instagram</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">Connect an Instagram professional account from your own Meta app. Replies are allowed within 24 hours of the person's last message.</p>
        <form wire:submit="connectInstagram" class="space-y-3">
            <div><label for="ig-id" class="{{ $lbl }}">Instagram account ID</label><input id="ig-id" type="text" inputmode="numeric" wire:model="igAccountId" class="{{ $inp }}"></div>
            <div><label for="ig-token" class="{{ $lbl }}">Access token</label><input id="ig-token" type="password" autocomplete="off" wire:model="igToken" class="{{ $inp }}"></div>
            <div><label for="ig-secret" class="{{ $lbl }}">App secret</label><input id="ig-secret" type="password" autocomplete="off" wire:model="igSecret" class="{{ $inp }}"></div>
            <button type="submit" class="ns-cta ns-cta--pill ns-cta--sm" wire:loading.attr="disabled" wire:target="connectInstagram"><span wire:loading.remove wire:target="connectInstagram">Connect Instagram</span><span wire:loading wire:target="connectInstagram">Checking...</span></button>
        </form>
    </section>

    @if (count($smsNumbers) > 0)
    <section class="{{ $card }}" aria-labelledby="ch-sms" data-sms-channel>
        <h2 id="ch-sms" class="mb-1 flex items-center gap-2 text-sm font-bold text-[rgb(var(--nx-text))]">SMS on your workspace number</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">Texts to one of your workspace numbers arrive in the Respond inbox and your team replies from it. Each reply is charged to the number's owner wallet at the normal SMS price, the same as sending from Messages. The same texts also stay visible in Messages, so choose one inbox for your team to answer from.</p>
        <div class="space-y-2">
            @foreach ($smsNumbers as $n)
                <div class="flex items-center justify-between gap-2" wire:key="sms-{{ $n['id'] }}">
                    <span class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $n['number'] }}</span>
                    <button type="button" wire:click="connectSms({{ $n['id'] }})" wire:loading.attr="disabled" wire:target="connectSms({{ $n['id'] }})" class="ns-cta ns-cta--pill ns-cta--sm" data-connect-sms="{{ $n['id'] }}">Use for Respond</button>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    @if ($messengerAvailable)
    <section class="{{ $card }}" aria-labelledby="ch-ms">
        <h2 id="ch-ms" class="mb-1 flex items-center gap-2 text-sm font-bold text-[rgb(var(--nx-text))]">Facebook Messenger</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">Connect a Facebook Page from your own Meta app. Replies are allowed within 24 hours of the person's last message.</p>
        <form wire:submit="connectMessenger" class="space-y-3">
            <div><label for="ms-id" class="{{ $lbl }}">Page ID</label><input id="ms-id" type="text" inputmode="numeric" wire:model="msPageId" class="{{ $inp }}"></div>
            <div><label for="ms-token" class="{{ $lbl }}">Page access token</label><input id="ms-token" type="password" autocomplete="off" wire:model="msToken" class="{{ $inp }}"></div>
            <div><label for="ms-secret" class="{{ $lbl }}">App secret</label><input id="ms-secret" type="password" autocomplete="off" wire:model="msSecret" class="{{ $inp }}"></div>
            <button type="submit" class="ns-cta ns-cta--pill ns-cta--sm" wire:loading.attr="disabled" wire:target="connectMessenger"><span wire:loading.remove wire:target="connectMessenger">Connect Messenger</span><span wire:loading wire:target="connectMessenger">Checking...</span></button>
        </form>
    </section>
    @endif

    <a href="{{ route('business.workspace') }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Back to the workspace</a>
</div>
</x-nx.page>
</div>

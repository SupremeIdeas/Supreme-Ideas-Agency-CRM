@if (\App\Support\FeatureFlags::enabled('naara_push'))
    {{-- Self-hosted web-push opt-in (owner request). Registers the service
         worker, and offers a subtle prompt to enable closed-tab notifications.
         Nothing renders/prompts unless VAPID keys are configured. --}}
    <div x-data="naaraPush(@js(\App\Support\WebPushConfig::publicKey()))" x-init="init()" x-cloak>
        <div x-show="showPrompt" x-transition
             class="nx-fab fixed bottom-24 left-1/2 z-40 w-[92vw] max-w-sm -translate-x-1/2 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 shadow-xl lg:bottom-6 lg:left-auto lg:right-6 lg:translate-x-0  ">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))] ">
                    <x-icon name="bell" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-[rgb(var(--nx-text))] ">Turn on notifications</p>
                    <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))] ">Get alerts about your orders, wallet and offers — even when this tab is closed.</p>
                    <div class="mt-3 flex gap-2">
                        <button @click="enable()" :disabled="busy"
                                class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                            <span x-show="!busy">Enable</span><span x-show="busy">Enabling…</span>
                        </button>
                        <button @click="dismiss()" class="rounded-lg px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))] ">Not now</button>
                    </div>
                </div>
                <button @click="dismiss()" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text-2))] "><x-icon name="x" class="h-4 w-4" /></button>
            </div>
        </div>
    </div>

    <script>
        function naaraPush(vapidPublicKey) {
            return {
                showPrompt: false,
                busy: false,
                reg: null,

                async init() {
                    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;
                    try {
                        this.reg = await navigator.serviceWorker.register('/sw.js');
                    } catch (e) { return; }

                    // Already subscribed? keep the server copy fresh, no prompt.
                    const existing = await this.reg.pushManager.getSubscription();
                    if (existing) { this.sync(existing); return; }

                    // Only nudge if the user hasn't decided and hasn't snoozed us.
                    if (Notification.permission === 'default' && localStorage.getItem('nx_push_snooze') !== '1') {
                        this.showPrompt = true;
                    }
                },

                async enable() {
                    this.busy = true;
                    try {
                        const permission = await Notification.requestPermission();
                        if (permission !== 'granted') { this.dismiss(); return; }
                        const sub = await this.reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlB64ToUint8Array(vapidPublicKey),
                        });
                        await this.sync(sub);
                        this.showPrompt = false;
                    } catch (e) {
                        // best-effort; leave the prompt for next time
                    } finally {
                        this.busy = false;
                    }
                },

                async sync(sub) {
                    const json = sub.toJSON();
                    await fetch('{{ route('push.subscribe') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({
                            endpoint: json.endpoint,
                            keys: json.keys,
                            contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                        }),
                    });
                },

                dismiss() {
                    this.showPrompt = false;
                    localStorage.setItem('nx_push_snooze', '1');
                },

                urlB64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                    const raw = atob(base64);
                    const out = new Uint8Array(raw.length);
                    for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
                    return out;
                },
            };
        }
    </script>
@endif

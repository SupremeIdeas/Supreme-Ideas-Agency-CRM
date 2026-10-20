{{-- Device permissions primer. Shown once, only inside the Naara apps (Android/iOS shell, desktop app, installed web app), so calls, voice notes, video, live
     location and alerts work the first time with no surprise error. Every button runs the real browser/OS prompt; nothing is faked. Skippable, and it can be
     reopened any time with: window.dispatchEvent(new CustomEvent('nx-open-permissions')). Contacts are not requested: no WebView exposes them to a website. --}}
<div x-data="naaraPermissions()" x-init="init()" x-cloak @nx-open-permissions.window="open(true)">
    <div x-show="show" x-transition.opacity class="fixed inset-0 z-[90] flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="{{ __('Allow device access') }}" @keydown.escape.window="finish()">
        <div class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-t-[30px] border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-2xl sm:rounded-3xl">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[rgb(var(--nx-cta-b)/0.12)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="shield-check" class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-bold text-[rgb(var(--nx-text))]">{{ __('Get Naara ready on this device') }}</h2>
                    <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ __('Allow what you want to use. You choose each one, and you can change it later in your device settings.') }}</p>
                </div>
            </div>

            <ul class="mt-4 space-y-2">
                <template x-for="p in items" :key="p.key">
                    <li class="flex items-center gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2,var(--nx-surface)))] p-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]">
                            <svg class="h-4 w-4" aria-hidden="true"><use :href="'#i-' + p.icon"></use></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-[rgb(var(--nx-text))]" x-text="p.title"></p>
                            <p class="text-xs text-[rgb(var(--nx-text-2))]" x-text="state[p.key] === 'denied' ? p.denied : p.why"></p>
                        </div>
                        <span x-show="state[p.key] === 'granted'" class="inline-flex items-center gap-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]"><svg class="h-4 w-4" aria-hidden="true"><use href="#i-check"></use></svg>{{ __('On') }}</span>
                        <button x-show="state[p.key] !== 'granted' && state[p.key] !== 'denied'" type="button" @click="ask(p.key)" :disabled="busy === p.key"
                                class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                            <span x-show="busy !== p.key">{{ __('Allow') }}</span><span x-show="busy === p.key">{{ __('Asking…') }}</span>
                        </button>
                        <span x-show="state[p.key] === 'denied'" class="text-xs font-semibold text-[rgb(var(--nx-text-2))]">{{ __('Blocked') }}</span>
                    </li>
                </template>
            </ul>

            <p x-show="anyDenied" class="mt-3 rounded-xl bg-[rgb(var(--nx-surface-3))] p-3 text-xs text-[rgb(var(--nx-text-2))]" x-text="helpText"></p>

            <div class="mt-4 flex gap-2">
                <button type="button" @click="allowAll()" :disabled="!!busy || allDone" class="flex-1 rounded-xl bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <span x-show="!allDone">{{ __('Allow all') }}</span><span x-show="allDone">{{ __('All set') }}</span>
                </button>
                <button type="button" @click="finish()" class="rounded-xl px-4 py-2.5 text-sm font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]" x-text="allDone ? @js(__('Done')) : @js(__('Not now'))"></button>
            </div>
        </div>
    </div>
</div>

<script>
    function naaraPermissions() {
        const KEY = 'nx_perm_primer_v1';
        const ua = navigator.userAgent || '';
        const inApp = () => !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform())
            || /Naara(App|Desktop)\//.test(ua) || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
        const platform = /NaaraDesktop\//.test(ua) ? 'desktop' : (/Android/i.test(ua) ? 'android' : (/iPhone|iPad/i.test(ua) ? 'ios' : 'web'));
        const all = [
            { key: 'microphone', icon: 'mic', title: @js(__('Microphone')), why: @js(__('Voice calls and voice notes.')), denied: @js(__('Blocked, so calls and voice notes cannot use your voice.')) },
            { key: 'camera', icon: 'camera', title: @js(__('Camera')), why: @js(__('Video calls, QR scanning and photos.')), denied: @js(__('Blocked, so video calls and QR scanning will not work.')) },
            { key: 'geolocation', icon: 'map-pin', title: @js(__('Location')), why: @js(__('Suggest your country and share live location when you choose.')), denied: @js(__('Blocked, so we cannot suggest your country or share location.')) },
            { key: 'notifications', icon: 'bell', title: @js(__('Notifications')), why: @js(__('Call, message and order alerts.')), denied: @js(__('Blocked, so you may miss calls and order updates.')) },
        ];
        return {
            show: false, busy: null, state: {}, items: [],
            get anyDenied() { return this.items.some((p) => this.state[p.key] === 'denied'); },
            get allDone() { return this.items.length > 0 && this.items.every((p) => ['granted', 'denied'].includes(this.state[p.key])); },
            get helpText() {
                return ({ android: @js(__('To turn one back on: Settings > Apps > Naara > Permissions.')), ios: @js(__('To turn one back on: Settings > Naara.')),
                          desktop: @js(__('To turn one back on, use your computer privacy settings (microphone, camera, location) and allow Naara.')),
                          web: @js(__('To turn one back on, use the lock icon beside the address.')) })[platform];
            },
            async init() {
                this.items = all.filter((p) => this.supported(p.key));
                await this.refresh();
                if (!inApp() || localStorage.getItem(KEY) === '1' || this.allDone) return;
                setTimeout(() => { if (!document.querySelector('[role="dialog"][aria-modal="true"]:not([aria-label="{{ __('Allow device access') }}"])')) this.show = true; }, 2200);
            },
            open(force) { this.refresh().then(() => { this.show = true; }); },
            supported(k) {
                if (k === 'microphone' || k === 'camera') return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
                if (k === 'geolocation') return 'geolocation' in navigator;
                return 'Notification' in window;   // the Android/iOS WebView has none: those alerts arrive through the app's own push channel
            },
            async query(k) {
                if (k === 'notifications' && 'Notification' in window) return ({ default: 'prompt', granted: 'granted', denied: 'denied' })[Notification.permission] || 'prompt';
                try { const r = await navigator.permissions.query({ name: k }); return r.state; } catch (e) { return 'prompt'; }
            },
            async refresh() { for (const p of this.items) this.state[p.key] = await this.query(p.key); },
            async ask(k) {
                this.busy = k;
                try {
                    if (k === 'microphone' || k === 'camera') {
                        const s = await navigator.mediaDevices.getUserMedia(k === 'camera' ? { video: true } : { audio: true });
                        s.getTracks().forEach((t) => t.stop());   // only asking: release the device straight away
                        this.state[k] = 'granted';
                    } else if (k === 'geolocation') {
                        await new Promise((ok, no) => navigator.geolocation.getCurrentPosition(ok, no, { timeout: 15000, maximumAge: 600000 }));
                        this.state[k] = 'granted';
                    } else {
                        this.state[k] = ({ granted: 'granted', denied: 'denied' })[await Notification.requestPermission()] || 'prompt';
                    }
                } catch (e) {
                    // NotAllowedError / PERMISSION_DENIED (code 1) is a real refusal; anything else (no camera fitted, no GPS fix) is not the person's choice
                    const refused = e && (e.name === 'NotAllowedError' || e.name === 'SecurityError' || e.code === 1);
                    this.state[k] = refused ? 'denied' : (await this.query(k));
                } finally { this.busy = null; }
            },
            async allowAll() { for (const p of this.items) if (this.state[p.key] === 'prompt') await this.ask(p.key); },
            finish() { try { localStorage.setItem(KEY, '1'); } catch (e) {} this.show = false; },
        };
    }
</script>

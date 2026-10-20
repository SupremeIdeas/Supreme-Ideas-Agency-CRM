{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Security — two-factor authentication</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        The admin panel requires TOTP two-factor authentication. Scan the code with an authenticator app
        (Google Authenticator, Authy, 1Password) and confirm to unlock the rest of the panel.
    </p>

    @if (! $confirmed)
        <div class="mb-6 flex items-start gap-2 rounded-lg bg-[rgb(var(--nx-warn)/0.12)] p-3 text-sm text-[rgb(var(--nx-warn))]">
            <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>Two-factor authentication is required before you can use the admin panel. Finish enrolment below.</span>
        </div>
    @endif

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        @if ($confirmed)
            <div class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-ok))]">
                <x-icon name="badge-check" class="h-5 w-5" /> Two-factor authentication is active.
            </div>
            <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">
                Keep your recovery codes somewhere safe — each can be used once if you lose your authenticator.
            </p>

            @if (! empty($recoveryCodes))
                <div class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-[rgb(var(--nx-surface-2))] p-4 font-mono text-xs text-[rgb(var(--nx-text))]">
                    @foreach ($recoveryCodes as $rc)
                        <span>{{ $rc }}</span>
                    @endforeach
                </div>
            @endif

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" wire:click="regenerateRecoveryCodes" wire:loading.attr="disabled" wire:target="regenerateRecoveryCodes"
                        class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line-strong))] px-4 py-2 text-sm font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-60">
                    <x-icon name="refresh" class="h-4 w-4" /> Regenerate recovery codes
                </button>
                @if ($canDisable)
                    <button type="button" wire:click="disable" wire:loading.attr="disabled" wire:target="disable"
                            wire:confirm="Turn off two-factor authentication for your admin account?"
                            class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-bad))] px-4 py-2 text-sm font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)] disabled:opacity-60">
                        <x-icon name="x" class="h-4 w-4" /> Disable 2FA
                    </button>
                @endif
            </div>
        @elseif ($enabled)
            {{-- Enabled but not yet confirmed: show the QR + secret + confirm step. --}}
            <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                <div class="shrink-0 rounded-lg bg-[rgb(var(--nx-surface))] p-3 shadow-sm">{!! $qr !!}</div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">
                        Scan this QR code, or enter the setup key manually:
                    </p>
                    <code class="mt-2 block break-all rounded-lg bg-[rgb(var(--nx-surface-2))] px-3 py-2 font-mono text-xs text-[rgb(var(--nx-text))]">{{ $secret }}</code>

                    @if (! empty($recoveryCodes))
                        <p class="mt-4 text-xs font-medium text-[rgb(var(--nx-text-2))]">Recovery codes (save these now):</p>
                        <div class="mt-1 grid grid-cols-2 gap-1 rounded-lg bg-[rgb(var(--nx-surface-2))] p-3 font-mono text-[11px] text-[rgb(var(--nx-text))]">
                            @foreach ($recoveryCodes as $rc)
                                <span>{{ $rc }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <form wire:submit="confirm" class="mt-6 border-t border-[rgb(var(--nx-line))] pt-5">
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Enter the 6-digit code from your app</label>
                <div class="flex items-start gap-2">
                    <input wire:model="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                           class="w-40 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 font-mono text-sm tracking-widest">
                    <button type="submit" wire:loading.attr="disabled" wire:target="confirm"
                            class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="confirm">Confirm &amp; activate</span>
                        <span wire:loading wire:target="confirm" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Verifying…</span>
                    </button>
                </div>
                @error('code') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </form>
        @else
            {{-- Not enabled yet. --}}
            <div class="flex items-start gap-3">
                <x-icon name="shield" class="mt-0.5 h-6 w-6 text-[rgb(var(--nx-teal-ink))]" />
                <div>
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">
                        Two-factor authentication is not set up on your admin account yet. Turn it on to generate a
                        QR code and recovery codes.
                    </p>
                    <button type="button" wire:click="enable" wire:loading.attr="disabled" wire:target="enable"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="enable" class="inline-flex items-center gap-2"><x-icon name="shield" class="h-4 w-4" /> Enable two-factor</span>
                        <span wire:loading wire:target="enable" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Setting up…</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Site protection (super-admin only). Plain-language toggles for the two
         security headers most likely to clash with a specific host. --}}
    @if ($canManageSite)
        <div class="mt-8">
            <h2 class="mb-1 text-lg font-bold text-[rgb(var(--nx-text))]">Site protection</h2>
            <p class="mb-4 text-sm text-[rgb(var(--nx-text-2))]">
                These extra web-security shields are <span class="font-medium">on by default</span> and recommended. Only turn one off if something on your site visibly stops working after installing on your server — then tell your developer.
            </p>

            @if ($siteSaved)
                <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
                    <x-icon name="badge-check" class="h-4 w-4" /> {{ $siteSaved }}
                </div>
            @endif

            <form wire:submit="saveSiteProtection" class="space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
                <label class="flex items-start justify-between gap-4">
                    <span>
                        <span class="block text-sm font-medium text-[rgb(var(--nx-text))]">Content protection (CSP)</span>
                        <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">Blocks malicious scripts from loading. Turn off only if a trusted embed/widget you added won’t appear.</span>
                    </span>
                    <x-ui.switch wire:model="csp_enabled" label="Content protection (CSP)" class="mt-1" />
                </label>

                <label class="flex items-start justify-between gap-4 border-t border-[rgb(var(--nx-line))] pt-4">
                    <span>
                        <span class="block text-sm font-medium text-[rgb(var(--nx-text))]">Force secure connection (HSTS)</span>
                        <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">Tells browsers to always use HTTPS. Turn off only if your server isn’t on HTTPS yet.</span>
                    </span>
                    <x-ui.switch wire:model="hsts_enabled" label="Force secure connection (HSTS)" class="mt-1" />
                </label>

                <label class="flex items-start justify-between gap-4 border-t border-[rgb(var(--nx-line))] pt-4">
                    <span>
                        <span class="block text-sm font-medium text-[rgb(var(--nx-text))]">Require two-factor for admin login</span>
                        <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">Off by default — sign in with just email &amp; password. Turn on to require an authenticator code as an extra layer for everyone in the panel.</span>
                    </span>
                    <x-ui.switch wire:model="admin_2fa_required" label="Require two-factor for admin login" class="mt-1" />
                </label>

                <label class="flex items-start justify-between gap-4 border-t border-[rgb(var(--nx-line))] pt-4">
                    <span>
                        <span class="block text-sm font-medium text-[rgb(var(--nx-text))]">Bot protection on sign-in (Cloudflare Turnstile)</span>
                        <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">
                            Shows a privacy-friendly “I’m human” check on login &amp; register and verifies it server-side.
                            @if ($turnstileConfigured)
                                Site &amp; secret keys are set — flip this on to activate.
                            @else
                                <span class="text-[rgb(var(--nx-warn))]">Add the Turnstile site &amp; secret keys on the <a href="{{ route('admin.api-keys') }}" class="underline" wire:navigate>API keys</a> page first — the toggle has no effect until both are set.</span>
                            @endif
                        </span>
                    </span>
                    <x-ui.switch wire:model="turnstile_enabled" label="Bot protection on sign-in" class="mt-1" />
                </label>

                <div class="flex justify-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveSiteProtection"
                            class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveSiteProtection">Save site protection</span>
                        <span wire:loading wire:target="saveSiteProtection" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Login alert visual (content/billing/hot-menu blueprint Phase D). ONE
             consistent image, shown identically on every "new sign-in" email —
             never per-send custom media, since a varied visual in a security
             alert trains users to expect variation, making a phishing
             lookalike easier to pass off, not harder. --}}
        <div class="mt-8">
            <h2 class="mb-1 text-lg font-bold text-[rgb(var(--nx-text))]">Login alert visual</h2>
            <p class="mb-4 text-sm text-[rgb(var(--nx-text-2))]">
                One fixed image shown on every "new sign-in detected" email, so your users learn to recognise a real alert at a glance. Deliberately the same image every time — never a per-send custom upload, so a phishing lookalike with a different visual stands out.
            </p>

            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
                <div class="flex flex-wrap items-center gap-4">
                    @if ($login_alert_image_url)
                        <img src="{{ $login_alert_image_url }}" alt="Login alert visual" class="h-20 w-20 rounded-xl border border-[rgb(var(--nx-line))] object-cover">
                    @else
                        <span class="flex h-20 w-20 items-center justify-center rounded-xl border border-dashed border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text-3))]">
                            <x-icon name="shield-check" class="h-8 w-8" />
                        </span>
                    @endif

                    <form wire:submit="saveLoginAlertImage" class="flex flex-1 flex-wrap items-center gap-3">
                        <input type="file" wire:model="login_alert_image" accept="{{ \App\Support\MediaStorage::acceptAttribute() }}"
                               class="text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveLoginAlertImage,login_alert_image"
                                class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveLoginAlertImage,login_alert_image">{{ $login_alert_image_url ? 'Replace' : 'Upload' }}</span>
                            <span wire:loading wire:target="saveLoginAlertImage,login_alert_image" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                        </button>
                        @if ($login_alert_image_url)
                            <button type="button" wire:click="removeLoginAlertImage" wire:confirm="Remove the login alert image? The email reverts to text-only."
                                    class="text-sm font-medium text-[rgb(var(--nx-bad))] hover:underline">
                                Remove
                            </button>
                        @endif
                    </form>
                </div>
                @error('login_alert_image') <p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Admin access control (owner request): panel-managed IP + country
             allow-lists. Both OFF by default — sign in from anywhere until you
             deliberately restrict it. --}}
        <div class="mt-8" x-data="{
            countryOn: @js($country_allowlist_enabled), ipOn: @js($ip_allowlist_enabled),
            currentCountry: @js($currentCountry), currentIp: @js($currentIp),
            get risky() {
                let el = this.$refs;
                let countries = (el.countries?.value || '').toUpperCase();
                let ips = (el.ips?.value || '');
                let cBlock = this.countryOn && this.currentCountry && countries.trim() && !countries.includes(this.currentCountry);
                let iBlock = this.ipOn && ips.trim() && !ips.includes(this.currentIp);
                return cBlock || iBlock;
            }
        }">
            <h2 class="mb-1 text-lg font-bold text-[rgb(var(--nx-text))]">Admin access control</h2>
            <p class="mb-4 text-sm text-[rgb(var(--nx-text-2))]">Restrict which IPs or countries can reach the panel. Both are off by default. Your current location: <span class="font-medium">{{ $currentCountry ?? 'unknown' }}</span> · IP <span class="font-mono">{{ $currentIp }}</span>.</p>

            @if ($accessSaved)
                <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-ok))]">
                    <x-icon name="badge-check" class="h-4 w-4" /> {{ $accessSaved }}
                </div>
            @endif

            <form wire:submit="saveAccessControl" class="space-y-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
                {{-- Country --}}
                <div>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" wire:model="country_allowlist_enabled" x-model="countryOn" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                        <span class="text-sm font-medium text-[rgb(var(--nx-text))]">Restrict admin access by country</span>
                    </label>
                    <div class="mt-2 pl-7" x-show="countryOn" x-collapse>
                        <input type="text" wire:model="allowed_countries" x-ref="countries" placeholder="NG, GB, US"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm uppercase">
                        <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Comma-separated ISO country codes. Requires Cloudflare (or a geo backend); if a country can’t be resolved, access is allowed (never a lockout).</p>
                        @error('allowed_countries') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- IP --}}
                <div>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" wire:model="ip_allowlist_enabled" x-model="ipOn" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                        <span class="text-sm font-medium text-[rgb(var(--nx-text))]">Restrict admin access by IP address</span>
                    </label>
                    <div class="mt-2 pl-7" x-show="ipOn" x-collapse>
                        <textarea wire:model="ip_allowlist" x-ref="ips" rows="3" placeholder="One IP per line"
                                  class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 font-mono text-xs"></textarea>
                        <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">In addition to any ADMIN_IP_ALLOWLIST set in .env.</p>
                    </div>
                </div>

                {{-- Self-lockout guard --}}
                <div x-show="risky" x-cloak class="rounded-lg border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-3">
                    <label class="flex items-start gap-2 text-sm text-[rgb(var(--nx-warn))]">
                        <input type="checkbox" wire:model="lockout_ack" class="mt-0.5 h-4 w-4 rounded border-[rgb(var(--nx-warn))] text-[rgb(var(--nx-warn))] focus:ring-[rgb(var(--nx-warn))]">
                        <span>These rules would block your current location. I understand this may lock me out — I have another way in (e.g. the break-glass CLI).</span>
                    </label>
                    @error('lockout_ack') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveAccessControl"
                            class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveAccessControl">Save access rules</span>
                        <span wire:loading wire:target="saveAccessControl" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- NAARA-PROMPT-22 §3 — admin panel path. --}}
        <div class="mt-8">
            <h2 class="mb-1 text-lg font-bold text-[rgb(var(--nx-text))]">Admin panel path</h2>
            <p class="mb-4 text-sm text-[rgb(var(--nx-text-2))]">The URL segment the admin panel lives under. Currently <span class="font-mono">/{{ $admin_path }}</span>.</p>

            @if ($adminPathSaved)
                <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-ok))]">
                    <x-icon name="badge-check" class="h-4 w-4" /> {{ $adminPathSaved }}
                </div>
            @endif

            <form wire:submit="saveAdminPath" class="space-y-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
                <div class="flex items-start gap-2 rounded-lg border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-3 text-sm text-[rgb(var(--nx-warn))]">
                    <x-icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>You will need this new path to reach the admin login going forward — make sure it's recorded somewhere safe.</span>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">New path</label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-[rgb(var(--nx-text-2))]">/</span>
                        <input type="text" wire:model="admin_path" placeholder="adminmaster"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 font-mono text-sm">
                    </div>
                    @error('admin_path') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Confirm new path</label>
                    <input type="text" wire:model="admin_path_confirmation" placeholder="Type it again"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 font-mono text-sm">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text))]">Your current password</label>
                    <input type="password" wire:model="admin_path_current_password"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @error('admin_path_current_password') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveAdminPath"
                            class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveAdminPath">Change admin path</span>
                        <span wire:loading wire:target="saveAdminPath" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>

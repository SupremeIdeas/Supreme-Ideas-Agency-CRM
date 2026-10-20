{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Email settings</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        Configure how {{ config('app.name') }} sends account, order and security emails. Until you set a real
        mailer, mail is only written to the log and customers receive nothing. Settings are encrypted and apply
        immediately — no file editing.
    </p>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif
    @if ($testResult)
        <div class="mb-4 flex items-start gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $testResult }}</span>
        </div>
    @endif
    @if ($testError)
        <div class="mb-4 flex items-start gap-2 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">
            <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $testError }}</span>
        </div>
    @endif

    {{-- Where-to-get-credentials guide (the "tooltip" the operator needs). --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-4 text-sm text-[rgb(var(--nx-warn))]">
        <p class="mb-1 flex items-center gap-2 font-semibold"><x-icon name="info" class="h-4 w-4" /> Where do I get these?</p>
        <ul class="ml-6 list-disc space-y-1 text-[13px] leading-relaxed">
            <li><b>cPanel email:</b> create an email account in cPanel → Email Accounts, then use its host (mail.yourdomain.com), port 465 (SSL) or 587 (STARTTLS), the full email as username, and its password.</li>
            <li><b>Mailgun / Postmark / Resend / Brevo / SendGrid:</b> sign up, open their SMTP/credentials page, and copy the SMTP host, port, username and password they give you.</li>
            <li><b>Gmail:</b> host <code>smtp.gmail.com</code>, port 587, your Gmail address, and a Google <b>App Password</b> (not your login password).</li>
        </ul>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Mailer</label>
                    <select wire:model.live="mailer"
                            class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                        <option value="log">Log (development — sends nothing)</option>
                        <option value="smtp">SMTP (recommended — works with any provider)</option>
                        <option value="sendmail">Sendmail (local mail server on a VPS)</option>
                    </select>
                    @error('mailer') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>

                @if ($mailer === 'smtp')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">SMTP host</label>
                            <input type="text" wire:model="smtp_host" placeholder="mail.yourdomain.com" autocomplete="off"
                                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                            @error('smtp_host') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Port</label>
                            <input type="text" wire:model="smtp_port" placeholder="587" autocomplete="off"
                                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                            @error('smtp_port') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Username</label>
                            <input type="text" wire:model="smtp_username" placeholder="you@yourdomain.com" autocomplete="off"
                                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                            @error('smtp_username') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 flex items-center justify-between text-xs font-medium text-[rgb(var(--nx-text-2))]">
                                <span>Password</span>
                                @if ($passwordPreview)<span class="font-mono text-[11px] text-[rgb(var(--nx-text-2))]">saved: {{ $passwordPreview }}</span>@endif
                            </label>
                            <input type="password" wire:model="smtp_password" autocomplete="off"
                                   placeholder="{{ $passwordPreview ? 'Leave blank to keep current' : 'SMTP password' }}"
                                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                            @error('smtp_password') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Encryption</label>
                            <select wire:model="smtp_scheme"
                                    class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                                <option value="">STARTTLS (port 587)</option>
                                <option value="smtps">SSL/TLS (port 465)</option>
                            </select>
                            @if (! $pairing['ok'])
                                <p class="mt-1 text-xs text-[rgb(var(--nx-warn))]" role="alert">{{ $pairing['message'] }} <button type="button" wire:click="fixEncryption" class="underline">Fix it</button></p>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">From address</label>
                        <input type="email" wire:model="from_address" placeholder="hello@yourdomain.com"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                        @error('from_address') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">From name</label>
                        <input type="text" wire:model="from_name" placeholder="{{ config('app.name') }}"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                        @error('from_name') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Email verification enforcement (NAARA-BUILD-20 §2). --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <h2 class="mb-1 text-sm font-semibold text-[rgb(var(--nx-text))]">Email verification</h2>
            <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">How strongly to enforce confirming an email address. Only applies once a real mailer is configured above.</p>
            <div class="space-y-2">
                @foreach ([
                    'soft' => ['Soft — recommended', 'Never blocks anyone. Unverified users buy and use everything; a dismissible banner nudges them to confirm.'],
                    'hard' => ['Hard', 'Unverified users are held at the verification screen until they confirm — nothing else opens.'],
                    'off' => ['Off', 'Verification is never required or nudged, even with mail configured.'],
                ] as $val => [$label, $desc])
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 {{ $verificationMode === $val ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-cta-b)/0.05)]' : 'border-[rgb(var(--nx-line))]' }}">
                        <input type="radio" wire:model.live="verificationMode" value="{{ $val }}" class="mt-1 text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                        <span>
                            <span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $label }}</span>
                            <span class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $desc }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('verificationMode') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div><h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Deliverability check</h2><p class="text-xs text-[rgb(var(--nx-text-2))]">Looks up SPF, DKIM and DMARC for the From address's domain, so mail lands in the inbox instead of spam.</p></div>
                <button type="button" wire:click="checkDns" wire:loading.attr="disabled" wire:target="checkDns" class="nc__chip">Check DNS</button>
            </div>
            @if ($dns)
                <ul class="mt-3 space-y-1 text-sm text-[rgb(var(--nx-text))]">
                    @foreach (['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC'] as $k => $lbl)
                        <li class="flex gap-2"><b class="{{ $dns[$k]['ok'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-warn))]' }}">{{ $lbl }} {{ $dns[$k]['ok'] ? 'OK' : 'missing' }}</b><span class="text-[rgb(var(--nx-text-2))]">{{ $dns[$k]['note'] }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <button type="button" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest"
                    class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line-strong))] px-4 py-2.5 text-sm font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-60">
                <x-icon name="send" class="h-4 w-4" />
                <span wire:loading.remove wire:target="sendTest">Send test email</span>
                <span wire:loading wire:target="sendTest">Sending…</span>
            </button>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save settings
            </button>
        </div>
    </form>
</div>

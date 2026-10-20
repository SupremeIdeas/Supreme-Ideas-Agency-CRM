{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">App Builder</h1>
        <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">
            Configure and build the installable Android / iOS app. Publishing to the stores still needs the
            developer-account steps in the handoff doc — a compiled build is not a live listing.
        </p>
    </div>

    {{-- ===== App Export master switch (owner request, 2026-09-16) ========= --}}
    <div class="mb-6 flex items-center justify-between gap-4 rounded-2xl border p-5 {{ ($form['app_export_enabled'] ?? true) ? 'border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))]' : 'border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)]' }}">
        <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                <x-icon name="{{ ($form['app_export_enabled'] ?? true) ? 'shield-check' : 'x' }}" class="h-4 w-4 {{ ($form['app_export_enabled'] ?? true) ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-bad))]' }}" />
                App Export layer
            </h2>
            <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">
                @if ($form['app_export_enabled'] ?? true)
                    On — builds can be generated and the public <code>/download</code> page is reachable.
                @else
                    Closed — no new Android/iOS build can be generated, and <code>/download</code> returns a plain 404.
                    Settings below stay editable so you can switch it back on any time.
                @endif
            </p>
        </div>
        <button type="button" wire:click="toggleAppExportEnabled" wire:loading.attr="disabled" wire:target="toggleAppExportEnabled"
                role="switch" :aria-checked="'{{ ($form['app_export_enabled'] ?? true) ? 'true' : 'false' }}'"
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition {{ ($form['app_export_enabled'] ?? true) ? 'bg-[rgb(var(--nx-cta-b))]' : 'bg-[rgb(var(--nx-line-strong))]' }}">
            <span class="inline-block h-5 w-5 transform rounded-full bg-[rgb(var(--nx-surface))] shadow transition {{ ($form['app_export_enabled'] ?? true) ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
        </button>
    </div>

    {{-- ===== Publish-readiness checklist ================================= --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Publish readiness</h2>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $score['done'] === $score['total'] ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' }}">
                {{ $score['done'] }} / {{ $score['total'] }} ready
            </span>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($checklist as $group => $items)
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $group }}</p>
                    <ul class="space-y-1.5">
                        @foreach ($items as $item)
                            <li class="flex items-start gap-2 text-xs" title="{{ $item['hint'] }}">
                                @if ($item['operator'] ?? false)
                                    <x-icon name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[rgb(var(--nx-teal-ink))]" />
                                    <span class="text-[rgb(var(--nx-text-2))]">{{ $item['label'] }} <span class="text-[rgb(var(--nx-text-2))]">(you)</span></span>
                                @elseif ($item['ok'])
                                    <x-icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[rgb(var(--nx-ok))]" />
                                    <span class="text-[rgb(var(--nx-text-2))]">{{ $item['label'] }}</span>
                                @else
                                    <x-icon name="x" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[rgb(var(--nx-bad))]" />
                                    <span class="text-[rgb(var(--nx-text-2))]">{{ $item['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Blue items marked “(you)” are account/review steps only Frank can complete — see the App Export doc.</p>
    </div>

    {{-- ===== App identity ================================================= --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-4 text-sm font-semibold text-[rgb(var(--nx-text))]">App identity</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">App name</label>
                <input type="text" wire:model="form.app_name" class="{{ $inp }}">
                @error('form.app_name') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Short name</label>
                <input type="text" wire:model="form.short_name" class="{{ $inp }}">
                @error('form.short_name') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Theme colour</label>
                <input type="color" wire:model="form.theme_color" class="h-10 w-full rounded-lg border border-[rgb(var(--nx-line))]">
                @error('form.theme_color') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Background colour</label>
                <input type="color" wire:model="form.background_color" class="h-10 w-full rounded-lg border border-[rgb(var(--nx-line))]">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Version (x.y.z)</label>
                <input type="text" wire:model="form.version" placeholder="1.0.0" class="{{ $inp }}">
                @error('form.version') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Build number</label>
                <input type="number" min="1" wire:model="form.build_number" class="{{ $inp }}">
                @error('form.build_number') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Preloader</label>
                <select wire:model="form.preloader" class="{{ $inp }}">
                    @foreach ($preloaders as $p)<option value="{{ $p }}">{{ ucwords(str_replace('-', ' ', $p)) }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="mt-4">
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Release notes / changelog</label>
            <textarea wire:model="form.changelog" rows="2" class="{{ $inp }}" placeholder="What's new in this build"></textarea>
        </div>
    </div>

    {{-- ===== Assets ======================================================= --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-4 text-sm font-semibold text-[rgb(var(--nx-text))]">Icon &amp; splash</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">App icon (square, ≥512px)</label>
                @if (! empty($form['icon_url']))<img src="{{ $form['icon_url'] }}" alt="" class="mb-2 h-16 w-16 rounded-2xl object-cover">@endif
                <input type="file" wire:model="icon" accept="image/webp,image/png,image/jpeg" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                <div wire:loading wire:target="icon" class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Uploading…</div>
                @error('icon') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Splash screen</label>
                @if (! empty($form['splash_url']))<img src="{{ $form['splash_url'] }}" alt="" class="mb-2 h-16 w-28 rounded-lg object-cover">@endif
                <input type="file" wire:model="splash" accept="image/webp,image/png,image/jpeg" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                <div wire:loading wire:target="splash" class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Uploading…</div>
                @error('splash') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- ===== Signing credentials ========================================== --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-sm font-semibold text-[rgb(var(--nx-text))]">Android signing keystore</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Stored encrypted at rest. Never logged, never shown again.</p>

        @if ($hasKeystore)
            <div class="mb-3 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-3 py-2 text-sm text-[rgb(var(--nx-ok))]">
                <x-icon name="shield-check" class="h-4 w-4" />
                Keystore on file{{ ! empty($keystoreMeta['filename']) ? ' · '.$keystoreMeta['filename'] : '' }}
            </div>
            @unless ($form['keystore_backed_up'] ?? false)
                <div class="rounded-xl border border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] p-4">
                    <p class="flex items-center gap-1.5 text-sm font-semibold text-[rgb(var(--nx-bad))]"><x-icon name="shield" class="h-4 w-4" /> Back up this keystore now</p>
                    <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">
                        If you lose this file, the app can <strong>never</strong> be updated again under the same package ID —
                        you'd have to publish a brand-new listing. Download it from your CI/keystore source and store it
                        somewhere safe and permanent before continuing.
                    </p>
                    <button type="button" wire:click="acknowledgeBackup"
                        class="mt-3 rounded-full bg-[rgb(var(--nx-bad))] px-4 py-1.5 text-xs font-semibold text-white hover:opacity-90">
                        I've backed it up safely
                    </button>
                </div>
            @endunless
        @endif

        <div class="mt-3 flex items-center gap-2">
            <input type="file" wire:model="keystore" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
            <button type="button" wire:click="uploadKeystore" wire:loading.attr="disabled" wire:target="keystore,uploadKeystore"
                class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">Upload</button>
        </div>
        @error('keystore') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
    </div>

    {{-- ===== iOS signing (App Export audit §1.5/§3.5) ====================== --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-sm font-semibold text-[rgb(var(--nx-text))]">iOS signing</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">
            Stored encrypted at rest, same as the Android keystore above. Some CI providers (notably an App Store
            Connect API key) only accept these on their own dashboard rather than via API — if that's your provider,
            configure it there and check the box below instead of uploading here.
        </p>

        <div class="mb-3 grid gap-3 sm:grid-cols-2">
            <div>
                <div class="mb-2 flex items-center gap-2 text-xs {{ $hasIosCert ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-text-2))]' }}">
                    <x-icon name="{{ $hasIosCert ? 'shield-check' : 'x' }}" class="h-3.5 w-3.5" />
                    {{ $hasIosCert ? 'Certificate on file'.(! empty($iosCertMeta['filename']) ? ' · '.$iosCertMeta['filename'] : '') : 'No distribution certificate stored' }}
                </div>
                <div class="flex items-center gap-2">
                    <input type="file" wire:model="iosCert" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                    <button type="button" wire:click="uploadIosCert" wire:loading.attr="disabled" wire:target="iosCert,uploadIosCert"
                        class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">Upload</button>
                </div>
                @error('iosCert') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <div class="mb-2 flex items-center gap-2 text-xs {{ $hasIosProvisioningProfile ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-text-2))]' }}">
                    <x-icon name="{{ $hasIosProvisioningProfile ? 'shield-check' : 'x' }}" class="h-3.5 w-3.5" />
                    {{ $hasIosProvisioningProfile ? 'Provisioning profile on file'.(! empty($iosProvisioningProfileMeta['filename']) ? ' · '.$iosProvisioningProfileMeta['filename'] : '') : 'No provisioning profile stored' }}
                </div>
                <div class="flex items-center gap-2">
                    <input type="file" wire:model="iosProvisioningProfile" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                    <button type="button" wire:click="uploadIosProvisioningProfile" wire:loading.attr="disabled" wire:target="iosProvisioningProfile,uploadIosProvisioningProfile"
                        class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">Upload</button>
                </div>
                @error('iosProvisioningProfile') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="flex items-center gap-3 text-sm text-[rgb(var(--nx-text))]">
            <input type="checkbox" wire:click="toggleIosSigningOnProvider" @checked($form['ios_signing_on_provider'] ?? false) class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
            iOS signing is configured directly on my CI provider's dashboard instead
        </label>
    </div>

    {{-- ===== Store & download ============================================= --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-4 text-sm font-semibold text-[rgb(var(--nx-text))]">Store &amp; download page</h2>
        <label class="mb-3 flex items-center gap-3 text-sm text-[rgb(var(--nx-text))]">
            <input type="checkbox" wire:model="form.download_enabled" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
            Publish the public <code>/download</code> page
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3">
                <label class="flex items-center gap-2 text-sm font-medium text-[rgb(var(--nx-text))]">
                    <input type="checkbox" wire:model="form.android_store_live" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                    Google Play listing is live
                </label>
                <input type="url" wire:model="form.android_store_url" placeholder="https://play.google.com/…" class="{{ $inp }} mt-2">
                @error('form.android_store_url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3">
                <label class="flex items-center gap-2 text-sm font-medium text-[rgb(var(--nx-text))]">
                    <input type="checkbox" wire:model="form.ios_store_live" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                    App Store listing is live
                </label>
                <input type="url" wire:model="form.ios_store_url" placeholder="https://apps.apple.com/…" class="{{ $inp }} mt-2">
                @error('form.ios_store_url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>
        {{-- Android and iOS use DIFFERENT CI (owner audit, 2026-09-15 — Doc B
             Stage 1): Android always builds via this repo's own GitHub Actions
             workflow (cheap, no macOS runner); the provider picker below is
             iOS-only. Never one shared toggle for both, like the number-provider
             lanes never cross. --}}
        <div class="mt-4 rounded-xl bg-[rgb(var(--nx-surface-2))] p-4">
            <div class="mb-3 flex items-center justify-between">
                <label class="block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Android CI — GitHub Actions</label>
                <span class="flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $androidCiConfigured ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]' }}">
                    <x-icon name="{{ $androidCiConfigured ? 'shield-check' : 'x' }}" class="h-3 w-3" />
                    {{ $androidCiConfigured ? 'Configured' : 'Not configured — Android builds will fail immediately' }}
                </span>
            </div>
            <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">
                Android compiles for free on this repo's own GitHub Actions runner (<code>.github/workflows/android-build.yml</code>)
                — there's no Codemagic/paid macOS step for Android. Set the GitHub token under
                Admin → API Keys → App Export, then fill in the repo it fires against.
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">GitHub repo (owner/repo)</label>
                    <input type="text" wire:model="form.github_repo" placeholder="SupremeIdeas/NaaraSim" class="{{ $inp }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Branch</label>
                    <input type="text" wire:model="form.github_branch" placeholder="main" class="{{ $inp }}">
                </div>
            </div>
            <details class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">
                <summary class="cursor-pointer font-semibold text-[rgb(var(--nx-teal-ink))]">Where do I get the GitHub token?</summary>
                <ol class="mt-2 list-decimal space-y-1 pl-4">
                    <li>Go to <span class="font-mono">github.com/settings/personal-access-tokens/new</span> (while signed in as an account with access to this repo).</li>
                    <li>Set "Resource owner" to the repo's owner, and "Repository access" → Only select repositories → this one.</li>
                    <li>Under Permissions → Repository permissions, grant <strong>Contents: Read-only</strong> and <strong>Actions: Read and write</strong> — nothing more.</li>
                    <li>Generate, then paste the token under Admin → API Keys → App Export → "GitHub — App Export Token".</li>
                    <li>Also add these as <strong>repository secrets</strong> (Settings → Secrets and variables → Actions) so the workflow itself can sign in and upload the built app: <span class="font-mono">ANDROID_KEYSTORE_BASE64</span>, <span class="font-mono">ANDROID_STORE_PASSWORD</span>, <span class="font-mono">ANDROID_KEY_ALIAS</span>, <span class="font-mono">ANDROID_KEY_PASSWORD</span>, <span class="font-mono">APPEXPORT_CI_SECRET</span> (same value as this app's <span class="font-mono">.env</span>), and <span class="font-mono">WASABI_ACCESS_KEY_ID</span> / <span class="font-mono">WASABI_SECRET_ACCESS_KEY</span> / <span class="font-mono">WASABI_BUCKET</span> / <span class="font-mono">WASABI_ENDPOINT</span> (same Wasabi account this app already uses for storage — the workflow uploads the signed APK there so <code>/download</code> has a real, working link).</li>
                </ol>
            </details>
        </div>

        <div class="mt-4 rounded-xl bg-[rgb(var(--nx-surface-2))] p-4">
            <div class="mb-3 flex items-center justify-between">
                <label class="block text-xs font-semibold text-[rgb(var(--nx-text-2))]">iOS CI provider</label>
                <span class="flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $iosCiConfigured ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]' }}">
                    <x-icon name="{{ $iosCiConfigured ? 'shield-check' : 'x' }}" class="h-3 w-3" />
                    {{ $iosCiConfigured ? 'Configured' : 'Not configured — iOS builds will fail immediately' }}
                </span>
            </div>

            <select wire:model.live="form.ci_provider" class="{{ $inp }} mb-3">
                <option value="generic">Generic / custom webhook</option>
                <option value="codemagic">Codemagic</option>
            </select>

            @if (($form['ci_provider'] ?? 'generic') === 'codemagic')
                <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">
                    Set the Codemagic API token under Admin → API Keys → App Export. App id and workflow id come
                    from your Codemagic app's settings page — this repo's <code>codemagic.yaml</code> already
                    defines the <span class="font-mono">ios-release</span> workflow it should point at.
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">App ID</label>
                        <input type="text" wire:model="form.codemagic_app_id" placeholder="60xxxxxxxxxxxxxxxxxxxxxx" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Branch</label>
                        <input type="text" wire:model="form.codemagic_branch" placeholder="main" class="{{ $inp }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">iOS workflow ID</label>
                        <input type="text" wire:model="form.codemagic_ios_workflow_id" placeholder="ios-release" class="{{ $inp }}">
                    </div>
                </div>
                <details class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">
                    <summary class="cursor-pointer font-semibold text-[rgb(var(--nx-teal-ink))]">Where do I get these?</summary>
                    <ol class="mt-2 list-decimal space-y-1 pl-4">
                        <li>Sign in at <span class="font-mono">codemagic.io</span> and add this repo as an app — the App ID is in the URL bar once it's added.</li>
                        <li>Codemagic auto-detects the <span class="font-mono">ios-release</span> workflow from this repo's <span class="font-mono">codemagic.yaml</span> — copy its workflow ID from the workflow's settings page.</li>
                        <li>Teams → Personal Access Tokens → create one, paste it under Admin → API Keys → App Export → "Codemagic — API Token".</li>
                        <li>In the workflow's own environment variables (Codemagic dashboard, marked Secure), set <span class="font-mono">APPEXPORT_CI_SECRET</span> to the same value as this app's <span class="font-mono">.env</span> — this is what signs the build-complete callback.</li>
                        <li>Under Team settings → Code signing identities, add your Apple Developer distribution certificate + an App Store Connect API key (Apple's modern, non-interactive signing method — no manually expiring profiles to babysit).</li>
                    </ol>
                </details>
            @else
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">CI / cloud-build webhook URL</label>
                <input type="url" wire:model="form.ci_webhook_url" placeholder="https://your-ci-provider.example/webhook" class="{{ $inp }}">
            @endif
        </div>
    </div>

    {{-- ===== Download CTA placements ====================================== --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-sm font-semibold text-[rgb(var(--nx-text))]">"Download the app" placements</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Toggle where the Android download CTA appears. Each has its own label.</p>
        <div class="space-y-2">
            @foreach ($placementLabels as $key => $label)
                <div class="flex flex-wrap items-center gap-3 rounded-xl bg-[rgb(var(--nx-surface-2))] px-3 py-2">
                    <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]">
                        <input type="checkbox" wire:model="placements.{{ $key }}.active" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                        <span class="font-medium">{{ $label }}</span>
                    </label>
                    <input type="text" wire:model="placements.{{ $key }}.label" placeholder="Get the app" class="{{ $inp }} ml-auto max-w-[200px]">
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===== Store listing & compliance ================================== --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-4 text-sm font-semibold text-[rgb(var(--nx-text))]">Store listing &amp; compliance</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Privacy policy URL <span class="text-[rgb(var(--nx-bad))]">*</span></label>
                <input type="url" wire:model="form.privacy_policy_url" class="{{ $inp }}">
                @error('form.privacy_policy_url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Account deletion URL</label>
                <input type="text" wire:model="form.account_deletion_url" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Support email</label>
                <input type="email" wire:model="form.support_email" class="{{ $inp }}">
                @error('form.support_email') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Support URL</label>
                <input type="url" wire:model="form.support_url" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Category</label>
                <input type="text" wire:model="form.category" placeholder="e.g. Travel / Communication" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Content rating</label>
                <input type="text" wire:model="form.content_rating" placeholder="e.g. Everyone / 4+" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Keywords</label>
                <input type="text" wire:model="form.keywords" placeholder="comma,separated" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Android target API</label>
                <input type="number" wire:model="form.min_android_target_api" class="{{ $inp }}"></div>
        </div>
        <div class="mt-4">
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Short description</label>
            <input type="text" wire:model="form.short_description" maxlength="200" class="{{ $inp }}"></div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Full description</label>
            <textarea wire:model="form.full_description" rows="4" class="{{ $inp }}"></textarea></div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Data safety / privacy summary</label>
            <textarea wire:model="form.data_safety" rows="3" placeholder="What data the app collects and why (Google Data Safety / Apple privacy labels)." class="{{ $inp }}"></textarea></div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Permissions justification</label>
            <textarea wire:model="form.permissions_note" rows="2" class="{{ $inp }}"></textarea></div>

        {{-- App Store payments-compliance doc (BUILD-5 §6): gift cards read as
             "digital gift cards redeemed for digital goods" on iOS — gated off
             by default until a per-brand IAP determination is made. --}}
        <label class="mt-4 flex items-start gap-3 rounded-xl bg-[rgb(var(--nx-warn)/0.12)] p-3 text-sm text-[rgb(var(--nx-text))]">
            <input type="checkbox" wire:model="form.ios_gift_cards_enabled" class="mt-0.5 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
            <span>
                <span class="font-semibold">Enable Naara Gift on iOS</span>
                <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">Off by default — Apple treats digital gift cards as an In-App Purchase item. Only turn this on after confirming per-brand with Apple's current guidelines.</span>
            </span>
        </label>
    </div>

    {{-- ===== First-run onboarding slides ================================= --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">First-run onboarding</h2>
            <button type="button" wire:click="addSlide" class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">+ Slide</button>
        </div>
        <label class="mb-3 flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]">
            <input type="checkbox" wire:model="form.onboarding_enabled" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
            Show onboarding on first app open (portrait slides → login)
        </label>
        <div class="space-y-3">
            @foreach (($form['onboarding_slides'] ?? []) as $i => $slide)
                <div class="flex gap-3 rounded-xl border border-[rgb(var(--nx-line)/0.7)] p-3" wire:key="slide-{{ $i }}">
                    <div class="shrink-0">
                        @if (! empty($slide['image']))
                            <img src="{{ $slide['image'] }}" class="h-24 w-16 rounded-lg object-cover">
                        @else
                            <div class="flex h-24 w-16 items-center justify-center rounded-lg bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-3))]"><x-icon name="image" class="h-5 w-5" /></div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="text-xs font-medium text-[rgb(var(--nx-text-2))]">Slide {{ $i + 1 }} (portrait)</span>
                            <button type="button" wire:click="removeSlide({{ $i }})" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </div>
                        <input type="text" wire:model="form.onboarding_slides.{{ $i }}.title" placeholder="Title" class="{{ $inp }}">
                        <input type="text" wire:model="form.onboarding_slides.{{ $i }}.subtitle" placeholder="Subtitle" class="{{ $inp }} mt-1.5">
                        <div class="mt-1.5 flex items-center gap-2">
                            <input type="file" wire:model="slideImage" accept="image/*" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-2 file:py-1 file:text-xs file:text-[rgb(var(--nx-teal-ink))]">
                            <button type="button" wire:click="uploadSlideImage({{ $i }})" class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">Set</button>
                        </div>
                    </div>
                </div>
            @endforeach
            @if (empty($form['onboarding_slides']))
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No slides yet — add 3–4 portrait slides users swipe through on first open.</p>
            @endif
        </div>
    </div>

    <button type="button" wire:click="save" wire:loading.attr="disabled"
        class="mb-8 w-full rounded-full bg-[rgb(var(--nx-cta-b))] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
        Save app settings
    </button>

    {{-- ===== App Studio (native config surface, NAARA-BUILD-21) =========== --}}
    @include('livewire.admin.partials.app-studio', ['inp' => $inp])

    {{-- ===== Generate build =============================================== --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-sm font-semibold text-[rgb(var(--nx-text))]">Generate a build</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">
            Android compiles on a Linux CI runner. iOS requires a cloud macOS build service (set its webhook above).
        </p>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="generateBuild('android', 'apk')"
                class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-4 py-2 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">Android APK (direct install)</button>
            <button type="button" wire:click="generateBuild('android', 'aab')"
                class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-4 py-2 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">Android AAB (Play Store)</button>
            <button type="button" wire:click="generateBuild('ios', 'ipa')"
                class="rounded-full bg-[rgb(var(--nx-surface-3))] px-4 py-2 text-xs font-semibold text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-3))]">iOS IPA (macOS service)</button>
        </div>
    </div>

    {{-- ===== Build history ================================================ --}}
    {{-- Live status (App Export audit §3.3): polls ONLY while a build is
         queued/building, so Queued -> Building -> Ready/Failed updates without
         a manual refresh, and a genuinely stuck build (status never changes)
         reads visibly differently from one that's just slow. --}}
    <div class="rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5"
         x-data
         x-on:appbuild-ready.window="
            $dispatch('nx-toast', { type: 'success', message: $event.detail.label + ' is ready — downloading…' });
            const a = document.createElement('a');
            a.href = $event.detail.url;
            // Force a download rather than an in-page navigation — without this,
            // a same-origin URL the browser can render inline (confirmed live:
            // an .ico/.png artifact URL) navigates the whole admin page away
            // instead of downloading, exactly the opposite of the intent here.
            a.download = $event.detail.label.replace(/\s+/g, '-') + '.' + $event.detail.url.split('.').pop().split(/[?#]/)[0];
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            a.remove();
         "
         @if ($hasActiveBuild) wire:poll.3s="pollBuilds" @endif>
        <h2 class="mb-4 text-sm font-semibold text-[rgb(var(--nx-text))]">Build history</h2>
        @forelse ($builds as $b)
            <div class="mb-2 rounded-xl border border-[rgb(var(--nx-line)/0.7)] p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="text-sm text-[rgb(var(--nx-text))]">
                        <span class="font-semibold uppercase">{{ $b->platform }}</span> · {{ strtoupper($b->artifact_type) }} · v{{ $b->version }} ({{ $b->build_number }})
                    </div>
                    @php $tone = match($b->status){
                        'ready' => 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]',
                        'failed' => 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]',
                        'building' => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]',
                        default => 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text))]',
                    }; @endphp
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold{{ $tone }}">{{ $b->statusLabel() }}</span>
                </div>
                <div class="mt-1 flex items-center gap-3 text-xs text-[rgb(var(--nx-text-2))]">
                    <span>{{ $b->created_at->diffForHumans() }}</span>
                    @if ($b->isReady() && $b->artifact_url)
                        <a href="{{ $b->artifact_url }}" class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Download artifact</a>
                    @endif
                    @if ($b->log)
                        <details class="cursor-pointer"><summary class="text-[rgb(var(--nx-text-2))]">Build log</summary>
                            <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded bg-black/80 p-2 text-[11px] text-[rgb(var(--nx-text))]">{{ $b->log }}</pre>
                        </details>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-[rgb(var(--nx-text-2))]">No builds yet. Configure the app above, then Generate a build.</p>
        @endforelse
        <div class="mt-3">{{ $builds->links() }}</div>
    </div>
</div>

{{-- Public download page. Master shows every install route (phone apps, desktop, Chrome); a fork has no $installers and keeps just the two phone cards. --}}
@php
    $hasDesktop = ! empty($installerMeta);
    $live = $installers ?? [];
    $famLabel = ['windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux', 'chrome' => 'Chrome extension'];
    $famNote = ['windows' => 'Windows 10 or newer', 'macos' => 'macOS 11 or newer', 'linux' => 'Ubuntu, Debian, Fedora and more', 'chrome' => 'Chrome, Edge and Brave'];
    $famLogo = ['windows' => 'windows', 'macos' => 'apple', 'linux' => 'linux', 'chrome' => 'chrome'];
    $famLive = collect($families ?? [])->map(fn ($keys) => collect($keys)->filter(fn ($k) => isset($live[$k]))->values()->all())->all();
    // the one big button: the visitor's own device first, then whatever is live
    $primary = null;
    if ($device === 'android' && $androidDownloadable) { $primary = ['label' => ($c['apk_button'] ?? 'Download for Android'), 'href' => $apkUrl, 'sub' => 'Android · direct install']; }
    elseif ($device === 'android' && $androidBadge) { $primary = ['label' => ($c['play_label'] ?? 'Get it on Google Play'), 'href' => $androidStoreUrl, 'sub' => 'Android · Google Play']; }
    elseif ($device === 'ios' && $iosBadge) { $primary = ['label' => ($c['ios_label'] ?? 'Download on the App Store'), 'href' => $iosStoreUrl, 'sub' => 'iPhone and iPad · App Store']; }
    elseif ($device && ! in_array($device, ['android', 'ios'], true) && ! empty($famLive[$device])) { $k = $famLive[$device][0]; $primary = ['label' => 'Download for '.$famLabel[$device], 'href' => route('download.installer', $k), 'sub' => trim(($live[$k]->version ? 'Version '.$live[$k]->version : '').($live[$k]->humanSize() ? ' · '.$live[$k]->humanSize() : ''), ' ·')]; }
    elseif ($androidDownloadable) { $primary = ['label' => ($c['apk_button'] ?? 'Download for Android'), 'href' => $apkUrl, 'sub' => 'Android · direct install']; }
    elseif ($androidBadge) { $primary = ['label' => ($c['play_label'] ?? 'Get it on Google Play'), 'href' => $androidStoreUrl, 'sub' => 'Android · Google Play']; }
    elseif ($live) { $k = array_key_first($live); $f = $installerMeta[$k]['family']; $primary = ['label' => 'Download for '.$famLabel[$f], 'href' => route('download.installer', $k), 'sub' => '']; }
    $heroArt = asset('images/onboarding/slide-2-plan.svg');
    $steps = [
        'android' => ['Android', ['Download the app with the button above, or get it from Google Play.', 'If Android asks, allow your browser to install apps from this source. It is a one-time switch.', 'Open the file and tap Install. Naara opens ready to sign in.']],
        'ios' => ['iPhone and iPad', ['Open the App Store on your iPhone or iPad.', 'Search for '.$appName.' or use the App Store button on this page.', 'Tap Get, then open Naara and sign in.']],
        'windows' => ['Windows', ['Download the file for Windows.', 'Open it. If Windows says it protected your PC, choose More info, then Run anyway. That is normal for a new publisher.', 'Naara opens in its own window and can sit in your taskbar.']],
        'macos' => ['macOS', ['Download the file that matches your Mac: Apple silicon for M1 and later, Intel for older Macs.', 'Open it and drag Naara into Applications.', 'The first time, right-click Naara and choose Open. After that it opens like any app.']],
        'linux' => ['Linux', ['Download the AppImage, or the .deb package if you use Ubuntu or Debian.', 'AppImage: right-click, Properties, allow executing, then double-click. Package: run sudo apt install ./the-file.deb.', 'Naara appears in your app menu.']],
        'chrome' => ['Chrome extension', ['Download the extension and unzip it.', 'Open chrome://extensions, switch on Developer mode, choose Load unpacked and pick the folder.', 'Pin Naara to your toolbar. One click opens it in its own window.']],
    ];
    $faq = [
        ['Is it free?', 'Yes. Downloading and creating an account cost nothing. You pay only for the eSIM plans, numbers and calls you choose.'],
        ['Is it the same account as the website?', 'Yes. Sign in with the account you already use. Your wallet, eSIMs and numbers are all there.'],
        ['Why does my computer show a warning?', 'New apps from a new publisher get an extra check from Windows and macOS. Follow the install steps above and compare the SHA-256 below if you want to be sure the file is untouched.'],
        ['Do I need the app to use Naara?', 'No. Everything also works at '.parse_url(url('/'), PHP_URL_HOST).' in your browser. The apps add alerts, quicker sign-in and a window of their own.'],
    ];
@endphp
<x-layouts.marketing :title="'Get the '.$appName.' app'" description="Download Naara for Android, iPhone, Windows, Mac, Linux and Chrome. Free, with every file checked.">
    {{-- Hero --}}
    <section class="relative overflow-hidden px-4 pb-14 pt-12 sm:px-6 sm:pt-16">
        <div class="mx-auto grid max-w-6xl items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">Free download</p>
                <h1 class="mt-3 text-4xl font-bold leading-tight text-slate-900 sm:text-5xl dark:text-white">{{ str_replace('{app}', $appName, $c['headline'] ?? 'Get the '.$appName.' app') }}</h1>
                <p class="mt-4 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $c['subtext'] ?? '' }}</p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    @if ($primary)
                        <a href="{{ $primary['href'] }}" rel="nofollow" class="nx-btn nx-btn--primary !px-7 !py-3.5 text-base" data-primary-download><x-icon name="download" class="h-5 w-5" /> {{ $primary['label'] }}</a>
                    @else
                        <a href="{{ url('/') }}" class="nx-btn nx-btn--primary !px-7 !py-3.5 text-base">Open {{ $appName }} in your browser</a>
                    @endif
                    <a href="#all" class="nx-btn !px-6 !py-3.5 text-slate-800 dark:text-white" style="box-shadow: inset 0 0 0 1.5px rgb(100 116 139 / .5)">All downloads</a>
                </div>
                @if ($primary && $primary['sub'])<p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $primary['sub'] }}@if ($version && ! str_contains($primary['sub'], 'Version')) · Version {{ $version }}@endif</p>@endif
                <ul class="mt-8 grid gap-3 text-sm text-slate-700 sm:grid-cols-3 dark:text-slate-200">
                    <li class="flex items-center gap-2"><x-icon name="shield-check" class="h-5 w-5 shrink-0 text-primary dark:text-teal-300" /> Every file checked with SHA-256</li>
                    <li class="flex items-center gap-2"><x-icon name="globe" class="h-5 w-5 shrink-0 text-primary dark:text-teal-300" /> 190+ countries, one app</li>
                    <li class="flex items-center gap-2"><x-icon name="lock" class="h-5 w-5 shrink-0 text-primary dark:text-teal-300" /> Same account as the website</li>
                </ul>
            </div>
            <div class="relative mx-auto w-full max-w-sm">
                <div class="absolute -inset-6 rounded-[2.5rem] bg-primary/15 blur-3xl dark:bg-teal-400/10"></div>
                <img src="{{ $heroArt }}" alt="The Naara app showing a data plan" width="800" height="900" class="relative w-full rounded-[2rem] shadow-2xl ring-1 ring-black/5 dark:ring-white/10">
            </div>
        </div>
    </section>

    {{-- Every way to install --}}
    <section id="all" class="px-4 pb-16 sm:px-6">
        <div class="mx-auto max-w-6xl">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Choose your device</h2>
            <p class="mt-1 text-slate-600 dark:text-slate-300">Pick the one you are on now. You can add the others any time.</p>

            <div class="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Android --}}
                <article class="flex flex-col rounded-3xl border p-6 {{ $device === 'android' ? 'border-primary ring-2 ring-primary/30' : 'border-slate-200/70 dark:border-white/10' }} bg-white dark:bg-slate-900/60" data-card="android">
                    <header class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:text-teal-300">@include('marketing.partials.platform-logo', ['name' => 'android'])</span>
                        <div class="min-w-0 flex-1"><h3 class="font-semibold text-slate-900 dark:text-white">{{ $c['android_title'] ?? 'Android' }}</h3><p class="text-xs text-slate-500 dark:text-slate-400">Phones and tablets</p></div>
                        @if ($device === 'android')<span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary dark:text-teal-300">Your device</span>@endif
                    </header>
                    <div class="mt-5 flex-1 space-y-2">
                        @if ($androidDownloadable)
                            <a href="{{ $apkUrl }}" rel="nofollow" class="flex w-full items-center justify-center gap-2 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary-dark"><x-icon name="download" class="h-4 w-4" /> {{ $c['apk_button'] ?? 'Download APK' }}</a>
                            <p class="text-center text-xs text-slate-500 dark:text-slate-400">{{ $c['apk_note'] ?? '' }}</p>
                        @endif
                        @if ($androidBadge)
                            <a href="{{ $androidStoreUrl }}" target="_blank" rel="noopener nofollow" class="flex w-full items-center justify-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5">{{ $c['play_label'] ?? 'Get it on Google Play' }}</a>
                        @endif
                        @unless ($androidDownloadable || $androidBadge)<p class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500 dark:bg-white/5 dark:text-slate-400">{{ $c['android_soon'] ?? 'The Android app is on its way.' }}</p>@endunless
                    </div>
                </article>

                {{-- iPhone and iPad: App Store only --}}
                <article class="flex flex-col rounded-3xl border p-6 {{ $device === 'ios' ? 'border-primary ring-2 ring-primary/30' : 'border-slate-200/70 dark:border-white/10' }} bg-white dark:bg-slate-900/60" data-card="ios">
                    <header class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:text-teal-300">@include('marketing.partials.platform-logo', ['name' => 'apple'])</span>
                        <div class="min-w-0 flex-1"><h3 class="font-semibold text-slate-900 dark:text-white">{{ $c['ios_title'] ?? 'iPhone & iPad' }}</h3><p class="text-xs text-slate-500 dark:text-slate-400">App Store</p></div>
                        @if ($device === 'ios')<span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary dark:text-teal-300">Your device</span>@endif
                    </header>
                    <div class="mt-5 flex-1">
                        @if ($iosBadge)
                            <a href="{{ $iosStoreUrl }}" target="_blank" rel="noopener nofollow" class="flex w-full items-center justify-center gap-2 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900">{{ $c['ios_label'] ?? 'Download on the App Store' }}</a>
                        @else
                            <p class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500 dark:bg-white/5 dark:text-slate-400">{{ $c['ios_soon'] ?? 'The iOS app will be on the App Store soon.' }}</p>
                        @endif
                    </div>
                </article>

                {{-- Desktop and browser (master only) --}}
                @foreach ($families as $fam => $keys)
                    @php($rows = collect($keys)->filter(fn ($k) => isset($live[$k]))->values())
                    <article class="flex flex-col rounded-3xl border p-6 {{ $device === $fam ? 'border-primary ring-2 ring-primary/30' : 'border-slate-200/70 dark:border-white/10' }} bg-white dark:bg-slate-900/60" data-card="{{ $fam }}" @if ($rows->isNotEmpty()) data-installer-card="{{ $rows->first() }}" @endif>
                        <header class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:text-teal-300">@include('marketing.partials.platform-logo', ['name' => $famLogo[$fam]])</span>
                            <div class="min-w-0 flex-1"><h3 class="font-semibold text-slate-900 dark:text-white">{{ $famLabel[$fam] }}</h3><p class="text-xs text-slate-500 dark:text-slate-400">{{ $famNote[$fam] }}</p></div>
                            @if ($device === $fam)<span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary dark:text-teal-300">Your computer</span>@endif
                        </header>
                        <div class="mt-5 flex-1 space-y-2">
                            @forelse ($rows as $k)
                                @php($row = $live[$k])
                                <a href="{{ route('download.installer', $k) }}" rel="nofollow" class="flex w-full items-center justify-between gap-3 rounded-full {{ $loop->first ? 'bg-primary text-white hover:bg-primary-dark' : 'border border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5' }} px-5 py-3 text-sm font-semibold transition" data-download="{{ $k }}">
                                    <span class="flex items-center gap-2"><x-icon name="download" class="h-4 w-4" /> {{ $installerMeta[$k]['variant'] }}</span>
                                    <span class="text-xs font-normal opacity-80">{{ trim(($row->version ? 'v'.$row->version : '').($row->humanSize() ? ' · '.$row->humanSize() : ''), ' ·') }}</span>
                                </a>
                            @empty
                                <p class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500 dark:bg-white/5 dark:text-slate-400">Coming soon.</p>
                            @endforelse
                            @foreach ($rows as $k)@if ($live[$k]->notes)<p class="text-center text-xs text-slate-500 dark:text-slate-400">{{ $live[$k]->notes }}</p>@endif @endforeach
                        </div>
                        @if ($rows->isNotEmpty())
                            <details class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                                <summary class="cursor-pointer font-medium text-slate-700 dark:text-slate-200">Verify this download</summary>
                                @foreach ($rows as $k)
                                    @if ($live[$k]->sha256)
                                        <div class="mt-2" x-data="{ done: false }">
                                            <p class="font-medium">{{ $installerMeta[$k]['variant'] }} SHA-256</p>
                                            <p class="mt-1 break-all rounded-lg bg-slate-100 p-2 font-mono text-[11px] text-slate-700 dark:bg-white/5 dark:text-slate-300" x-ref="h">{{ $live[$k]->sha256 }}</p>
                                            <button type="button" class="mt-1 underline" @click="navigator.clipboard.writeText($refs.h.textContent.trim()); done = true; setTimeout(() => done = false, 1800)"><span x-show="!done">Copy</span><span x-show="done" x-cloak>Copied</span></button>
                                        </div>
                                    @endif
                                @endforeach
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Install steps --}}
    <section class="px-4 pb-16 sm:px-6" x-data="{ tab: @js(in_array($device, ['android', 'ios', 'windows', 'macos', 'linux', 'chrome'], true) ? $device : ($androidDownloadable || $androidBadge ? 'android' : array_key_first(array_filter($famLive)) ?? 'android')) }">
        <div class="mx-auto max-w-6xl">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Install in three steps</h2>
            <div class="mt-5 flex flex-wrap gap-2" role="tablist">
                @foreach ($steps as $key => [$label])
                    @continue($key === 'ios' ? false : ! in_array($key, array_merge(['android'], array_keys($families ?? [])), true))
                    <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200'" class="rounded-full px-4 py-2 text-sm font-medium transition">{{ $label }}</button>
                @endforeach
            </div>
            @foreach ($steps as $key => [$label, $list])
                <ol x-show="tab === '{{ $key }}'" x-cloak class="mt-6 grid gap-4 sm:grid-cols-3" data-steps="{{ $key }}">
                    @foreach ($list as $i => $text)
                        <li class="rounded-3xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary dark:text-teal-300">{{ $i + 1 }}</span>
                            <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            @endforeach
        </div>
    </section>

    {{-- What the app adds, on a sheet that rises over the section above --}}
    <section class="-mt-8 rounded-t-[30px] bg-slate-900 px-4 pb-16 pt-14 text-white sm:px-6 dark:bg-slate-950">
        <div class="mx-auto max-w-6xl">
            <h2 class="text-2xl font-bold">Why people keep the app</h2>
            <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([['bell', 'Alerts that arrive', 'Calls, messages and order updates, even when the app is closed.'], ['phone', 'Calls and numbers', 'Your numbers ring in the app, with the microphone and camera ready.'], ['wallet', 'One wallet', 'Top up once. Pay for eSIMs, numbers and calls from the same balance.'], ['sim', 'eSIMs in a tap', 'Scan or tap to install a plan before you land.']] as [$ic, $t, $d])
                    <div class="rounded-3xl bg-white/5 p-5 ring-1 ring-white/10">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/10 text-teal-300"><x-icon :name="$ic" class="h-5 w-5" /></span>
                        <h3 class="mt-4 font-semibold">{{ $t }}</h3>
                        <p class="mt-1 text-sm text-slate-300">{{ $d }}</p>
                    </div>
                @endforeach
            </div>

            @if ($hasDesktop)
                <div class="mt-12 rounded-3xl bg-white/5 p-6 ring-1 ring-white/10" data-verify>
                    <h3 class="flex items-center gap-2 text-lg font-semibold"><x-icon name="badge-check" class="h-5 w-5 text-teal-300" /> Check your download</h3>
                    <p class="mt-1 text-sm text-slate-300">Every file has a SHA-256 fingerprint (open Verify this download on its card). After downloading, compare it with your own:</p>
                    <div class="mt-4 grid gap-3 text-xs sm:grid-cols-3">
                        @foreach ([['Windows', 'certutil -hashfile Naara-file.zip SHA256'], ['macOS', 'shasum -a 256 Naara-file.zip'], ['Linux', 'sha256sum Naara-file.zip']] as [$os, $cmd])
                            <div><p class="font-medium text-slate-200">{{ $os }}</p><code class="mt-1 block overflow-x-auto rounded-lg bg-black/30 p-2 font-mono text-[11px] text-teal-200">{{ $cmd }}</code></div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- Questions, then the phone shortcut --}}
    <section class="px-4 py-16 sm:px-6">
        <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[1.3fr_0.7fr]">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Questions</h2>
                <div class="mt-5 divide-y divide-slate-200/70 rounded-3xl border border-slate-200/70 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-slate-900/60">
                    @foreach ($faq as [$q, $a])
                        <details class="group p-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-medium text-slate-900 dark:text-white">{{ $q }}<span class="text-slate-400 transition group-open:rotate-45">+</span></summary><p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $a }}</p></details>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-col items-center text-center">
                <div class="rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-white/10"><img src="{{ $qr }}" alt="Scan to open this download page" width="200" height="200" class="h-40 w-40"></div>
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $c['qr_caption'] ?? 'Scan to open this page on your phone' }}</p>
            </div>
        </div>
    </section>
</x-layouts.marketing>

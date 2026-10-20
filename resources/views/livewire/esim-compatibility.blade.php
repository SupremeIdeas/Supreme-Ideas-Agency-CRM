{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div>
    @if ($open)
        {{-- Best-effort client-side device detection (BUILD-8 §6.2). Self-contained
             Alpine object (no external script) so it's CSP-safe. Reads only what
             the browser honestly exposes: OS from UA/Client Hints always; an
             Android model code (mapped to a marketing name) when available; iOS
             stays generic because Safari's UA never reveals the iPhone model. --}}
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center"
             x-data="{
                 ran: false,
                 samsung: {
                     'SM-S911B':'Galaxy S23','SM-S911U':'Galaxy S23','SM-S916B':'Galaxy S23+','SM-S918B':'Galaxy S23 Ultra',
                     'SM-S921B':'Galaxy S24','SM-S926B':'Galaxy S24+','SM-S928B':'Galaxy S24 Ultra',
                     'SM-S901B':'Galaxy S22','SM-S906B':'Galaxy S22+','SM-S908B':'Galaxy S22 Ultra',
                     'SM-G991B':'Galaxy S21','SM-G998B':'Galaxy S21 Ultra','SM-G981B':'Galaxy S20',
                     'SM-F946B':'Galaxy Z Fold5','SM-F731B':'Galaxy Z Flip5'
                 },
                 norm(code) {
                     if (!code) return '';
                     if (this.samsung[code]) return this.samsung[code];
                     if (/^pixel/i.test(code)) return code;
                     if (/^sm-/i.test(code)) return '';
                     return code;
                 },
                 fromUa(ua) { const m = ua.match(/android[\s\d.]+;\s([^;)]+?)(?:\sbuild\/|\))/i); return m ? m[1].trim() : ''; },
                 run() {
                     if (this.ran) return; this.ran = true;
                     try {
                         const ua = navigator.userAgent || '';
                         const uad = navigator.userAgentData || null;
                         const plat = uad && uad.platform ? uad.platform.toLowerCase() : '';
                         let os = 'others';
                         if (/iphone|ipad|ipod/i.test(ua) || plat === 'ios') os = 'apple';
                         else if (/android/i.test(ua) || plat === 'android') os = 'android';
                         if (os === 'apple') { $wire.detected('apple', ''); return; }
                         if (os !== 'android') { $wire.detected(os, ''); return; }
                         if (uad && uad.getHighEntropyValues) {
                             uad.getHighEntropyValues(['model'])
                                 .then(v => $wire.detected('android', this.norm((v && v.model) || this.fromUa(ua))))
                                 .catch(() => $wire.detected('android', this.norm(this.fromUa(ua))));
                         } else {
                             $wire.detected('android', this.norm(this.fromUa(ua)));
                         }
                     } catch (e) {}
                 }
             }" x-init="run()"
             @keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-black/60" wire:click="close"></div>

            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-[rgb(var(--nx-surface))] shadow-2xl sm:rounded-3xl">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-[rgb(var(--nx-line))] px-5 py-4">
                    <h2 class="flex items-center gap-2 text-base font-bold text-[rgb(var(--nx-text))]">
                        <x-icon name="signal" class="h-5 w-5" gradient /> Check device compatibility
                    </h2>
                    <button wire:click="close" class="flex h-8 w-8 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]"><x-icon name="x" class="h-5 w-5" /></button>
                </div>

                {{-- EID / *#06# banner --}}
                <div class="mx-5 mt-4 rounded-xl border border-[rgb(var(--nx-teal)/0.25)] bg-[rgb(var(--nx-cta-b)/0.05)] p-3">
                    <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">The definitive check</p>
                    <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">Dial <span class="rounded bg-[rgb(var(--nx-surface))] px-1.5 py-0.5 font-mono font-bold text-[rgb(var(--nx-teal-ink))]">*#06#</span> on your device — if an <strong>EID</strong> number appears, your device supports eSIM.</p>
                </div>

                {{-- Best-effort auto-detect result (BUILD-8 §6). Advisory only —
                     the full list below stays fully interactive regardless. --}}
                @if ($detectedResult === 'yes')
                    <div class="mx-5 mt-3 flex items-start gap-2 rounded-xl border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] p-3">
                        <x-icon name="badge-check" class="mt-0.5 h-4 w-4 shrink-0 text-[rgb(var(--nx-ok))]" />
                        <p class="text-xs text-[rgb(var(--nx-text))]">We detected <strong>{{ $detectedName }}</strong> — this device supports eSIM. Not your device? Search below to confirm.</p>
                    </div>
                @elseif ($detectedResult === 'no')
                    <div class="mx-5 mt-3 flex items-start gap-2 rounded-xl border border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] p-3">
                        <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0 text-[rgb(var(--nx-bad))]" />
                        <p class="text-xs text-[rgb(var(--nx-text))]">We detected <strong>{{ $detectedName }}</strong>, which doesn’t support eSIM. Search below if this isn’t your device.</p>
                    </div>
                @elseif ($detectedResult === 'ios')
                    <div class="mx-5 mt-3 flex items-start gap-2 rounded-xl border border-[rgb(var(--nx-teal)/0.25)] bg-[rgb(var(--nx-cta-b)/0.05)] p-3">
                        <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-[rgb(var(--nx-teal-ink))]" />
                        <p class="text-xs text-[rgb(var(--nx-text))]">Looks like an <strong>iPhone</strong>. Most iPhones from XR/XS onward support eSIM — search your exact model below to confirm.</p>
                    </div>
                @elseif ($detectedResult === 'unknown')
                    <div class="mx-5 mt-3 flex items-start gap-2 rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] p-3">
                        <x-icon name="help-circle" class="mt-0.5 h-4 w-4 shrink-0 text-[rgb(var(--nx-text-2))]" />
                        <p class="text-xs text-[rgb(var(--nx-text))]">We detected <strong>{{ $detectedName }}</strong> but couldn’t confirm it. Use the <span class="font-mono">*#06#</span> check above, or search below.</p>
                    </div>
                @endif

                {{-- Search --}}
                <div class="px-5 pt-4">
                    <div class="relative">
                        <x-icon name="search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[rgb(var(--nx-text-2))]" />
                        <input type="text" wire:model.live.debounce.250ms="search" placeholder="Search your device…"
                               class="w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] py-2.5 pl-9 pr-3 text-sm">
                    </div>
                </div>

                {{-- OS pill tabs --}}
                <div class="flex gap-2 px-5 pt-3">
                    @foreach (['apple' => 'Apple', 'android' => 'Android', 'others' => 'Others'] as $key => $label)
                        <button wire:click="setOs('{{ $key }}')"
                                class="flex-1 rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $os === $key ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Device accordions --}}
                <div class="mt-3 flex-1 overflow-y-auto px-5 pb-5" x-data="{ openCat: null }" wire:key="cats-{{ $os }}">
                    @forelse ($grouped as $category => $devices)
                        <div class="mt-2 overflow-hidden rounded-xl border border-[rgb(var(--nx-line))]" x-data>
                            <button type="button" @click="openCat = (openCat === '{{ $category }}' ? null : '{{ $category }}')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-[rgb(var(--nx-text))]">
                                <span>{{ $categoryLabels[$category] ?? ucfirst($category) }} <span class="ml-1 text-xs font-normal text-[rgb(var(--nx-text-2))]">{{ $devices->count() }}</span></span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-[rgb(var(--nx-text-2))] transition" ::class="openCat === '{{ $category }}' ? 'rotate-90' : ''" />
                            </button>
                            <div x-show="openCat === '{{ $category }}'" x-collapse>
                                <ul class="divide-y divide-[rgb(var(--nx-line))] border-t border-[rgb(var(--nx-line))]">
                                    @foreach ($devices as $d)
                                        <li class="flex items-center gap-2 px-4 py-2 text-sm text-[rgb(var(--nx-text-2))]">
                                            <x-icon name="badge-check" class="h-3.5 w-3.5 shrink-0 text-[rgb(var(--nx-ok))]" /> {{ $d->device_name }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center text-sm text-[rgb(var(--nx-text-2))]">
                            No matching device. Try the <span class="font-mono">*#06#</span> check above, or another spelling.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>

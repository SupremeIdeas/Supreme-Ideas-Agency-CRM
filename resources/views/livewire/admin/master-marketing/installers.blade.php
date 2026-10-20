{{-- Admin > Installers (MASTER ONLY). Skin tokens only. --}}
<div class="mx-auto max-w-5xl" data-installers-admin>
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Installers</h1>
    <p class="mb-5 text-sm text-[rgb(var(--nx-text-2))]">
        Everything people can install, in one place. Drop your files below and Naara sorts them, joins any numbered parts, checks the checksums and puts them on the
        <a href="{{ route('download') }}" target="_blank" class="font-medium underline">download page</a>. No limit worry: files travel in small slices, so this works on any host.
    </p>

    @if ($status || session('status'))
        <div class="mb-5 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]"><x-icon name="badge-check" class="h-4 w-4" /> {{ $status ?? session('status') }}</div>
    @endif
    @error('installer')
        <div class="mb-5 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]"><x-icon name="x" class="h-4 w-4" /> {{ $message }}</div>
    @enderror

    {{-- Are all slots from the same release? --}}
    @if ($release['latest'])
        @if ($release['aligned'])
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]" data-release-aligned><x-icon name="badge-check" class="h-4 w-4" /> Every file on the download page is version {{ $release['latest'] }}.</div>
        @else
            <div class="mb-4 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]" data-release-stale>
                <p class="flex items-center gap-2 font-medium"><x-icon name="x" class="h-4 w-4" /> Not every file is from the same release. The newest is {{ $release['latest'] }}.</p>
                <p class="mt-1 text-xs">Older: @foreach ($release['stale'] as $k){{ $platforms[$k]['label'] }} ({{ $release['versions'][$k] }}){{ $loop->last ? '' : ', ' }}@endforeach. Upload the matching {{ $release['latest'] }} files for those.</p>
            </div>
        @endif
    @endif

    {{-- Smart drop zone: sorts, joins, verifies and publishes in one go --}}
    <section x-data="installerDrop(@js($dropConfig))" class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" data-drop-zone>
        <div @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false; add($event.dataTransfer.files)"
             :class="over ? 'border-[rgb(var(--nx-cta-b))] bg-[rgb(var(--nx-cta-b)/0.08)]' : 'border-[rgb(var(--nx-line-strong))]'"
             class="flex flex-col items-center gap-2 rounded-xl border-2 border-dashed p-8 text-center transition">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[rgb(var(--nx-cta-b)/0.12)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="download" class="h-6 w-6 rotate-180" /></span>
            <p class="text-base font-semibold text-[rgb(var(--nx-text))]">Drop your installers here</p>
            <p class="max-w-xl text-xs text-[rgb(var(--nx-text-2))]">Drop everything from the installers folder at once: the installers, the numbered parts (.part00, .part01) and the SHA256SUMS files. Parts are joined and every checksum is checked before anything goes live.</p>
            <label class="mt-1 cursor-pointer rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">
                Choose files<input type="file" multiple class="hidden" @change="add($event.target.files); $event.target.value = ''">
            </label>
        </div>

        <template x-if="items.length">
            <div class="mt-5" data-drop-review>
                <div x-show="manifest" class="mb-3 rounded-lg p-3 text-sm" :class="missing.length ? 'bg-[rgb(var(--nx-bad)/0.1)] text-[rgb(var(--nx-bad))]' : 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]'" data-drop-manifest>
                    <span x-text="manifest ? 'Release ' + (manifest.release || '') + ': ' + (manifest.files || []).length + ' files listed in the manifest, ' + items.filter(i => i.listed).length + ' recognised in your drop.' : ''"></span>
                    <span x-show="missing.length" x-text="' Missing from your drop: ' + missing.join(', ') + '.'"></span>
                </div>
                <div class="space-y-2">
                    <template x-for="(it, i) in items" :key="it.key">
                        <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3" :data-drop-item="it.base">
                            <div class="flex flex-wrap items-center gap-3">
                                <select x-model="it.platform" :disabled="busy" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                                    <option value="">Choose where it goes</option>
                                    @foreach ($dropConfig['labels'] as $dKey => $dLabel)<option value="{{ $dKey }}">{{ $dLabel }}</option>@endforeach
                                </select>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-[rgb(var(--nx-text))]" x-text="it.base"></p>
                                    <p class="text-xs text-[rgb(var(--nx-text-2))]"><span x-text="human(it.size)"></span><span x-show="it.files.length > 1"> · <span x-text="it.files.length"></span> parts to join</span> · <span x-text="it.sha ? 'checksum found, will verify' : 'no checksum given'"></span></p>
                                </div>
                                <input type="text" x-model="it.version" :disabled="busy" placeholder="Version" class="w-24 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                                <label class="flex items-center gap-1.5 text-xs text-[rgb(var(--nx-text))]"><input type="checkbox" x-model="it.publish" :disabled="busy" class="rounded"> Publish</label>
                                <button type="button" x-show="!busy && it.state !== 'done'" @click="items.splice(i, 1)" class="text-xs text-[rgb(var(--nx-text-2))] underline" aria-label="Remove">Remove</button>
                            </div>
 <p x-show="it.warn" class="mt-2 text-xs text-[rgb(var(--nx-bad))]" x-text="it.warn"></p>
                            <p x-show="clash(it)" class="mt-2 text-xs text-[rgb(var(--nx-warn,var(--nx-bad)))]" x-text="'Another file is also going to this slot. Only one can be the download, so the last one sent wins.'"></p>
                            <div x-show="it.state !== 'ready'" class="mt-3">
                                <div class="h-1.5 overflow-hidden rounded-full bg-[rgb(var(--nx-surface-3))]"><div class="h-full rounded-full transition-all" :class="it.state === 'failed' ? 'bg-[rgb(var(--nx-bad))]' : 'bg-[rgb(var(--nx-cta-b))]'" :style="'width:' + it.pct + '%'"></div></div>
                                <p class="mt-1 text-xs" :class="it.state === 'failed' ? 'text-[rgb(var(--nx-bad))]' : (it.state === 'done' ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-text-2))]')" x-text="it.message"></p>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" @click="run()" :disabled="busy || !canRun" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60" data-drop-run>
                        <span x-show="!busy" x-text="'Join, verify and upload ' + items.filter(i => i.state !== 'done').length + ' file(s)'"></span><span x-show="busy">Working...</span>
                    </button>
                    <button type="button" x-show="!busy" @click="items = []" class="text-sm text-[rgb(var(--nx-text-2))] underline">Clear</button>
                    <p x-show="ignored.length" class="text-xs text-[rgb(var(--nx-text-2))]" x-text="'Not installers, ignored: ' + ignored.join(', ')"></p>
                </div>
            </div>
        </template>
    </section>

    <div class="grid gap-5 md:grid-cols-2">
        @foreach ($platforms as $key => $p)
            @php($row = $rows[$key])
            <section wire:key="inst-{{ $key }}" class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" data-installer="{{ $key }}">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]"><x-icon name="{{ $p['icon'] }}" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> {{ $p['label'] }}</h2>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $row?->isLive() ? 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]' }}">{{ $row?->isLive() ? 'Live' : 'Not shown' }}</span>
                </div>

                @if ($row?->file_path)
                    <p class="mb-2 break-all text-xs text-[rgb(var(--nx-text-2))]">{{ $row->file_name }} · {{ $row->humanSize() }} · {{ $row->downloads }} downloads<br>SHA-256 {{ $row->sha256 }}@if (! empty($row->checks))<br>Checked inside: {{ collect($row->checks)->map(fn ($val, $k) => match ($k) { 'arch' => 'CPU '.$val, 'signing' => 'signing: '.$val, 'version' => 'manifest v'.$val, default => $val })->implode(' · ') }}@endif</p>
                    <button type="button" wire:click="removeFile('{{ $key }}')" wire:confirm="Remove the uploaded file?" class="mb-3 text-xs font-medium text-[rgb(var(--nx-bad))] underline">Remove file</button>
                @endif

                <details class="mb-3"><summary class="cursor-pointer text-xs font-medium text-[rgb(var(--nx-text-2))]">Upload by hand instead</summary>
                <form method="POST" action="{{ route('admin.installers.upload', $key) }}" enctype="multipart/form-data" class="my-3 flex flex-wrap items-center gap-2" data-upload-form>
                    @csrf
                    <input type="file" name="installer" accept=".{{ implode(',.', $p['ext']) }}" class="max-w-full text-xs text-[rgb(var(--nx-text-2))]">
                    <button type="submit" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Upload</button>
                </form>

                <form method="POST" action="{{ route('admin.installers.upload', $key) }}" enctype="multipart/form-data" class="mb-3 rounded-lg border border-dashed border-[rgb(var(--nx-line-strong))] p-2" data-parts-form>
                    @csrf
                    <p class="mb-1 text-xs text-[rgb(var(--nx-text-2))]">File came in numbered parts (.part00, .part01 ...)? Select all of them. They are joined here.</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="file" name="parts[]" multiple class="max-w-full text-xs text-[rgb(var(--nx-text-2))]">
                        <input type="text" name="expected_sha256" placeholder="SHA-256 to check (optional)" class="w-44 rounded border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-xs text-[rgb(var(--nx-text))]">
                        <button type="submit" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1 text-xs font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Join and upload</button>
                    </div>
                </form>
                </details>

                <div class="space-y-2">
                    <input type="text" wire:model="form.{{ $key }}.external_url" placeholder="or paste a link (https://...)" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                    @error("form.$key.external_url")<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" wire:model="form.{{ $key }}.version" placeholder="Version, e.g. 1.0.0" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                        <input type="text" wire:model="form.{{ $key }}.min_os" placeholder="{{ $p['hint'] }}" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                    </div>
                    <textarea wire:model="form.{{ $key }}.notes" rows="2" placeholder="Short note shown under the button (optional)" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]"></textarea>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="form.{{ $key }}.is_published" class="rounded"> Published</label>
                        <button type="button" wire:click="save('{{ $key }}')" wire:loading.attr="disabled" wire:target="save('{{ $key }}')" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-1.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Save</button>
                    </div>
                </div>
            </section>
        @endforeach

        {{-- Android + iOS: the same settings App Export uses --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 md:col-span-2" data-installer="mobile">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]"><x-icon name="phone" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Android and iOS</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-medium text-[rgb(var(--nx-text-2))]">Android file (.apk or .aab)</p>
                    @if ($apkUrl)<p class="mb-2 break-all text-xs text-[rgb(var(--nx-text-2))]">Current: {{ $apkUrl }}</p>@endif
                    <form method="POST" action="{{ route('admin.installers.upload', 'android') }}" enctype="multipart/form-data" class="mb-3 flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="file" name="installer" accept=".apk,.aab" class="max-w-full text-xs text-[rgb(var(--nx-text-2))]">
                        <button type="submit" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Upload</button>
                    </form>
                    <label class="mb-3 flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="androidApkOn" class="rounded"> Show the direct download</label>
                    <input type="text" wire:model="androidStoreUrl" placeholder="Google Play listing URL" class="mb-2 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                    <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="androidStoreLive" class="rounded"> Play listing is live</label>
                </div>
                <div>
                    <p class="mb-2 text-xs font-medium text-[rgb(var(--nx-text-2))]">iOS (App Store only; iOS has no direct download)</p>
                    <input type="text" wire:model="iosStoreUrl" placeholder="App Store listing URL" class="mb-2 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                    <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="iosStoreLive" class="rounded"> App Store listing is live</label>
                    <button type="button" wire:click="saveStores" wire:loading.attr="disabled" wire:target="saveStores" class="mt-4 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-1.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Save Android and iOS</button>
                </div>
            </div>
        </section>
    </div>

<script>
    function installerDrop(cfg) {
        const SUMS = /sha-?256/i;
        return {
            items: [], ignored: [], sums: {}, manifest: null, missing: [], over: false, busy: false, platformLabels: cfg.labels,
            get canRun() { return this.items.length > 0 && this.items.every((i) => i.platform) && this.items.some((i) => i.state !== 'done'); },
            human(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; },
            clash(it) { return !!it.platform && this.items.filter((o) => o.platform === it.platform).length > 1; },
            async add(list) {
                const files = Array.from(list), manifestFile = files.find((f) => /release-manifest.*\.json$/i.test(f.name)), sumFiles = files.filter((f) => SUMS.test(f.name) || /\.txt$/i.test(f.name)), rest = files.filter((f) => !sumFiles.includes(f) && f !== manifestFile);
                if (manifestFile) { try { this.manifest = JSON.parse(await manifestFile.text()); for (const m of this.manifest.files || []) if (m.sha256) this.sums[m.name] = String(m.sha256).toLowerCase(); } catch (e) { this.ignored.push(manifestFile.name + ' (not readable)'); } }
                for (const f of sumFiles) {
                    for (const line of (await f.text()).split(/\r?\n/)) {
                        const m = line.trim().match(/^([A-Fa-f0-9]{64})\s+\*?(.+)$/);
                        if (m) this.sums[m[2].trim()] = m[1].toLowerCase();
                    }
                }
                const res = await fetch(cfg.planUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, Accept: 'application/json' }, body: JSON.stringify({ names: rest.map((f) => f.name) }) });
                const plan = (await res.json()).files || {};
                const groups = {};
                for (const f of rest) {
                    const g = plan[f.name];
                    if (!g || !g.platform) { this.ignored.push(f.name); continue; }
                    (groups[g.base] ||= { base: g.base, platform: g.platform, version: g.version || '', files: [] }).files.push(f);
                }
                for (const g of Object.values(groups)) {
                    g.files.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true }));
                    const key = g.base + ':' + g.files.length;
                    if (this.items.some((i) => i.key === key)) continue;
                    this.items.push({ key, base: g.base, platform: g.platform, version: g.version, publish: true, files: g.files, size: g.files.reduce((n, f) => n + f.size, 0), sha: this.sums[g.base] || '', state: 'ready', pct: 0, message: '' });
                }
                for (const it of this.items) if (!it.sha) it.sha = this.sums[it.base] || '';
                if (this.manifest) {
                    const listed = new Map((this.manifest.files || []).map((m) => [m.name, m]));
                    for (const it of this.items) {
                        const m = listed.get(it.base);
                        it.listed = !!m;
                        if (m) { if (m.platform) it.platform = m.platform; if (m.version) it.version = m.version; it.warn = ''; }
                        else it.warn = 'This file is not in the release manifest. Check it is the right file.';
                    }
                    this.missing = [...listed.keys()].filter((n) => !this.items.some((i) => i.base === n));
                }
            },
            async post(url, body) {
                for (let attempt = 1; ; attempt++) {
                    try {
                        const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.csrf, Accept: 'application/json' }, body });
                        if (r.status === 422 || r.ok) return { status: r.status, json: await r.json().catch(() => ({})) };
                        throw new Error('HTTP ' + r.status);
                    } catch (e) { if (attempt >= 3) throw e; await new Promise((ok) => setTimeout(ok, 800 * attempt)); }
                }
            },
            async send(it) {
                const id = (crypto.randomUUID ? crypto.randomUUID() : String(Math.random())).replace(/[^A-Za-z0-9]/g, '').slice(0, 32).padEnd(16, '0');
                const chunkUrl = cfg.chunkUrl.replace('__P__', it.platform), finalUrl = cfg.finalizeUrl.replace('__P__', it.platform);
                let index = 0, sent = 0;
                const t0 = Date.now();
                it.state = 'sending'; it.pct = 0; it.message = 'Starting...';
                for (const f of it.files) {
                    for (let off = 0; off < f.size || (f.size === 0 && off === 0); off += cfg.chunkBytes) {
                        const fd = new FormData();
                        fd.append('upload_id', id); fd.append('index', index++); fd.append('chunk', f.slice(off, off + cfg.chunkBytes), 'slice');
                        const r = await this.post(chunkUrl, fd);
                        if (r.status !== 200) throw new Error(r.json.message || 'A slice was refused.');
                        sent += Math.min(cfg.chunkBytes, f.size - off);
                        it.pct = Math.min(99, Math.round((sent / it.size) * 100));
                        it.message = 'Sending ' + this.human(sent) + ' of ' + this.human(it.size) + ' (' + (sent / 1048576 / Math.max(1, (Date.now() - t0) / 1000)).toFixed(1) + ' MB/s)';
                        if (f.size === 0) break;
                    }
                }
                it.state = 'joining'; it.message = it.files.length > 1 ? 'Joining ' + it.files.length + ' parts and checking the checksum...' : 'Checking the file...';
                const fd = new FormData();
                fd.append('upload_id', id); fd.append('total', index); fd.append('name', it.base); fd.append('publish', it.publish ? '1' : '0');
                if (it.version) fd.append('version', it.version);
                if (it.sha) fd.append('expected_sha256', it.sha);
                let r = await this.post(finalUrl, fd);
                if (!r.json.ok && r.json.needs_confirm) {
                    if (!window.confirm(r.json.message + '\n\nUpload it anyway?')) throw new Error('Skipped: ' + r.json.message);
                    fd.set('force', '1');
                    r = await this.post(finalUrl, fd);
                }
                if (!r.json.ok) { const e = new Error(r.json.message || 'The server could not finish this file.'); e.sha = r.json.sha256; throw e; }
                it.pct = 100; it.state = 'done';
                it.message = r.json.message + (r.json.verified ? ' Checksum matched (' + r.json.sha256.slice(0, 12) + '...).' : ' SHA-256 ' + r.json.sha256.slice(0, 12) + '...') + (r.json.facts && r.json.facts.arch ? ' CPU ' + r.json.facts.arch + '.' : '') + ((r.json.warnings || []).length ? ' Note: ' + r.json.warnings.join(' ') : '');
            },
            async run() {
                this.busy = true;
                for (const it of this.items) {
                    if (it.state === 'done') continue;
                    try { await this.send(it); } catch (e) { it.state = 'failed'; it.message = e.message + (e.sha ? ' (the joined file hashes to ' + e.sha.slice(0, 12) + '...)' : ''); }
                }
                this.busy = false;
                if (this.items.every((i) => i.state === 'done')) this.$wire.$refresh();
            },
        };
    }
</script>
</div>

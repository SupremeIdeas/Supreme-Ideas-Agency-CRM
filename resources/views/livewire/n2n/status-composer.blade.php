{{-- nx:converted --}}
{{-- Add a status (MASTER-ONLY). Modes mirror the familiar status composer: Video, Photo, Text, Voice. The text screen takes any colour. Skin tokens only apart from the user-picked backdrop. --}}
<div x-data="{ open: false, mode: 'text', rec: null, chunks: [], secs: 0, timer: null, fontMap: { sans: 'system-ui, sans-serif', serif: 'Georgia, serif', mono: 'ui-monospace, monospace', rounded: 'ui-rounded, \'Nunito\', system-ui', script: 'cursive', bold: 'system-ui' },
        async startRec() { try { const s = await navigator.mediaDevices.getUserMedia({ audio: true }); this.chunks = []; this.rec = new MediaRecorder(s); this.rec.ondataavailable = e => this.chunks.push(e.data); this.rec.onstop = () => { s.getTracks().forEach(t => t.stop()); const b = new Blob(this.chunks, { type: this.rec.mimeType }); const f = new File([b], 'voice.' + (this.rec.mimeType.includes('mp4') ? 'm4a' : 'webm'), { type: this.rec.mimeType }); $wire.set('seconds', this.secs); $wire.set('mode', 'voice'); $wire.upload('file', f, () => $wire.postFile()); }; this.rec.start(); this.secs = 0; this.timer = setInterval(() => { this.secs++; if (this.secs >= 90) this.stopRec(); }, 1000); } catch (e) { $wire.set('error', 'Microphone permission is needed to record.'); } },
        stopRec() { clearInterval(this.timer); if (this.rec && this.rec.state !== 'inactive') this.rec.stop(); this.rec = null; },
        cancelRec() { clearInterval(this.timer); if (this.rec) { this.rec.onstop = null; this.rec.stream.getTracks().forEach(t => t.stop()); this.rec.stop(); } this.rec = null; this.secs = 0; } }"
     @status-posted.window="open = false" @keydown.escape.window="open = false; cancelRec()">
    @vite('resources/js/n2n/status-camera.js')
    <button type="button" @click="open = true" class="ns-cta ns-cta--pill ns-cta--sm" data-tour="n2n-status-add"><x-nx.icon name="plus" /> Add status</button>
    @if (count($kinds) > 0)
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">{{ $limit < 0 ? 'Unlimited statuses' : $used.' of '.$limit.' statuses used in 24 hours' }}. Each one is deleted after 24 hours.</p>
    @endif

    <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex flex-col" :style="mode === 'text' ? 'background:{{ $bg }}' : ''" :class="mode === 'text' ? '' : 'bg-[rgb(var(--nx-canvas))]'" role="dialog" aria-modal="true" aria-label="Add status">
        <div class="flex items-center justify-between p-3">
            <button type="button" @click="open = false; cancelRec()" class="nn__iconbtn" aria-label="Close"><x-nx.icon name="x" /></button>
            {{-- nx:allow:start the status backdrop is the colour the member picked, so the controls over it stay white --}}
            <label x-show="mode === 'text'" class="nn__iconbtn cursor-pointer text-white" aria-label="Pick any colour"><x-nx.icon name="palette" /><input type="color" wire:model.live="bg" class="sr-only"></label>
            {{-- nx:allow:end --}}
        </div>

        @if ($error)<p class="mx-3 rounded-xl bg-[rgb(var(--nx-bad)/0.15)] px-3 py-2 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif

        {{-- Text --}}
        <div x-show="mode === 'text'" class="flex min-h-0 flex-1 flex-col">
            <div class="relative flex min-h-0 flex-1 flex-col" x-data="nxMention({ people: @js($mentionables), max: {{ \App\Services\N2N\Updates\StatusService::MAX_MENTIONS }} })" @status-posted.window="reset()" data-status-mention>
            {{-- nx:allow:start text over the chosen backdrop is white for contrast --}}
            <textarea x-ref="field" @input="onInput()" @keydown="onKey($event)" @click="onInput()" wire:model.live.debounce.300ms="text" maxlength="{{ $maxChars }}" rows="4" placeholder="Type a status" aria-label="Status text"
                      :style="'font-family:' + fontMap['{{ $font }}'] + ';' + ('{{ $font }}' === 'bold' ? 'font-weight:800' : '')"
                      class="min-h-0 flex-1 resize-none border-0 bg-transparent px-6 text-center text-3xl leading-snug text-white placeholder-white/60 outline-none ring-0 focus:border-0 focus:outline-none focus:ring-0"></textarea>
            {{-- nx:allow:end --}}
                <ul x-show="open" x-cloak class="absolute inset-x-3 bottom-2 z-10 max-h-48 overflow-y-auto rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-1 shadow-lg" role="listbox" aria-label="Tag someone">
                    <template x-for="(p, i) in items" :key="p.id"><li><button type="button" role="option" :aria-selected="i === idx" @mousedown.prevent="pick(p)" @touchstart.prevent="pick(p)" :class="i === idx ? 'bg-[rgb(var(--nx-surface-3))]' : ''" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-[rgb(var(--nx-text))]"><x-nx.icon name="at-sign" class="h-4 w-4" /><span x-text="p.name"></span></button></li></template>
                </ul>
            </div>
            <div class="space-y-3 bg-black/25 p-3">
                <div class="flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Background colour">
                    @foreach ($palette as $c)
                        {{-- nx:allow:start swatches show the real colour --}}
                        <button type="button" wire:click="$set('bg', '{{ $c }}')" style="background: {{ $c }}" class="h-8 w-8 shrink-0 rounded-full border-2 {{ strtoupper($bg) === $c ? 'border-white' : 'border-transparent' }}" aria-label="Colour {{ $c }}" aria-pressed="{{ strtoupper($bg) === $c ? 'true' : 'false' }}"></button>
                        {{-- nx:allow:end --}}
                    @endforeach
                </div>
                <div class="flex gap-2 overflow-x-auto" role="group" aria-label="Font">
                    @foreach ($fonts as $f)
                        <button type="button" wire:click="$set('font', '{{ $f }}')" aria-pressed="{{ $font === $f ? 'true' : 'false' }}" :style="'font-family:' + fontMap['{{ $f }}']" class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold {{ $font === $f ? 'bg-white text-black' : 'bg-white/20 text-white' }}">Aa</button>
                    @endforeach
                </div>
                <div class="flex justify-end">
                    <button type="button" wire:click="postText" wire:loading.attr="disabled" wire:target="postText" @disabled(trim($text) === '') class="ns-cta ns-cta--pill ns-cta--gold"><span wire:loading.remove wire:target="postText">Post</span><span wire:loading wire:target="postText">Posting...</span></button>
                </div>
            </div>
        </div>

        {{-- Photo / Video: the in-app camera (with a gallery fallback), then a review step with a caption. Nothing is uploaded until Post. --}}
        <div x-show="mode === 'image' || mode === 'video'" x-cloak class="flex min-h-0 flex-1 flex-col" data-status-camera
             x-data="nxStatusCam({ camera: {{ $cameraOn ? 'true' : 'false' }}, maxSeconds: {{ \App\Support\N2N\N2NStatusSettings::maxSeconds('video') }}, maxBytes: {{ \App\Support\N2N\N2NStatusSettings::maxBytes('video') }}, denied: 'Camera permission is needed. You can choose from your gallery instead.', noRecord: 'This browser cannot record video here. Choose a video from your gallery instead.', tooBig: 'That video is too large. Record a shorter one.', uploadFailed: 'The upload did not finish. Try again.' })"
             x-effect="(open && (mode === 'image' || mode === 'video')) ? ((!stream && !shot && !err) ? start(mode) : (kind !== mode && !shot && start(mode))) : stop()"
             @status-posted.window="discard(); stop()">
            {{-- review --}}
            <div x-show="shot" class="flex min-h-0 flex-1 flex-col" x-data="nxMention({ people: @js($mentionables), max: {{ \App\Services\N2N\Updates\StatusService::MAX_MENTIONS }} })" @status-posted.window="reset()">
                <div class="relative flex min-h-0 flex-1 items-center justify-center bg-black">
                    <img x-show="kind === 'image'" :src="shotUrl" alt="Your photo" class="max-h-full max-w-full object-contain">
                    <video x-show="kind === 'video'" :src="shotUrl" controls playsinline class="max-h-full max-w-full"></video>
                </div>
                <div class="relative space-y-2 bg-[rgb(var(--nx-surface))] p-3">
                    <ul x-show="open" x-cloak class="absolute inset-x-3 bottom-full z-10 mb-1 max-h-48 overflow-y-auto rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-1 shadow-lg" role="listbox" aria-label="Tag someone">
                        <template x-for="(p, i) in items" :key="p.id"><li><button type="button" role="option" :aria-selected="i === idx" @mousedown.prevent="pick(p)" @touchstart.prevent="pick(p)" :class="i === idx ? 'bg-[rgb(var(--nx-surface-3))]' : ''" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-[rgb(var(--nx-text))]"><x-nx.icon name="at-sign" class="h-4 w-4" /><span x-text="p.name"></span></button></li></template>
                    </ul>
                    <label class="sr-only" for="st-caption">Caption</label>
                    <input id="st-caption" x-ref="field" type="text" maxlength="200" placeholder="Add a caption{{ $mentionsOn ? ' (type @ to tag someone)' : '' }}" @input="onInput()" @keydown="onKey($event)" class="w-full rounded-full border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-4 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="retake()" class="ns-cta ns-cta--pill ns-cta--ghost">Retake</button>
                        <button type="button" @click="post($refs.field.value)" :disabled="busy" wire:loading.attr="disabled" wire:target="file,postFile" class="ns-cta ns-cta--pill ns-cta--gold"><span x-show="!busy">Post</span><span x-show="busy" x-cloak>Posting...</span></button>
                    </div>
                </div>
            </div>
            {{-- live camera --}}
            <div x-show="!shot" class="flex min-h-0 flex-1 flex-col">
                <div class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden bg-black">
                    <video x-ref="live" x-show="ready" playsinline muted autoplay :style="facing === 'user' ? 'transform:scaleX(-1)' : ''" class="h-full w-full object-cover"></video>
                    <div x-show="!ready" class="p-6 text-center text-sm text-[rgb(var(--nx-text-2))]">
                        <p x-show="!hasCam">{{ $cameraOn ? 'This browser has no camera available here.' : 'The camera is not offered right now.' }} Choose from your gallery.</p>
                        <p x-show="hasCam && !err" x-cloak>Starting the camera...</p>
                        <p x-show="err" x-cloak x-text="err" role="alert" class="text-[rgb(var(--nx-bad))]"></p>
                    </div>
                    <span x-show="rec" x-cloak class="absolute left-3 top-3 rounded-full bg-black/60 px-3 py-1 text-sm tabular-nums text-white" role="timer" x-text="clock() + ' / 0:{{ \App\Support\N2N\N2NStatusSettings::maxSeconds('video') }}'"></span>
                </div>
                <div class="flex items-center justify-between gap-3 bg-[rgb(var(--nx-surface))] p-3">
                    <span class="w-12" aria-hidden="true"></span>
                    <button type="button" x-show="mode === 'image'" @click="snap()" :disabled="!ready" class="grid h-16 w-16 place-items-center rounded-full border-4 border-[rgb(var(--nx-text))] disabled:opacity-40" aria-label="Take photo"><span class="h-11 w-11 rounded-full bg-[rgb(var(--nx-text))]"></span></button>
                    <button type="button" x-show="mode === 'video'" x-cloak @click="toggleRec()" :disabled="!ready" class="grid h-16 w-16 place-items-center rounded-full border-4 border-[rgb(var(--nx-bad))] disabled:opacity-40" :aria-label="rec ? 'Stop recording' : 'Record video'"><span :class="rec ? 'h-6 w-6 rounded-md' : 'h-11 w-11 rounded-full'" class="bg-[rgb(var(--nx-bad))] transition-all"></span></button>
                    <span class="flex items-center gap-1">
                      <label class="nn__iconbtn cursor-pointer" aria-label="Choose from gallery"><x-nx.icon name="image" /><input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" @change="pickFile($event)"></label>
                      <button type="button" @click="flip()" :disabled="!hasCam || rec" class="nn__iconbtn disabled:opacity-40" aria-label="Switch camera"><x-nx.icon name="refresh" /></button>
                    </span>
                </div>
            </div>
            <div wire:loading wire:target="file,postFile" class="bg-[rgb(var(--nx-surface))] px-3 pb-3 text-center text-sm text-[rgb(var(--nx-text-2))]" role="status">Uploading...</div>
        </div>

        {{-- Voice --}}
        <div x-show="mode === 'voice'" x-cloak class="flex min-h-0 flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
            <p class="text-3xl font-semibold tabular-nums text-[rgb(var(--nx-text))]" x-text="Math.floor(secs/60) + ':' + String(secs%60).padStart(2,'0')" aria-live="off"></p>
            <button type="button" x-show="!rec" @click="startRec()" class="ns-cta ns-cta--pill"><x-nx.icon name="mic" /> Record</button>
            <div x-show="rec" class="flex gap-3">
                <button type="button" @click="cancelRec()" class="ns-cta ns-cta--pill ns-cta--ghost">Cancel</button>
                <button type="button" @click="stopRec()" class="ns-cta ns-cta--pill ns-cta--gold">Stop and post</button>
            </div>
            <div wire:loading wire:target="file,postFile" class="text-sm text-[rgb(var(--nx-text-2))]" role="status">Uploading...</div>
        </div>

        <div class="flex items-center justify-center gap-2 bg-[rgb(var(--nx-surface))] p-3" style="padding-bottom: calc(12px + env(safe-area-inset-bottom))" role="tablist" aria-label="Status type">
            @foreach (['video' => 'Video', 'image' => 'Photo', 'text' => 'Text', 'voice' => 'Voice'] as $k => $label)
                @if (in_array($k === 'image' ? 'image' : $k, $kinds, true))
                    <button type="button" role="tab" @click="mode = '{{ $k }}'; cancelRec()" :aria-selected="mode === '{{ $k }}'" :class="mode === '{{ $k }}' ? 'bg-[rgb(var(--nx-surface-3))] font-semibold' : ''" class="rounded-full px-4 py-2 text-sm text-[rgb(var(--nx-text))]">{{ $label }}</button>
                @endif
            @endforeach
        </div>
    </div>

    <div class="mt-3">
        <button type="button" wire:click="$toggle('audienceOpen')" class="text-xs font-medium text-[rgb(var(--nx-teal-ink))]" aria-expanded="{{ $audienceOpen ? 'true' : 'false' }}">Who can see my status{{ count($hidden) ? ' ('.count($hidden).' hidden)' : '' }}</button>
        @if ($audienceOpen)
            <div class="mt-2 rounded-xl border border-[rgb(var(--nx-line))] p-3">
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Everyone you are connected with sees your status. Tick anyone you want to hide it from; they are not told.</p>
                <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto">
                    @forelse ($audience as $id => $name)<li wire:key="aud-{{ $id }}"><label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="hidden" value="{{ $id }}"> {{ $name }}</label></li>@empty<li class="text-xs text-[rgb(var(--nx-text-2))]">You have no connections yet.</li>@endforelse
                </ul>
                <button type="button" wire:click="saveAudience" wire:loading.attr="disabled" wire:target="saveAudience" class="ns-cta ns-cta--pill ns-cta--sm mt-2">Save</button>
            </div>
        @endif
    </div>

    @if ($mine->isNotEmpty())
        <ul class="mt-3 space-y-1" aria-label="My active status updates">
            @foreach ($mine as $p)
                <li wire:key="my-{{ $p->id }}" class="rounded-xl border border-[rgb(var(--nx-line))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <div class="flex items-center justify-between gap-2">
                        <span class="min-w-0 truncate">{{ ucfirst($p->kind) }}{{ $p->body ? ': '.$p->body : '' }} <small class="text-[rgb(var(--nx-text-2))]">ends {{ $p->expires_at->diffForHumans() }}</small></span>
                        <span class="flex shrink-0 items-center gap-3">
                            <button type="button" wire:click="toggleViewers({{ $p->id }})" class="text-xs font-medium text-[rgb(var(--nx-teal-ink))]" aria-expanded="{{ $viewersFor === $p->id ? 'true' : 'false' }}" data-status-views>{{ (int) ($views[$p->id] ?? 0) }} {{ \Illuminate\Support\Str::plural('view', (int) ($views[$p->id] ?? 0)) }}</button>
                            <button type="button" wire:click="remove({{ $p->id }})" wire:confirm="Delete this status now?" wire:loading.attr="disabled" wire:target="remove({{ $p->id }})" class="text-[rgb(var(--nx-bad))]" aria-label="Delete status"><x-nx.icon name="trash" /></button>
                        </span>
                    </div>
                    @if ($viewersFor === $p->id)
                        <ul class="mt-2 space-y-1 border-t border-[rgb(var(--nx-line))] pt-2" aria-label="Who viewed this status">
                            @forelse ($viewers as $v)<li class="flex items-center justify-between text-xs" wire:key="v-{{ $p->id }}-{{ $loop->index }}"><span>{{ $v['name'] }}</span><span class="text-[rgb(var(--nx-text-2))]">{{ $v['emoji'] }} {{ \Illuminate\Support\Carbon::createFromTimestamp($v['at'])->diffForHumans() }}</span></li>@empty<li class="text-xs text-[rgb(var(--nx-text-2))]">Nobody has opened it yet.</li>@endforelse
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>

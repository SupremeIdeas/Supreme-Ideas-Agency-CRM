{{-- Resumable video upload control for the White Label page (MASTER-ONLY). Included by the Site Editor for the `video_file` field. $section / $field name the Livewire
     properties it fills (sections.{section}.{field}); the poster frame goes to sections.{section}.poster_image. Logic: resources/js/master-marketing/uploader.js --}}
@php
    $page ??= 'mm';
    $section ??= 'hero';
    $sections ??= [];
    $cfg = [
        'urls' => [
            'init' => route('admin.mm-video.init'),
            'chunk' => route('admin.mm-video.chunk', ['id' => '__ID__', 'n' => '__N__']),
            'complete' => route('admin.mm-video.complete', ['id' => '__ID__']),
            'abort' => route('admin.mm-video.abort', ['id' => '__ID__']),
            'poster' => route('admin.mm-video.poster'),
            'forget' => route('admin.mm-video.forget'),
        ],
        'csrf' => csrf_token(),
        'maxMb' => \App\Support\MasterMarketing\VideoSource::maxMb(),
        'current' => (string) $value,
        'poster' => (string) ($posterCurrent ?? ($sections[$section]['poster_image'] ?? '')),
        'props' => $propsOverride ?? ['file' => "sections.{$section}.{$field}", 'source' => "sections.{$section}.video_source", 'poster' => "sections.{$section}.poster_image"],
    ];
    $currentUrl = \App\Support\SiteContent::imageUrl((string) $value);
@endphp
<div wire:key="vu-{{ $page }}-{{ $section }}" wire:ignore class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-4"
     x-data="mmVideoUploader(Object.assign(@js($cfg), { wire: (p, v) => $wire.$set(p, v, false) }))">
    <div class="mb-3 flex items-center justify-between gap-3">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Video file</span>
        <span class="text-[11px] text-[rgb(var(--nx-text-2))]">MP4, WebM or MOV, up to {{ $cfg['maxMb'] }} MB</span>
    </div>

    {{-- The video that is on the page now --}}
    <template x-if="current && status !== 'ready' && status !== 'uploading' && status !== 'paused'">
        <div class="mb-3 overflow-hidden rounded-xl border border-[rgb(var(--nx-line))] bg-black">
            <video :src="current" controls preload="metadata" playsinline class="aspect-video w-full"></video>
            <div class="flex items-center justify-between gap-3 bg-[rgb(var(--nx-surface))] p-3 text-xs">
                <span class="truncate text-[rgb(var(--nx-text-2))]">Playing on the page now</span>
                <button type="button" @click="removeCurrent()" class="font-semibold text-[rgb(var(--nx-bad))] hover:underline">Remove video</button>
            </div>
        </div>
    </template>

    {{-- Drop zone --}}
    <label x-show="status === 'idle' || status === 'done' || (status === 'error' && !file)" @dragover.prevent="drag = true" @dragleave.prevent="drag = false" @drop.prevent="drop($event)"
           :class="drag ? 'border-[rgb(var(--nx-cta-b))] bg-[rgb(var(--nx-cta-b)/0.08)]' : 'border-[rgb(var(--nx-line-strong))]'"
           class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition">
        <x-icon name="upload" class="h-7 w-7 text-[rgb(var(--nx-teal-ink))]" />
        <span class="text-sm font-semibold text-[rgb(var(--nx-text))]" x-text="current ? 'Replace the video' : 'Drag a video here, or choose a file'"></span>
        <span class="text-xs text-[rgb(var(--nx-text-2))]">Landscape 16:9, up to 1080p, H.264 MP4 plays everywhere. Large files upload in pieces and resume if your connection drops.</span>
        <input type="file" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" class="sr-only" @change="choose($event)">
    </label>

    <p x-show="error" x-cloak x-text="error" class="mt-3 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-xs text-[rgb(var(--nx-bad))]" role="alert"></p>

    {{-- A chosen file: preview, poster frame, facts --}}
    <div x-show="file && status !== 'done'" x-cloak class="mt-3 space-y-3">
        <div class="overflow-hidden rounded-xl bg-black">
            <video x-ref="local" :src="previewUrl" muted playsinline preload="auto" @loadedmetadata="meta()" @seeked="grab()" class="aspect-video w-full object-contain" controls></video>
        </div>
        <dl class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
            <div class="rounded-lg bg-[rgb(var(--nx-surface))] p-2"><dt class="text-[rgb(var(--nx-text-2))]">File</dt><dd class="truncate font-semibold text-[rgb(var(--nx-text))]" x-text="name"></dd></div>
            <div class="rounded-lg bg-[rgb(var(--nx-surface))] p-2"><dt class="text-[rgb(var(--nx-text-2))]">Size</dt><dd class="font-semibold text-[rgb(var(--nx-text))]" x-text="bytes(size)"></dd></div>
            <div class="rounded-lg bg-[rgb(var(--nx-surface))] p-2"><dt class="text-[rgb(var(--nx-text-2))]">Length</dt><dd class="font-semibold text-[rgb(var(--nx-text))]" x-text="time(duration)"></dd></div>
            <div class="rounded-lg bg-[rgb(var(--nx-surface))] p-2"><dt class="text-[rgb(var(--nx-text-2))]">Picture</dt><dd class="font-semibold text-[rgb(var(--nx-text))]" x-text="width ? width + ' x ' + height : '...'"></dd></div>
        </dl>
        <template x-for="w in warnings()" :key="w"><p class="rounded-lg bg-[rgb(var(--nx-warn)/0.12)] p-2 text-xs text-[rgb(var(--nx-text))]" x-text="w"></p></template>

        <div x-show="status === 'ready' || status === 'error'" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
            <p class="text-xs font-semibold text-[rgb(var(--nx-text))]">Poster image</p>
            <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">The still shown before anyone presses play. Drag to the frame you like.</p>
            <div class="mt-2 flex items-center gap-3">
                <img x-show="posterPreview" :src="posterPreview" alt="" class="h-16 w-28 shrink-0 rounded-lg border border-[rgb(var(--nx-line))] object-cover">
                <input type="range" min="0" :max="duration || 1" step="0.1" x-model="posterTime" @input.debounce.150ms="seekPoster()" class="w-full accent-[rgb(var(--nx-cta-b))]" aria-label="Poster frame">
            </div>
        </div>

        {{-- Progress --}}
        <div x-show="status === 'uploading' || status === 'paused'" x-cloak class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
            <div class="flex items-center justify-between text-xs font-semibold text-[rgb(var(--nx-text))]"><span x-text="status === 'paused' ? 'Paused' : 'Uploading'"></span><span x-text="pct + '%'"></span></div>
            <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-[rgb(var(--nx-surface-3))]" role="progressbar" :aria-valuenow="pct" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-gradient-to-r from-[rgb(var(--nx-cta-b))] to-[rgb(var(--nx-cta-a))] transition-all duration-300" :style="`width:${pct}%`"></div>
            </div>
            <p class="mt-2 text-[11px] text-[rgb(var(--nx-text-2))]"><span x-text="bytes(sent) + ' of ' + bytes(size)"></span><span x-show="rate()"> · <span x-text="rate()"></span></span><span x-show="eta > 1 && status === 'uploading'"> · about <span x-text="time(eta)"></span> left</span></p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" x-show="status === 'ready' || status === 'error'" @click="start()" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]"><span x-text="status === 'error' ? 'Retry upload' : 'Upload video'"></span></button>
            <button type="button" x-show="status === 'uploading'" @click="pause()" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-4 py-2 text-xs font-semibold text-[rgb(var(--nx-text))]">Pause</button>
            <button type="button" x-show="status === 'paused'" @click="resume()" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-xs font-semibold text-white">Resume</button>
            <button type="button" x-show="status === 'uploading' || status === 'paused'" @click="cancel()" class="rounded-lg px-3 py-2 text-xs font-semibold text-[rgb(var(--nx-bad))] hover:underline">Cancel</button>
            <button type="button" x-show="status === 'ready' || status === 'error'" @click="reset()" class="rounded-lg px-3 py-2 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:underline">Choose another</button>
        </div>
    </div>

    {{-- Done --}}
    <div x-show="status === 'done'" x-cloak class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-[rgb(var(--nx-ok)/0.12)] p-3 text-xs text-[rgb(var(--nx-ok))]" role="status">
        <span class="font-semibold">{{ empty($propsOverride) ? 'Uploaded and attached to the page. It goes live when you save.' : 'Uploaded. Fill in the title and press Add video to put it on the page.' }}</span>
        @if (empty($propsOverride))<button type="button" @click="$wire.save()" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 font-semibold text-white">Save page now</button>@endif
    </div>
</div>

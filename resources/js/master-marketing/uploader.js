// MASTER-ONLY. The resumable video uploader in Admin > Marketing site > White Label > Video (resources/views/livewire/admin/partials/video-uploader.blade.php).
// The file is read locally first (so the admin can scrub it and pick a poster frame before anything is sent), then sent in slices of the size the server asked
// for. Each slice is retried with back-off, the same file picked again resumes where it stopped, and the server checks the assembled file by its magic bytes.
const CHUNK_RETRIES = 5;

function register(Alpine) {
    Alpine.data('mmVideoUploader', (cfg) => ({
        cfg,
        status: 'idle',            // idle | ready | uploading | paused | done | error
        file: null, name: '', size: 0, previewUrl: '', duration: 0, width: 0, height: 0,
        pct: 0, sent: 0, speed: 0, eta: 0, error: '', drag: false, current: cfg.current || '',
        posterTime: 0, posterPreview: '', posterBlob: null, posterUrl: cfg.poster || '', posterBusy: false,
        _xhr: null, _id: null, _paused: false, _cancel: false, _samples: [],

        // ---- choosing a file ----------------------------------------------------------------------------------------------------------
        choose(e) { const f = (e.target.files || [])[0]; if (f) { this.load(f); } e.target.value = ''; },
        drop(e) { this.drag = false; const f = (e.dataTransfer.files || [])[0]; if (f) { this.load(f); } },
        load(f) {
            this.error = '';
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            if (!['mp4', 'webm', 'mov'].includes(ext)) { return this.fail('Please choose an MP4, WebM or MOV video.'); }
            if (f.size > this.cfg.maxMb * 1048576) { return this.fail('That video is ' + this.bytes(f.size) + ', over the ' + this.cfg.maxMb + ' MB limit. Compress it, or paste a YouTube or Vimeo link instead.'); }
            if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); }
            this.file = f; this.name = f.name; this.size = f.size; this.pct = 0; this.sent = 0; this.posterBlob = null; this.posterPreview = '';
            this.previewUrl = URL.createObjectURL(f); this.status = 'ready';
            this.$nextTick(() => { const v = this.$refs.local; if (v) { v.load(); } });
        },
        meta() {
            const v = this.$refs.local; this.duration = v.duration || 0; this.width = v.videoWidth; this.height = v.videoHeight;
            this.posterTime = Math.min(1, (this.duration || 1) * 0.1); this.seekPoster();
        },
        seekPoster() { const v = this.$refs.local; if (v) { v.currentTime = Number(this.posterTime); } },
        grab() {
            const v = this.$refs.local; if (!v || !v.videoWidth) { return; }
            const w = Math.min(1280, v.videoWidth), h = Math.round(w * v.videoHeight / v.videoWidth);
            const c = document.createElement('canvas'); c.width = w; c.height = h; c.getContext('2d').drawImage(v, 0, 0, w, h);
            this.posterPreview = c.toDataURL('image/jpeg', 0.82); c.toBlob((b) => { this.posterBlob = b; }, 'image/jpeg', 0.86);
        },
        warnings() {
            const w = [];
            if (this.width && this.height / this.width > 1.05) { w.push('This is a portrait video. It will play inside a landscape frame, so it may show black bars; a landscape (16:9) video looks best.'); }
            if (this.duration > 600) { w.push('Videos under about 5 minutes keep visitors watching.'); }
            if (this.size > 200 * 1048576) { w.push('A large file loads slowly on mobile data. Consider compressing it to around 1080p.'); }
            return w;
        },

        // ---- upload -------------------------------------------------------------------------------------------------------------------
        async start() {
            if (!this.file) { return; }
            this.error = ''; this._cancel = false; this._paused = false; this.status = 'uploading'; this._samples = [];
            this._t = window.nxUpload ? window.nxUpload.track({ key: 'mm-video', name: this.file.name, size: this.file.size, cancel: () => this.cancel() }) : null;
            try {
                const init = await this.api('POST', this.cfg.urls.init, JSON.stringify({ name: this.file.name, size: this.file.size, mtime: this.file.lastModified }), 'application/json');
                this._id = init.id; const chunk = init.chunk; const total = Math.ceil(this.file.size / chunk); const have = new Set(init.received || []);
                this.sent = 0; for (const i of have) { this.sent += Math.min(chunk, this.file.size - i * chunk); }
                this.progress();
                for (let i = 0; i < total; i++) {
                    if (have.has(i)) { continue; }
                    while (this._paused && !this._cancel) { await new Promise((r) => setTimeout(r, 250)); }
                    if (this._cancel) { return; }
                    await this.sendChunk(i, this.file.slice(i * chunk, Math.min(this.file.size, (i + 1) * chunk)), chunk);
                }
                if (this._t) { this._t.processing('Checking and saving the video'); }
                const done = await this.api('POST', this.url(this.cfg.urls.complete), '{}', 'application/json');
                this.current = done.preview || done.url; this.cfg.wire(this.cfg.props.file, done.url); this.cfg.wire(this.cfg.props.source, 'upload');
                if (this.posterBlob) { await this.sendPoster(); }
                this.status = 'done'; this.pct = 100; if (this._t) { this._t.done(); }
            } catch (e) { if (!this._cancel) { this.fail(e.message || 'The upload stopped.'); } }
        },
        async sendChunk(i, blob, chunk) {
            let attempt = 0;
            for (;;) {
                try {
                    await new Promise((resolve, reject) => {
                        const x = new XMLHttpRequest(); this._xhr = x; x.open('PUT', this.url(this.cfg.urls.chunk).replace('__N__', i));
                        x.setRequestHeader('X-CSRF-TOKEN', this.cfg.csrf); x.setRequestHeader('Accept', 'application/json'); x.setRequestHeader('Content-Type', 'application/octet-stream');
                        const base = this.sent;
                        x.upload.onprogress = (ev) => { this.sent = base + ev.loaded; this.progress(); };
                        x.onload = () => { if (x.status >= 200 && x.status < 300) { this.sent = base + blob.size; this.progress(); resolve(); } else { reject(new Error(this.msg(x))); } };
                        x.onerror = () => reject(new Error('Connection lost.')); x.onabort = () => reject(new Error('aborted'));
                        x.send(blob);
                    });
                    return;
                } catch (e) {
                    if (this._cancel) { throw e; }
                    if (++attempt > CHUNK_RETRIES) { throw new Error('The connection kept dropping. Press Upload again to resume from ' + this.pct + '%.'); }
                    this.sent = Math.max(0, this.sent - blob.size); await new Promise((r) => setTimeout(r, 600 * 2 ** attempt));
                }
            }
        },
        async sendPoster() {
            const fd = new FormData(); fd.append('poster', this.posterBlob, 'poster.jpg');
            const r = await fetch(this.cfg.urls.poster, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.cfg.csrf, Accept: 'application/json' }, body: fd });
            if (r.ok) { const j = await r.json(); this.posterUrl = j.url; this.cfg.wire(this.cfg.props.poster, j.url); }
        },
        progress() {
            this.pct = this.size ? Math.min(99, Math.floor((this.sent / this.size) * 100)) : 0;
            if (this._t) { this._t.progress(this.pct, this.sent); }
            const now = Date.now(); this._samples.push([now, this.sent]); this._samples = this._samples.filter((s) => now - s[0] < 4000);
            if (this._samples.length > 1) { const a = this._samples[0], b = this._samples[this._samples.length - 1]; const dt = (b[0] - a[0]) / 1000; this.speed = dt > 0 ? (b[1] - a[1]) / dt : 0; this.eta = this.speed > 0 ? (this.size - this.sent) / this.speed : 0; }
        },
        pause() { this._paused = true; this.status = 'paused'; if (this._xhr) { this._xhr.abort(); } },
        resume() { this._paused = false; this.start(); },
        async cancel() {
            this._cancel = true; if (this._t) { this._t.cancelled(); } if (this._xhr) { this._xhr.abort(); }
            if (this._id) { try { await this.api('DELETE', this.url(this.cfg.urls.abort)); } catch (e) {} }
            this.status = this.file ? 'ready' : 'idle'; this.pct = 0; this.sent = 0; this._id = null;
        },
        async removeCurrent() {
            if (!this.current || !confirm('Remove this video from the page? The file is deleted too.')) { return; }
            try { await this.api('POST', this.cfg.urls.forget, JSON.stringify({ url: this.current }), 'application/json'); } catch (e) {}
            this.current = ''; this.cfg.wire(this.cfg.props.file, ''); this.status = 'idle'; this.file = null;
        },
        reset() { this.status = 'idle'; this.file = null; this.previewUrl = ''; this.error = ''; },

        // ---- plumbing -----------------------------------------------------------------------------------------------------------------
        url(t) { return t.replace('__ID__', this._id); },
        async api(method, url, body, type) {
            const h = { 'X-CSRF-TOKEN': this.cfg.csrf, Accept: 'application/json' }; if (type) { h['Content-Type'] = type; }
            const r = await fetch(url, { method, headers: h, body });
            const j = await r.json().catch(() => ({})); if (!r.ok) { throw new Error(j.message || 'The server refused that (' + r.status + ').'); } return j;
        },
        msg(x) { try { return JSON.parse(x.responseText).message || 'The server refused that piece.'; } catch (e) { return 'The server refused that piece (' + x.status + ').'; } },
        fail(m) { if (this._t) { this._t.fail(m); } this.error = m; if (this.status === 'uploading' || this.status === 'paused') { this.status = 'error'; } },
        bytes(n) { return n >= 1073741824 ? (n / 1073741824).toFixed(2) + ' GB' : n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; },
        time(s) { s = Math.max(0, Math.round(s || 0)); const m = Math.floor(s / 60); return m + ':' + String(s % 60).padStart(2, '0'); },
        rate() { return this.speed > 0 ? this.bytes(this.speed) + '/s' : ''; },
    }));
}

if (window.Alpine) { register(window.Alpine); } else { document.addEventListener('alpine:init', () => register(window.Alpine)); }

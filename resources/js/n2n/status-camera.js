// Naara Chat status (MASTER-ONLY): the in-app camera and the @mention picker used by the Add status screen.
// nxStatusCam: live preview with a front/back switch, a shutter for photos and tap-to-record video (up to the server limit), a review step with a caption, and the
// gallery picker as the fallback whenever the browser has no camera or permission is refused. Nothing is uploaded until the member taps Post.
// nxMention: typing "@" in the text or the caption lists the people who can be tagged; picking one inserts "@Name" and remembers the id (the server re-checks every id).
const MIMES = ['video/mp4;codecs=avc1', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];

function register(Alpine) {
    Alpine.data('nxStatusCam', (cfg) => ({
        hasCam: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && cfg.camera,
        stream: null, ready: false, err: '', facing: 'environment', kind: 'image', shot: null, shotUrl: '', secs: 0, rec: null, timer: null, chunks: [], busy: false,

        async start(kind) {
            this.kind = kind; this.stopStream(); this.discard();
            if (!this.hasCam) { return; }
            this.err = '';
            const video = { facingMode: { ideal: this.facing }, width: { ideal: 1280 }, height: { ideal: 1280 } };
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video, audio: kind === 'video' });
            } catch (e1) {
                try { this.stream = await navigator.mediaDevices.getUserMedia({ video, audio: false }); } catch (e2) { this.err = cfg.denied; this.ready = false; return; }
            }
            const el = this.$refs.live; if (el) { el.srcObject = this.stream; el.play?.().catch(() => {}); }
            this.ready = true;
        },
        stopStream() { if (this.stream) { this.stream.getTracks().forEach((t) => t.stop()); } this.stream = null; this.ready = false; },
        stop() { this.stopRec(true); this.stopStream(); },
        async flip() { this.facing = this.facing === 'environment' ? 'user' : 'environment'; await this.start(this.kind); },
        discard() { const u = this.shotUrl; this.shot = null; this.shotUrl = ''; this.secs = 0; this.err = ''; if (u) { setTimeout(() => URL.revokeObjectURL(u), 800); } },
        retake() { this.discard(); if (!this.stream) { this.start(this.kind); } },

        snap() {
            const v = this.$refs.live; if (!v || !v.videoWidth) { return; }
            const max = 1600; const k = Math.min(1, max / Math.max(v.videoWidth, v.videoHeight));
            const c = document.createElement('canvas'); c.width = Math.round(v.videoWidth * k); c.height = Math.round(v.videoHeight * k);
            c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
            c.toBlob((b) => { if (b) { this.setShot(new File([b], 'status.jpg', { type: 'image/jpeg' }), 0); } }, 'image/jpeg', 0.9);
        },
        toggleRec() { this.rec ? this.stopRec(false) : this.startRec(); },
        startRec() {
            if (!this.stream || typeof MediaRecorder === 'undefined') { this.err = cfg.noRecord; return; }
            const mime = MIMES.find((m) => MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(m)) || '';
            this.chunks = [];
            try { this.rec = new MediaRecorder(this.stream, mime ? { mimeType: mime } : undefined); } catch (e) { this.err = cfg.noRecord; return; }
            const type = this.rec.mimeType || mime || 'video/webm';
            this.rec.ondataavailable = (e) => { if (e.data && e.data.size) { this.chunks.push(e.data); } };
            this.rec.onstop = () => {
                clearInterval(this.timer);
                if (this._cancel) { this._cancel = false; this.rec = null; this.secs = 0; return; }
                const blob = new Blob(this.chunks, { type }); const secs = this.secs; this.rec = null;
                if (blob.size > cfg.maxBytes) { this.err = cfg.tooBig; this.secs = 0; return; }
                this.setShot(new File([blob], 'status.' + (type.includes('mp4') ? 'mp4' : 'webm'), { type: type.split(';')[0] }), secs);
            };
            this.secs = 0; this.rec.start(250);
            this.timer = setInterval(() => { this.secs++; if (this.secs >= cfg.maxSeconds) { this.stopRec(false); } }, 1000);
        },
        stopRec(cancel) { clearInterval(this.timer); if (this.rec && this.rec.state !== 'inactive') { this._cancel = !!cancel; this.rec.stop(); } else { this.rec = null; } },

        setShot(file, secs) {
            this.discard(); this.shot = file; this.secs = secs || 0; this.shotUrl = URL.createObjectURL(file);
            this.kind = file.type.startsWith('video') ? 'video' : 'image';
            this.stopStream();
        },
        pickFile(e) {
            const f = e.target.files && e.target.files[0]; e.target.value = ''; if (!f) { return; }
            if (f.type.startsWith('video')) {
                const v = document.createElement('video'); v.preload = 'metadata'; const u = URL.createObjectURL(f);
                v.onloadedmetadata = () => { const s = Math.round(v.duration || 0); URL.revokeObjectURL(u); this.setShot(f, isFinite(s) ? s : 0); };
                v.onerror = () => { URL.revokeObjectURL(u); this.setShot(f, 0); }; v.src = u;
            } else { this.setShot(f, 0); }
        },
        post(caption) {
            if (!this.shot || this.busy) { return; }
            this.busy = true; const file = this.shot; const wire = this.$wire;
            wire.set('mode', this.kind, false); wire.set('seconds', this.kind === 'video' && this.secs ? this.secs : null, false); wire.set('caption', caption || '', false);
            wire.upload('file', file, () => { wire.postFile().finally(() => { this.busy = false; }); }, () => { this.busy = false; this.err = cfg.uploadFailed; });
        },
        clock() { return Math.floor(this.secs / 60) + ':' + String(this.secs % 60).padStart(2, '0'); },
    }));

    Alpine.data('nxMention', (cfg) => ({
        people: cfg.people || [], ids: [], open: false, items: [], idx: 0, range: null,
        field() { return this.$refs.field; },
        onInput() {
            if (!this.people.length) { return; }
            const el = this.field(); const pos = el.selectionStart ?? el.value.length; const before = el.value.slice(0, pos);
            const m = before.match(/(^|\s)@([\p{L}\p{N}_.\- ]{0,24})$/u);
            if (!m) { this.close(); return; }
            const q = m[2].toLowerCase(); this.range = { start: pos - m[2].length - 1, end: pos };
            this.items = this.people.filter((p) => p.name.toLowerCase().includes(q)).slice(0, 6); this.idx = 0; this.open = this.items.length > 0;
        },
        onKey(e) {
            if (!this.open) { return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); this.idx = (this.idx + 1) % this.items.length; }
            else if (e.key === 'ArrowUp') { e.preventDefault(); this.idx = (this.idx - 1 + this.items.length) % this.items.length; }
            else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); this.pick(this.items[this.idx]); }
            else if (e.key === 'Escape') { e.stopPropagation(); this.close(); }
        },
        pick(p) {
            if (!p || !this.range) { return; }
            const el = this.field(); const v = el.value; const ins = '@' + p.name + ' ';
            el.value = v.slice(0, this.range.start) + ins + v.slice(this.range.end);
            const at = this.range.start + ins.length; el.setSelectionRange(at, at); el.focus();
            el.dispatchEvent(new Event('input', { bubbles: true }));
            if (!this.ids.includes(p.id) && this.ids.length < cfg.max) { this.ids.push(p.id); }
            this.$wire.set('mentionIds', this.ids.slice(), false); this.close();
        },
        close() { this.open = false; this.items = []; this.range = null; },
        reset() { this.ids = []; this.close(); },
    }));
}

if (window.Alpine) { register(window.Alpine); } else { document.addEventListener('alpine:init', () => register(window.Alpine)); }

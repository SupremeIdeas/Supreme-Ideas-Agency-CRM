/**
 * N2N group-call room client (Batch 11, MASTER-ONLY). The SERVER decides everything (who may speak, who pays, who is in); this page renders the
 * state it is given and connects to the SFU with a join token the server signed. A listener's token cannot publish, so a tampered page gains nothing.
 * livekit-client is loaded only on this page (dynamic import), so the rest of the app never pays for it.
 */
import './realtime.js';

const rt = window.N2NRealtime;

window.groupRoom = function (callId) {
  // The SFU room is kept OUT of Alpine's reactive data: a Proxy around it makes the SDK's internal postMessage calls fail (DataCloneError).
  let room = null;
  return {
    s: {}, msg: '', demoted: '', micOn: false, talking: new Set(), copied: false, busy: false,
    hb: null, base: '', connecting: false,

    headers() { return { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' }; },
    async api(path, method = 'POST', body = null) {
      const r = await fetch(this.base + path, { method, headers: this.headers(), credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) { const e = new Error(j.message || 'That did not work.'); e.data = j; throw e; }
      return j;
    },

    async start() {
      this.base = document.querySelector('[data-group-room]').dataset.base;
      await this.refresh();
      ['group.updated', 'group.demoted', 'group.removed', 'group.closed'].forEach((t) => rt && rt.subscribe(t, (p) => { if (p && Number(p.call_id) === Number(callId)) { if (t === 'group.demoted') { this.demoted = 'You ran out of NaaraCredit, so you are back in the audience.'; } if (t === 'group.removed') { this.msg = 'The host removed you from this call.'; this.disconnect(); } this.refresh(); } }));
      this.hb = setInterval(() => this.beat(), 8000);
      window.addEventListener('pagehide', () => { clearInterval(this.hb); });
      await this.connect();
    },

    async refresh() {
      try { this.s = await this.api('/state', 'GET'); } catch (e) { this.msg = e.message; return; }
      if (this.s.open === false || this.s.removed) { this.disconnect(); return; }
      if (room && room.state === 'connected') { await this.syncMic(); }
    },

    async beat() {
      try {
        const h = await this.api('/heartbeat');
        Object.assign(this.s, { speaking: h.speaking, open: h.open, present: h.present, removed: h.removed });
        if (!h.open || h.removed) { this.disconnect(); }
        else { await this.syncMic(); }
      } catch (e) { /* the next beat retries; the server decides what a missed beat means */ }
    },

    async connect() {
      if (this.connecting || room) { return; }
      this.connecting = true;
      try {
        const st = await this.api('/status', 'GET');
        if (!st.connect) { this.connecting = false; return; }
        const { Room, RoomEvent, Track } = await import('livekit-client');
        const r = new Room({ adaptiveStream: true, dynacast: true, audioCaptureDefaults: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
        r.on(RoomEvent.TrackSubscribed, (track) => { if (track.kind === Track.Kind.Audio) { const el = track.attach(); el.dataset.n2nAudio = '1'; document.querySelector('[data-audio-bay]').appendChild(el); } });
        r.on(RoomEvent.TrackUnsubscribed, (track) => track.detach().forEach((el) => el.remove()));
        r.on(RoomEvent.ActiveSpeakersChanged, (list) => { this.talking = new Set(list.map((p) => Number(String(p.identity).replace(/^u/, '')))); });
        r.on(RoomEvent.Disconnected, () => { if (this.s.open !== false && !this.s.removed) { room = null; setTimeout(() => this.connect(), 2500); } });
        r.on(RoomEvent.ParticipantPermissionsChanged, () => { this.syncMic(); });
        await r.connect(st.connect.url, st.connect.token);
        room = r;
        window.__n2nGroupRoom = r;       // exposed for the real-browser verification script
        await r.startAudio().catch(() => {});
        await this.syncMic();
      } catch (e) { this.msg = 'Could not connect to the call. ' + (e && e.message ? e.message : ''); room = null; }
      this.connecting = false;
    },

    /** The microphone follows the server: on only while the host has appointed this member to speak. */
    async syncMic() {
      if (!room || room.state !== 'connected') { return; }
      const lp = room.localParticipant;
      try {
        if (this.s.speaking && lp.permissions && lp.permissions.canPublish) { if (!lp.isMicrophoneEnabled && !this.manualMute) { await lp.setMicrophoneEnabled(true); } }
        else if (!this.s.speaking && lp.isMicrophoneEnabled) { await lp.setMicrophoneEnabled(false); }
      } catch (e) { this.msg = 'The microphone could not start (' + (e && e.name ? e.name : 'error') + '). Check that this site may use it.'; if (window.console) { console.warn('[n2n group] mic', e); } }
      this.micOn = !!lp.isMicrophoneEnabled;
    },
    async toggleMic() {
      const lp = room && room.localParticipant; if (!lp) { return; }
      this.manualMute = lp.isMicrophoneEnabled;
      try { await lp.setMicrophoneEnabled(!lp.isMicrophoneEnabled); } catch (e) { this.msg = 'The microphone could not start.'; }
      this.micOn = !!lp.isMicrophoneEnabled;
    },

    async act(kind, userId, body = null) {
      this.msg = '';
      try { await this.api('/people/' + userId + '/' + kind, 'POST', body); await this.refresh(); } catch (e) { this.msg = e.message; }
    },
    async leave() {
      this.busy = true;
      try { await this.api(this.s.is_host ? '/close' : '/leave'); } catch (e) { /* leaving must never get stuck */ }
      this.disconnect();
      window.location.assign(document.querySelector('a[href$="/n2n/calls"]')?.href || '/n2n/calls');
    },
    disconnect() { clearInterval(this.hb); if (room) { const r = room; room = null; r.disconnect(); } },

    async copy() { try { await navigator.clipboard.writeText(this.s.link || ''); this.copied = true; setTimeout(() => { this.copied = false; }, 1500); } catch (e) { /* the field is selectable */ } },
    async newLink() { try { await this.api('/link'); await this.refresh(); } catch (e) { this.msg = e.message; } },
    async revoke() { try { await this.api('/link', 'DELETE'); await this.refresh(); this.msg = 'The link is off. People already inside stay.'; } catch (e) { this.msg = e.message; } },
    async approval(on) { try { await this.api('/link', 'POST', { requires_approval: on }); await this.refresh(); } catch (e) { this.msg = e.message; } },
  };
};

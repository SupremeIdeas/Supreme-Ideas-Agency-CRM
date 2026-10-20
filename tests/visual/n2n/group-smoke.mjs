// N2N Batch 11 real-browser check against a REAL LiveKit SFU (dev mode). Host + listener in two Chromium contexts with fake microphones.
// Proves: join via link, listener cannot publish (even tampered), appoint -> audio really flows, demote silences within seconds, close.
// Needs: the app with N2N_SFU_* env pointing at a running livekit-server, group calls enabled, frank@n2n.test / ada@n2n.test funded.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8765';
const results = []; const ok = (n, c, extra = '') => { results.push([n, !!c]); console.log((c ? 'PASS ' : 'FAIL ') + n + (extra ? '  ' + extra : '')); };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream', '--autoplay-policy=no-user-gesture-required'] });

const hook = () => { window.__pcs = []; const O = window.RTCPeerConnection; window.RTCPeerConnection = function (...a) { const pc = new O(...a); window.__pcs.push(pc); return pc; }; window.RTCPeerConnection.prototype = O.prototype; };
async function login(email) {
  const ctx = await browser.newContext({ viewport: { width: 420, height: 900 }, permissions: ['microphone'] });
  await ctx.addInitScript(hook);
  const p = await ctx.newPage();
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', email); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation().catch(() => {}), p.click('button[type=submit]')]);
  return { ctx, p };
}
const api = (p, path, method = 'POST', body) => p.evaluate(async ([path, method, body]) => {
  const r = await fetch(path, { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' }, body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' });
  return { status: r.status, json: await r.json().catch(() => ({})) };
}, [path, method, body]);
const inboundBytes = (p) => p.evaluate(async () => { let n = 0; for (const pc of window.__pcs || []) { const st = await pc.getStats(); st.forEach((s) => { if (s.type === 'inbound-rtp' && s.kind === 'audio') n += s.bytesReceived; }); } return n; });
const outboundBytes = (p) => p.evaluate(async () => { let n = 0; for (const pc of window.__pcs || []) { const st = await pc.getStats(); st.forEach((s) => { if (s.type === 'outbound-rtp' && s.kind === 'audio') n += s.bytesSent; }); } return n; });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const H = await login('frank@n2n.test');
const created = await api(H.p, '/n2n/api/group', 'POST', { title: 'Smoke room', capacity: 10 });
ok('host creates a room', created.status === 200 && created.json.call_id, JSON.stringify(created.json).slice(0, 120));
const { call_id: id, link, room_url } = created.json;
await H.p.goto(room_url);
await H.p.waitForFunction(() => window.__n2nGroupRoom && window.__n2nGroupRoom.state === 'connected', null, { timeout: 20000 });
ok('host connected to the SFU room', true);

const L = await login('ada@n2n.test');
await L.p.goto(link);
ok('link landing shows the call', (await L.p.locator('h1').textContent()).includes('Smoke room'));
await L.p.click('text=Join call');
await L.p.waitForURL('**/n2n/group/' + id, { timeout: 15000 });
await L.p.waitForFunction(() => window.__n2nGroupRoom && window.__n2nGroupRoom.state === 'connected', null, { timeout: 20000 });
ok('listener joined through the link and connected', true);
await H.p.waitForFunction(() => document.body.innerText.includes('Ada'), null, { timeout: 15000 });
ok('host sees the listener in the roster', true);

// Tamper: the listener tries to publish a microphone straight through the SDK.
const tamper = await L.p.evaluate(async () => { try { await window.__n2nGroupRoom.localParticipant.setMicrophoneEnabled(true); } catch (e) { return 'rejected: ' + (e.message || e); } return 'accepted'; });
await sleep(2500);
const adaTracks = await H.p.evaluate(() => { const r = window.__n2nGroupRoom; let n = 0; r.remoteParticipants.forEach((p) => { n += p.audioTrackPublications.size; }); return n; });
ok('a tampered listener cannot publish (the SFU refuses it)', adaTracks === 0, 'sdk said: ' + tamper);
await L.p.screenshot({ path: 'test-results/group-1-listener-420.png' });

// Appoint -> audio flows
const ada = await H.p.evaluate(() => window.__ada || null);
const state = await api(H.p, '/n2n/api/group/' + id + '/state', 'GET');
const adaId = state.json.listeners.find((x) => x.name.includes('Ada')).id;
const ap = await api(H.p, `/n2n/api/group/${id}/people/${adaId}/appoint`);
ok('host appoints the listener as a speaker', ap.status === 200, ap.json.message || '');
await L.p.waitForFunction(() => window.__n2nGroupRoom.localParticipant.isMicrophoneEnabled, null, { timeout: 15000 }).catch(() => {});
await sleep(4000);
const dbg = await L.p.evaluate(() => ({ perm: window.__n2nGroupRoom.localParticipant.permissions?.canPublish, mic: window.__n2nGroupRoom.localParticipant.isMicrophoneEnabled, banner: document.querySelector('.gr__banner--bad')?.innerText, speakingText: document.body.innerText.includes('On stage') }));
if (process.env.DEBUG) console.log('DBG', JSON.stringify(dbg));
const micOn = dbg.mic;
ok('the speaker microphone starts by itself once appointed', micOn);
const got = await inboundBytes(H.p), sent = await outboundBytes(L.p);
ok('audio really flows speaker -> host (bytes received > 0)', got > 1000, `host received ${got} B, speaker sent ${sent} B`);
await H.p.screenshot({ path: 'test-results/group-2-host-420.png' });

// Demote -> silenced within seconds
const t0 = Date.now();
await api(H.p, `/n2n/api/group/${id}/people/${adaId}/demote`);
await H.p.waitForFunction(() => { const r = window.__n2nGroupRoom; let n = 0; r.remoteParticipants.forEach((p) => { n += p.audioTrackPublications.size; }); return n === 0; }, null, { timeout: 15000 }).catch(() => {});
const silencedIn = Date.now() - t0;
const left = await H.p.evaluate(() => { const r = window.__n2nGroupRoom; let n = 0; r.remoteParticipants.forEach((p) => { n += p.audioTrackPublications.size; }); return n; });
ok('demotion silences the speaker within seconds', left === 0 && silencedIn < 8000, `${silencedIn} ms`);

// Remove + ban, then the rejoin is refused
const rm = await api(H.p, `/n2n/api/group/${id}/people/${adaId}/remove`);
ok('host removes and bans the listener', rm.status === 200);
const again = await api(L.p, '/n2n/api/group/join', 'POST', { token: link.split('/').pop() });
ok('a removed person cannot rejoin with the same link', again.status === 403, again.json.message || '');

const close = await api(H.p, `/n2n/api/group/${id}/close`);
ok('host closes the room', close.status === 200);
await browser.close();
console.log(execSync(`cd /home/user/NaaraSim && mysql -N naarasim -e "select user_id, role, billed_seconds, billed_cents, end_reason from n2n_call_participant_intervals where group_call_id=${id}"`).toString());
process.exit(results.some((r) => !r[1]) ? 1 : 0);

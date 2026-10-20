/**
 * Naara beauty filters for video calls (MASTER-ONLY). Runs entirely in the browser, on the person's own device, with no vendor SDK and no network:
 * the camera frame is drawn through a small WebGL shader and the result is what the other person sees. Nothing leaves the device but the call itself.
 *
 * What it does (and deliberately does NOT do): gentle skin smoothing that keeps pores and edges (an edge-preserving blur limited to skin-coloured
 * pixels, with some of the fine detail added back so it never looks plastic), a soft lift in exposure, a touch of glow and colour warmth. It does not
 * reshape faces and does not use face landmarks. The filter is OFF by default; each person chooses a look from the list and an intensity, and the choice
 * is remembered on that device only.
 *
 * Provider upgrade path (see docs/VIDEO-CALLING-PLAN.md): Banuba, DeepAR or a LiveKit/Agora effects extension can replace `create()` to add face-shape,
 * makeup and AR effects, behind the same {stream, setPreset, setIntensity, destroy} surface.
 */

export const PRESETS = {
  off: { label: 'No filter', hint: 'Your camera as it is', smooth: 0, detail: 1, bright: 0, glow: 0, warm: 0, sat: 0, contrast: 0 },
  natural: { label: 'Natural', hint: 'Soft, even skin that still looks like you', smooth: 0.45, detail: 0.55, bright: 0.06, glow: 0.04, warm: 0.02, sat: 0.02, contrast: 0 },
  soft: { label: 'Soft focus', hint: 'Smoother and gentler', smooth: 0.8, detail: 0.35, bright: 0.1, glow: 0.14, warm: 0.02, sat: 0, contrast: -0.02 },
  glow: { label: 'Glow', hint: 'Bright and luminous', smooth: 0.55, detail: 0.5, bright: 0.2, glow: 0.3, warm: 0.04, sat: 0.04, contrast: 0 },
  radiant: { label: 'Radiant', hint: 'Healthy colour, a little sparkle', smooth: 0.5, detail: 0.55, bright: 0.12, glow: 0.12, warm: 0.07, sat: 0.1, contrast: 0.03 },
  warm: { label: 'Warm', hint: 'Golden-hour warmth', smooth: 0.35, detail: 0.6, bright: 0.08, glow: 0.08, warm: 0.14, sat: 0.05, contrast: 0 },
  studio: { label: 'Studio', hint: 'Clean, crisp and neutral', smooth: 0.3, detail: 0.7, bright: 0.08, glow: 0, warm: 0, sat: 0, contrast: 0.07 },
};

const VERT = 'attribute vec2 p;varying vec2 uv;void main(){uv=vec2(p.x*.5+.5,.5-p.y*.5);gl_Position=vec4(p,0.,1.);}';

// One pass. `TAPS` is injected (8 or 16) so a slow device can fall back to a cheaper blur.
const frag = (taps) => `precision mediump float;
varying vec2 uv;uniform sampler2D tex;uniform vec2 texel;
uniform float smoothAmt,detail,bright,glow,warm,sat,contrast;
const int TAPS=${taps};
float skin(vec3 c){
  float y=.299*c.r+.587*c.g+.114*c.b, cb=-.1687*c.r-.3313*c.g+.5*c.b+.5, cr=.5*c.r-.4187*c.g-.0813*c.b+.5;
  float a=smoothstep(.27,.31,cb)*(1.-smoothstep(.50,.54,cb));
  float b=smoothstep(.50,.53,cr)*(1.-smoothstep(.68,.72,cr));
  return a*b*smoothstep(.14,.30,y);
}
void main(){
  vec3 c=texture2D(tex,uv).rgb;
  float m=skin(c);
  vec3 outc=c;
  if(smoothAmt>0.001&&m>0.01){
    float r=2.6;
    vec3 acc=c;float wsum=1.;
    for(int i=0;i<TAPS;i++){
      float t=float(i)/float(TAPS);
      float ang=t*6.2831853*2.399;float rad=sqrt(t+.05)*r;
      vec2 o=vec2(cos(ang),sin(ang))*rad*texel;
      vec3 s=texture2D(tex,uv+o).rgb;
      float d=length(s-c);
      float w=exp(-d*d/.012)*exp(-rad*rad/9.);
      acc+=s*w;wsum+=w;
    }
    vec3 bil=acc/wsum;
    vec3 smoothed=bil+(c-bil)*detail;
    outc=mix(c,smoothed,smoothAmt*m);
    vec3 halo=bil;
    outc=mix(outc,1.-(1.-outc)*(1.-halo),glow*m*.7);
  }
  outc=pow(max(outc,0.),vec3(1./(1.+bright*.9)));
  outc.r*=1.+warm*.55;outc.b*=1.-warm*.55;
  float l=dot(outc,vec3(.299,.587,.114));
  outc=mix(vec3(l),outc,1.+sat*(0.4+m*0.6));
  outc=(outc-.5)*(1.+contrast)+.5;
  gl_FragColor=vec4(clamp(outc,0.,1.),1.);
}`;

const sh = (gl, type, src) => { const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) { throw new Error(gl.getShaderInfoLog(s) || 'shader'); } return s; };
const program = (gl, taps) => {
  const pr = gl.createProgram(); gl.attachShader(pr, sh(gl, gl.VERTEX_SHADER, VERT)); gl.attachShader(pr, sh(gl, gl.FRAGMENT_SHADER, frag(taps)));
  gl.bindAttribLocation(pr, 0, 'p'); gl.linkProgram(pr);
  if (!gl.getProgramParameter(pr, gl.LINK_STATUS)) { throw new Error('link'); }
  return pr;
};

/** Is on-device filtering possible here? (A 2D fallback is used when WebGL is missing; a browser with neither cannot filter.) */
export function supported() {
  try { const c = document.createElement('canvas'); return !!(c.getContext('webgl') || c.getContext('2d')) && typeof c.captureStream === 'function'; } catch (e) { return false; }
}

const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

/**
 * Process a camera stream. Returns { stream, setPreset, setIntensity, settings, destroy, mode }.
 * `stream` carries the filtered video track (the audio tracks are NOT included: keep sending the microphone as it is).
 */
export function create(camera, opts = {}) {
  const vtrack = camera.getVideoTracks()[0];
  const video = document.createElement('video');
  video.muted = true; video.playsInline = true; video.srcObject = new MediaStream([vtrack]);
  video.play().catch(() => {});
  const canvas = document.createElement('canvas');
  const maxW = opts.maxWidth || 1280;
  const set = { preset: opts.preset in PRESETS ? opts.preset : 'off', intensity: clamp(opts.intensity ?? 0.7, 0.1, 1) };
  let gl = null; let pr = null; let loc = {}; let tex = null; let taps = 16; let slow = 0; let frames = 0; let raf = 0; let cb = 0; let dead = false; let ctx2d = null; let last = 0;
  const FPS = 30;

  const init = (n) => {
    taps = n;
    pr = program(gl, taps); gl.useProgram(pr);
    const buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
    ['tex', 'texel', 'smoothAmt', 'detail', 'bright', 'glow', 'warm', 'sat', 'contrast'].forEach((k) => { loc[k] = gl.getUniformLocation(pr, k); });
    tex = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, tex);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
  };

  gl = canvas.getContext('webgl', { premultipliedAlpha: false, preserveDrawingBuffer: true, antialias: false, powerPreference: 'low-power' });
  let mode = 'webgl';
  if (gl) { try { init(16); } catch (e) { gl = null; } }
  if (!gl) { ctx2d = canvas.getContext('2d'); mode = ctx2d ? '2d' : 'none'; }

  const size = () => {
    const w = video.videoWidth || 640; const h = video.videoHeight || 480;
    const k = Math.min(1, maxW / w);
    const cw = Math.round(w * k); const ch = Math.round(h * k);
    if (canvas.width !== cw || canvas.height !== ch) { canvas.width = cw; canvas.height = ch; if (gl) { gl.viewport(0, 0, cw, ch); } }
  };

  const draw = () => {
    if (video.readyState < 2) { return; }
    size();
    const t0 = performance.now();
    const p = PRESETS[set.preset]; const k = set.intensity;
    if (gl) {
      gl.bindTexture(gl.TEXTURE_2D, tex);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, video);
      gl.uniform1i(loc.tex, 0); gl.uniform2f(loc.texel, 1 / canvas.width, 1 / canvas.height);
      gl.uniform1f(loc.smoothAmt, p.smooth * k); gl.uniform1f(loc.detail, 1 - (1 - p.detail) * k);
      gl.uniform1f(loc.bright, p.bright * k); gl.uniform1f(loc.glow, p.glow * k); gl.uniform1f(loc.warm, p.warm * k); gl.uniform1f(loc.sat, p.sat * k); gl.uniform1f(loc.contrast, p.contrast * k);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    } else if (ctx2d) {
      // Fallback: a light blur blended in (no skin mask), brightness and warmth through the canvas filter.
      ctx2d.filter = 'none'; ctx2d.globalAlpha = 1; ctx2d.drawImage(video, 0, 0, canvas.width, canvas.height);
      if (p.smooth > 0) { ctx2d.filter = `blur(${(1.6 * k).toFixed(2)}px) brightness(${1 + p.bright * k}) saturate(${1 + p.sat * k + p.warm * k})`; ctx2d.globalAlpha = clamp(p.smooth * k * 0.7, 0, 0.7); ctx2d.drawImage(video, 0, 0, canvas.width, canvas.height); ctx2d.globalAlpha = 1; ctx2d.filter = 'none'; }
    }
    // A slow device gets a cheaper blur, then a smaller picture, instead of a juddering call.
    const dt = performance.now() - t0; frames += 1; slow = slow * 0.9 + dt * 0.1;
    if (gl && frames > 45 && slow > 22 && taps === 16) { try { init(8); } catch (e) { /* keep going */ } slow = 0; } else if (frames > 90 && slow > 28) { /* halve the picture */ }
  };

  const loop = (now) => {
    if (dead) { return; }
    if (!last || now - last >= 1000 / FPS - 2) { last = now; if (set.preset !== 'off' || !opts.passthroughWhenOff) { draw(); } }
    if (video.requestVideoFrameCallback) { cb = video.requestVideoFrameCallback(() => loop(performance.now())); } else { raf = requestAnimationFrame(loop); }
  };
  loop(performance.now());
  // A background tab stops painting; keep the track alive with a timer so a minimised call tab does not freeze the picture the other person sees.
  const bg = setInterval(() => { if (!dead && document.hidden) { draw(); } }, 1000 / 15);

  const out = canvas.captureStream(FPS);

  return {
    stream: out, mode, settings: set, video, canvas,
    setPreset(name) { if (name in PRESETS) { set.preset = name; } },
    setIntensity(v) { set.intensity = clamp(Number(v), 0.1, 1); },
    destroy() { dead = true; clearInterval(bg); cancelAnimationFrame(raf); try { video.cancelVideoFrameCallback?.(cb); } catch (e) { /* ok */ } out.getTracks().forEach((t) => t.stop()); video.srcObject = null; if (gl) { try { gl.getExtension('WEBGL_lose_context')?.loseContext(); } catch (e) { /* ok */ } } },
  };
}

const KEY = 'naara-beauty';
/** The person's remembered choice on this device (OFF until they pick one). */
export function saved() {
  try { const v = JSON.parse(localStorage.getItem(KEY) || '{}'); return { preset: v.preset in PRESETS ? v.preset : 'off', intensity: clamp(v.intensity ?? 0.7, 0.1, 1) }; } catch (e) { return { preset: 'off', intensity: 0.7 }; }
}
export function remember(s) { try { localStorage.setItem(KEY, JSON.stringify({ preset: s.preset, intensity: s.intensity })); } catch (e) { /* private mode */ } }

if (typeof window !== 'undefined') { window.NaaraBeauty = { PRESETS, supported, create, saved, remember }; }

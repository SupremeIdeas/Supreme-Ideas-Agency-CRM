/* NaaraBrain shell (MASTER-ONLY): the sideways sheet and its history drawer. One sheet holds the header, the chat and the composer; the drawer waits underneath and slides in
   from the left as the sheet is dragged to the right, from anywhere on the screen (the composer included). Phone layout only (on a desktop the sidebar is docked).

   How it feels (the same rules a native drawer follows):
   - the finger drives ONE progress value (--brn-p, 0 closed .. 1 open); the sheet, the drawer's parallax slide, the dimming and the corner radius all read it, so they never drift apart;
   - the gesture only locks when the first movement is clearly sideways, so vertical scrolling is never taken over; the page is never left half-open;
   - it works in both directions and from both halves: drag the sheet right to open, drag the sheet OR the drawer left to close;
   - letting go decides by velocity first (a flick), then by position with a little hysteresis (open past 30%, close once 30% has been dragged back);
   - the settle is a critically-damped spring that inherits the finger's speed, so it never stops dead or jumps; past either end the sheet resists like a rubber band;
   - the menu button, the Escape key, tapping the dimmed sheet and the app's open/close events animate through the same spring. Reduced-motion users get an instant change. */
const NARROW = '(max-width: 1023px)';
const SKIP = 'input[type=range], audio, video, .cc-panel, .cc-voice, .cc-emoji, [data-no-swipe], pre, table, .brn__acts';
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

function brainShell() {
  const mq = window.matchMedia(NARROW);
  const still = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  return {
    open: false,
    dragging: false,
    settling: false,
    narrow: mq.matches,
    p: 0,
    raf: 0,
    init() {
      const root = this.$root;
      const sheet = root.querySelector('.brn__sheet');
      const side = root.querySelector('.brn__side');
      const setP = (v) => { this.p = v; root.style.setProperty('--brn-p', v.toFixed(4)); };
      const clearP = () => root.style.removeProperty('--brn-p');
      const width = () => (side || {}).offsetWidth || Math.min(window.innerWidth * 0.86, 340);
      const buzz = () => { try { if (navigator.vibrate) { navigator.vibrate(6); } } catch (_) {} };

      // Spring to a resting state (0 or 1), starting from the current progress and speed (progress units per second).
      const settle = (target, vel = 0) => {
        cancelAnimationFrame(this.raf);
        if (still() || !this.narrow) { this.open = target === 1; this.settling = false; this.p = target; clearP(); return; }
        this.settling = true;
        const k = 360; const c = 2 * Math.sqrt(k) * 0.96;       // stiffness / damping: just under critical, a hair of life
        let x = this.p; let v = vel; let last = performance.now();
        this.open = target === 1;                               // class + aria follow immediately; the inline value keeps the motion smooth
        const step = (now) => {
          const dt = Math.min(0.032, (now - last) / 1000); last = now;
          const a = -k * (x - target) - c * v;
          v += a * dt; x += v * dt;
          if (Math.abs(x - target) < 0.0015 && Math.abs(v) < 0.02) {
            this.p = target; this.settling = false; this.$nextTick(clearP); return;
          }
          setP(x);
          this.raf = requestAnimationFrame(step);
        };
        setP(x);
        this.raf = requestAnimationFrame(step);
      };

      // Anything that changes `open` without a finger (menu button, Esc, scrim tap, app events) goes through the same spring.
      this.$watch('open', (v) => {
        if (this.dragging || this.settling) { return; }
        if (!this.narrow) { this.p = v ? 1 : 0; return; }
        const from = this.p; const to = v ? 1 : 0;
        if (Math.abs(from - to) < 0.001) { return; }
        setP(from); settle(to, 0);
      });

      const sync = () => {
        this.narrow = mq.matches;
        if (!this.narrow) { cancelAnimationFrame(this.raf); this.open = false; this.p = 0; this.settling = false; this.dragging = false; clearP(); }
      };
      mq.addEventListener ? mq.addEventListener('change', sync) : mq.addListener(sync);

      let g = null;

      root.addEventListener('pointerdown', (e) => {
        if (!mq.matches || (e.pointerType !== 'touch' && e.pointerType !== 'pen') || !e.isPrimary) { return; }
        if (e.target.closest && e.target.closest(SKIP)) { return; }
        const ae = document.activeElement;
        if (ae && ae.tagName === 'TEXTAREA' && ae.selectionStart !== ae.selectionEnd) { return; }
        cancelAnimationFrame(this.raf);                          // a finger can catch the sheet mid-settle
        const from = this.settling ? this.p : (this.open ? 1 : 0);
        g = { id: e.pointerId, x0: e.clientX, y0: e.clientY, lock: false, dx: 0, w: width(), from, caught: this.settling, s: [[performance.now(), e.clientX]] };
      }, { passive: true });

      root.addEventListener('pointermove', (e) => {
        if (!g || e.pointerId !== g.id) { return; }
        const dx = e.clientX - g.x0; const dy = e.clientY - g.y0;
        if (!g.lock) {
          if (Math.abs(dx) < 8 && Math.abs(dy) < 8) { return; }
          // Closed: only a rightward drag opens. Open: only a leftward drag closes. Anything else belongs to the page.
          const wanted = g.from < 0.5 ? dx > 0 : dx < 0;
          if (!(Math.abs(dx) > Math.abs(dy) * 1.3 && (wanted || g.caught))) { g = null; return; }
          g.lock = true; g.x0 = e.clientX; g.y0 = e.clientY; g.dx = 0;   // restart from the point where the gesture became a drag: no jump
          g.s.push([performance.now(), e.clientX]);
          this.settling = false; this.dragging = true;
          try { root.setPointerCapture(e.pointerId); } catch (_) {}
          return;
        }
        g.dx = e.clientX - g.x0;
        let p = g.from + g.dx / g.w;
        if (p > 1) { p = 1 + (p - 1) * 0.22; }                   // rubber band past open
        if (p < 0) { p = 0; }
        setP(Math.min(p, 1.06));
        g.s.push([performance.now(), e.clientX]); if (g.s.length > 8) { g.s.shift(); }
        if (e.cancelable) { e.preventDefault(); }
      }, { passive: false });

      const end = (e) => {
        if (!g || e.pointerId !== g.id) { return; }
        const s = g; g = null;
        if (!s.lock) { return; }
        // Speed over the last ~100 ms, in progress units per second (signed: + opens).
        const now = performance.now();
        const recent = s.s.filter((q) => now - q[0] < 120);
        const a = recent[0] || s.s[0]; const b = s.s[s.s.length - 1];
        const vpx = b[0] > a[0] ? (b[1] - a[1]) / (b[0] - a[0]) : 0;          // px per ms
        const p = clamp(this.p, 0, 1);
        const flick = Math.abs(vpx) > 0.3;
        const goOpen = e.type === 'pointerup' && flick ? vpx > 0 : (s.from < 0.5 ? p > 0.3 : p > 0.7);
        const was = this.open;
        this.dragging = false;
        if (goOpen !== was) { buzz(); }
        settle(goOpen ? 1 : 0, (vpx * 1000) / s.w);
      };
      root.addEventListener('pointerup', end, { passive: true });
      root.addEventListener('pointercancel', end, { passive: true });
    },
  };
}

window.brainShell = brainShell;
document.addEventListener('alpine:init', () => { if (window.Alpine) { window.Alpine.data('brainShell', brainShell); } });

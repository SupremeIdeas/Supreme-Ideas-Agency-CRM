/*
 * Widget Dock (MASTER-ONLY): ONE positioning system for every floating widget (NaaraBrain, My Journey, WhatsApp, Wizard, quick Naara chat,
 * White Label resume, and any future one).
 *
 *   A widget opts in with markup:   data-dock="brain" data-dock-label="NaaraBrain" data-dock-anchor="left:96"  (side:px up from the bottom of the band)
 *   or in script:                   NaaraWidgets.register({ id, label, defaultAnchor: { side, bottom }, sides, flush, layer, hideOn })
 *
 * Rules: widgets rest on the LEFT or RIGHT edge band only, between the safe top and the bottom nav / composer / toasts; a drop in the
 * middle snaps to the nearest edge; widgets never leave the viewport and never overlap each other; positions persist per device
 * (localStorage, per user) and sync to the account. Long-press (touch) or the grip starts a drag; the grip is also keyboard operable.
 */
const STORE = 'naara.dock.v1';
const MARGIN = 12;
const GAP = 10;
const AVOID = '.nx-bottomnav, [data-dock-avoid], naara-composer, .nx-toast-stack';
const reduce = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!window.NaaraWidgets) {
    const cfg = (() => {
        try { return JSON.parse(document.querySelector('script[data-dock-config]')?.textContent || '{}'); } catch (e) { return {}; }
    })();
    const reg = new Map();        // id -> registry entry
    const els = new Map();        // id -> element currently in the page
    const grips = new Map();      // id -> grip button
    const last = new Map();       // id -> 'left,top' last written, so an unchanged layout never touches the DOM
    let saved = {};               // id -> { side, y }
    let stamp = 0;
    let raf = 0;
    let drag = null;
    let moving = null;            // id being moved from the keyboard
    let layer;
    let live;
    let guide;
    let syncTimer = 0;
    let ready = false;

    const uid = cfg.uid || 'guest';
    const key = `${STORE}.u${uid}`;

    const clamp = (v, a, b) => Math.min(Math.max(v, a), Math.max(a, b));
    const safe = (name) => {
        const probe = document.createElement('div');
        probe.style.cssText = `position:fixed;visibility:hidden;padding-top:env(safe-area-inset-${name})`;
        document.body.appendChild(probe);
        const v = parseFloat(getComputedStyle(probe).paddingTop) || 0;
        probe.remove();
        return v;
    };

    function load() {
        let local = {};
        try { local = JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { local = {}; }
        const localStamp = Number(local.stamp || 0);
        const serverStamp = Number(cfg.stamp || 0);
        if (serverStamp > localStamp && cfg.positions) {
            saved = cfg.positions;
            stamp = serverStamp;
            persistLocal();
        } else {
            saved = local.positions || {};
            stamp = localStamp;
        }
    }

    function persistLocal() {
        try { localStorage.setItem(key, JSON.stringify({ positions: saved, stamp })); } catch (e) { /* private mode */ }
    }

    function sync() {
        if (!cfg.saveUrl) return;
        clearTimeout(syncTimer);
        syncTimer = setTimeout(() => {
            fetch(cfg.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf || '', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ positions: saved, stamp }),
                credentials: 'same-origin',
                keepalive: true,
            }).catch(() => {});
        }, 600);
    }

    function remember(id, side, y) {
        saved[id] = { side, y: Math.round(y * 10000) / 10000 };
        stamp = Date.now();
        persistLocal();
        sync();
    }

    function announce(msg) {
        if (live) { live.textContent = ''; setTimeout(() => { live.textContent = msg; }, 30); }
    }

    /* ---------------- geometry ---------------- */

    function visible(el) {
        if (!el || !el.isConnected) return false;
        const cs = getComputedStyle(el);
        if (cs.display === 'none' || cs.visibility === 'hidden') return false;
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
    }

    function band() {
        const vv = window.visualViewport;
        const vw = document.documentElement.clientWidth;
        const vh = vv ? vv.height : window.innerHeight;
        const offTop = vv ? vv.offsetTop : 0;
        const sTop = safe('top');
        const sBottom = safe('bottom');
        let top = offTop + Math.max(sTop, 0) + 64;
        let bottom = offTop + vh - Math.max(sBottom, 0) - MARGIN;
        let left = MARGIN;
        const right = vw - MARGIN;
        const aside = document.querySelector('aside');
        if (aside && visible(aside)) {
            const ar = aside.getBoundingClientRect();
            if (ar.left < 8 && ar.width < vw * 0.5) left = Math.max(left, ar.right + MARGIN);
        }
        document.querySelectorAll(AVOID).forEach((n) => {
            if (!visible(n)) return;
            const r = n.getBoundingClientRect();
            if (r.width < vw * 0.4) return;                    // only full-width bars constrain the edge bands
            if (r.top > vh * 0.5) bottom = Math.min(bottom, r.top - GAP);
            else if (r.bottom < vh * 0.5) top = Math.max(top, r.bottom + GAP);
        });
        return { top, bottom, left, right, vw, vh };
    }

    function entry(id, el) {
        const base = reg.get(id) || {};
        const a = (el.dataset.dockAnchor || '').split(':');
        const anchor = base.defaultAnchor || (a.length === 2 ? { side: a[0], bottom: Number(a[1]) } : { side: 'right', bottom: 96 });
        return {
            id,
            label: el.dataset.dockLabel || base.label || id,
            sides: (el.dataset.dockSides || (base.sides || 'left,right')).split(','),
            flush: el.dataset.dockFlush !== undefined || !!base.flush,
            layer: Number(el.dataset.dockLayer || base.layer || 0),
            hideOn: base.hideOn || null,
            anchor,
            order: base.order ?? 0,
        };
    }

    function wanted(e, b, h) {
        const span = Math.max(1, b.bottom - b.top - h);
        const s = saved[e.id];
        let side = s && e.sides.includes(s.side) ? s.side : (e.sides.includes(e.anchor.side) ? e.anchor.side : e.sides[0]);
        let top;
        if (s) top = b.top + clamp(Number(s.y), 0, 1) * span;
        else top = b.bottom - h - Number(e.anchor.bottom || 0);   // a default anchor is an offset up from the bottom of the band
        return { side, top: clamp(top, b.top, b.bottom - h), pinned: !!s };
    }

    function free(top, left, w, h, taken) {
        return taken.every((r) => left + w + GAP <= r.l || r.l + r.w + GAP <= left || top + h + GAP <= r.t || r.t + r.h + GAP <= top);
    }

    function layout() {
        raf = 0;
        if (!ready) return;
        // forget elements that left the page; keep their saved position for next time
        els.forEach((el, id) => { if (!el.isConnected) { els.delete(id); last.delete(id); grips.get(id)?.remove(); grips.delete(id); } });
        const b = band();
        const items = [];
        els.forEach((el, id) => {
            const e = entry(id, el);
            if (e.hideOn && e.hideOn.some((p) => new RegExp(p).test(location.pathname))) { el.dataset.dockHidden = '1'; el.style.setProperty('display', 'none', 'important'); return; }
            if (el.dataset.dockHidden) { delete el.dataset.dockHidden; el.style.removeProperty('display'); }
            if (!visible(el)) { grips.get(id)?.setAttribute('hidden', ''); return; }
            if (drag && drag.id === id) return;
            const r = el.getBoundingClientRect();
            items.push({ e, el, w: Math.round(r.width), h: Math.round(r.height) });
        });
        // moved-by-the-member widgets keep their spot; defaults stack around them, lowest layer / registration order first
        items.sort((x, y) => (saved[y.e.id] ? 1 : 0) - (saved[x.e.id] ? 1 : 0) || x.e.layer - y.e.layer || x.e.order - y.e.order);
        const taken = [];
        if (drag) {
            const dr = drag.el.getBoundingClientRect();
            taken.push({ l: dr.left, t: dr.top, w: dr.width, h: dr.height });
        }
        items.forEach(({ e, el, w, h }) => {
            const want = wanted(e, b, h);
            const sides = [want.side, ...e.sides.filter((x) => x !== want.side)];
            let spot = null;
            for (const side of sides) {
                const margin = e.flush ? 0 : MARGIN;
                const left = side === 'left' ? b.left - MARGIN + margin : b.right + MARGIN - margin - w;
                if (free(want.top, left, w, h, taken)) { spot = { side, left, top: want.top }; break; }
                const cands = [];
                taken.forEach((r) => { cands.push(r.t - h - GAP, r.t + r.h + GAP); });
                cands.sort((p, q) => Math.abs(p - want.top) - Math.abs(q - want.top));
                const hit = cands.find((c) => c >= b.top && c <= b.bottom - h && free(c, left, w, h, taken));
                if (hit !== undefined) { spot = { side, left, top: hit }; break; }
            }
            if (!spot) {   // nowhere free on either edge: keep the wanted side and the nearest in-band height rather than leave the screen
                const margin = e.flush ? 0 : MARGIN;
                spot = { side: want.side, left: want.side === 'left' ? b.left - MARGIN + margin : b.right + MARGIN - margin - w, top: clamp(want.top, b.top, b.bottom - h) };
            }
            place(e, el, spot.left, spot.top, w, h, spot.side);
            taken.push({ l: spot.left, t: spot.top, w, h });
        });
        positionGrips();
    }

    function place(e, el, left, top, w, h, side) {
        const sig = `${Math.round(left)},${Math.round(top)}`;
        el.dataset.dockSide = side;
        if (last.get(e.id) === sig && el.dataset.dockOn) return;
        const first = !el.dataset.dockOn;
        el.dataset.dockOn = '1';
        last.set(e.id, sig);
        const s = el.style;
        if (first || reduce()) s.setProperty('transition', 'none', 'important');
        else s.setProperty('transition', 'left .24s cubic-bezier(.2,.8,.2,1), top .24s cubic-bezier(.2,.8,.2,1), opacity .15s ease', 'important');
        s.setProperty('position', 'fixed', 'important');
        s.setProperty('left', `${Math.round(left)}px`, 'important');
        s.setProperty('top', `${Math.round(top)}px`, 'important');
        s.setProperty('right', 'auto', 'important');
        s.setProperty('bottom', 'auto', 'important');
        s.setProperty('transform', 'none', 'important');
        s.setProperty('margin', '0', 'important');
        s.setProperty('touch-action', 'none', 'important');
    }

    function schedule() { if (!raf) raf = requestAnimationFrame(layout); }

    /* ---------------- grips (live in the dock's own layer so a Livewire re-render can't remove them) ---------------- */

    function ensureLayer() {
        if (layer && layer.isConnected) return;
        layer = document.createElement('div');
        layer.className = 'wd-layer';
        layer.setAttribute('data-dock-layer-root', '');
        live = document.createElement('div');
        live.className = 'wd-live';
        live.setAttribute('aria-live', 'polite');
        layer.appendChild(live);
        guide = document.createElement('div');
        guide.className = 'wd-guide';
        guide.setAttribute('aria-hidden', 'true');
        guide.innerHTML = '<i class="wd-guide__l"></i><i class="wd-guide__r"></i>';
        layer.appendChild(guide);
        document.body.appendChild(layer);
    }

    function gripFor(id, el) {
        let g = grips.get(id);
        if (g && g.isConnected) return g;
        const e = entry(id, el);
        g = document.createElement('button');
        g.type = 'button';
        g.className = 'wd-grip';
        g.dataset.dockGrip = id;
        g.setAttribute('aria-label', `Move ${e.label}. Press Enter, then use the arrow keys, then Enter to drop.`);
        g.innerHTML = '<svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.8"/><circle cx="15" cy="6" r="1.8"/><circle cx="9" cy="12" r="1.8"/><circle cx="15" cy="12" r="1.8"/><circle cx="9" cy="18" r="1.8"/><circle cx="15" cy="18" r="1.8"/></svg>';
        g.addEventListener('pointerdown', (ev) => begin(ev, id, true));
        g.addEventListener('keydown', (ev) => gripKey(ev, id));
        g.addEventListener('blur', () => { if (moving === id) drop(id, true); });
        layer.appendChild(g);
        grips.set(id, g);
        return g;
    }

    function positionGrips() {
        els.forEach((el, id) => {
            const g = gripFor(id, el);
            if (!visible(el) || el.dataset.dockHidden) { g.setAttribute('hidden', ''); return; }
            g.removeAttribute('hidden');
            const r = el.getBoundingClientRect();
            g.style.left = `${Math.round(r.left + r.width / 2)}px`;
            g.style.top = `${Math.round(r.top)}px`;
        });
    }

    /* ---------------- pointer drag ---------------- */

    function begin(ev, id, fromGrip) {
        if (ev.button !== undefined && ev.button !== 0) return;
        const el = els.get(id);
        if (!el) return;
        const touch = ev.pointerType === 'touch' || ev.pointerType === 'pen';
        const startX = ev.clientX;
        const startY = ev.clientY;
        const r = el.getBoundingClientRect();
        const st = { id, el, offX: startX - r.left, offY: startY - r.top, startX, startY, active: false, moved: false, timer: 0, pid: ev.pointerId, fromGrip };
        drag = st;
        const arm = () => {
            if (drag !== st) return;
            st.active = true;
            try { (fromGrip ? ev.currentTarget : el).setPointerCapture(st.pid); } catch (e) { /* element gone */ }
            document.documentElement.classList.add('wd-dragging');
            el.classList.add('wd-lifted');
            el.style.setProperty('transition', 'none', 'important');
            if (navigator.vibrate) { try { navigator.vibrate(8); } catch (e) { /* ignore */ } }
            showGuide(true);
        };
        if (fromGrip || !touch) { if (fromGrip) arm(); } else st.timer = setTimeout(arm, 250);
        const move = (m) => {
            if (m.pointerId !== st.pid) return;
            const dx = m.clientX - st.startX;
            const dy = m.clientY - st.startY;
            if (!st.active) {
                if (Math.hypot(dx, dy) > 10) {
                    if (touch && !fromGrip) { cancel(); return; }   // moved before the hold elapsed: it is a scroll or a tap, not a drag
                    arm();
                } else return;
            }
            st.moved = true;
            m.preventDefault();
            const b = band();
            const left = clamp(m.clientX - st.offX, 0, b.vw - r.width);
            const top = clamp(m.clientY - st.offY, b.top - 24, b.bottom - r.height + 24);
            el.style.setProperty('left', `${Math.round(left)}px`, 'important');
            el.style.setProperty('top', `${Math.round(top)}px`, 'important');
            positionGrips();
        };
        const up = (u) => {
            if (u.pointerId !== st.pid) return;
            finish(u.type === 'pointercancel');
        };
        const cancel = () => { clearTimeout(st.timer); detach(); if (drag === st) drag = null; };
        const detach = () => {
            window.removeEventListener('pointermove', move, true);
            window.removeEventListener('pointerup', up, true);
            window.removeEventListener('pointercancel', up, true);
        };
        const finish = (aborted) => {
            clearTimeout(st.timer);
            detach();
            document.documentElement.classList.remove('wd-dragging');
            el.classList.remove('wd-lifted');
            showGuide(false);
            if (st.active && !aborted) {
                const rr = el.getBoundingClientRect();
                const b = band();
                const e = entry(id, el);
                const centre = rr.left + rr.width / 2;
                let side = centre < b.vw / 2 ? 'left' : 'right';
                if (!e.sides.includes(side)) side = e.sides[0];
                const span = Math.max(1, b.bottom - b.top - rr.height);
                remember(id, side, clamp((rr.top - b.top) / span, 0, 1));
                if (st.moved) {
                    const swallow = (c) => { c.preventDefault(); c.stopPropagation(); };
                    el.addEventListener('click', swallow, { capture: true, once: true });
                    setTimeout(() => el.removeEventListener('click', swallow, true), 400);
                }
                announce(`${e.label} moved to the ${side} edge.`);
            }
            drag = null;
            last.delete(id);
            // animate from where it was dropped to its snapped slot
            el.style.removeProperty('transition');
            layout();
            raf = 0;
        };
        window.addEventListener('pointermove', move, { capture: true, passive: false });
        window.addEventListener('pointerup', up, true);
        window.addEventListener('pointercancel', up, true);
        if (fromGrip) ev.preventDefault();
    }

    function showGuide(on) {
        if (!guide) return;
        const b = band();
        guide.style.setProperty('--wd-top', `${b.top}px`);
        guide.style.setProperty('--wd-bottom', `${b.vh - b.bottom}px`);
        guide.classList.toggle('is-on', !!on);
    }

    /* ---------------- keyboard ---------------- */

    function gripKey(ev, id) {
        const el = els.get(id);
        if (!el) return;
        const e = entry(id, el);
        const cur = saved[id] || null;
        if (moving !== id) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                moving = id;
                if (!cur) {
                    const b = band();
                    const r = el.getBoundingClientRect();
                    remember(id, (el.dataset.dockSide || 'right'), clamp((r.top - b.top) / Math.max(1, b.bottom - b.top - r.height), 0, 1));
                }
                grips.get(id).classList.add('is-moving');
                announce(`Moving ${e.label}. Arrow keys move it, Enter drops it.`);
            }
            return;
        }
        const s = saved[id];
        let y = s.y;
        let side = s.side;
        if (ev.key === 'ArrowUp') y -= 0.05;
        else if (ev.key === 'ArrowDown') y += 0.05;
        else if (ev.key === 'ArrowLeft' && e.sides.includes('left')) side = 'left';
        else if (ev.key === 'ArrowRight' && e.sides.includes('right')) side = 'right';
        else if (ev.key === 'Enter' || ev.key === ' ' || ev.key === 'Escape') { ev.preventDefault(); drop(id, false); return; }
        else return;
        ev.preventDefault();
        remember(id, side, clamp(y, 0, 1));
        layout();
        announce(`${e.label}: ${side} edge, ${Math.round(clamp(y, 0, 1) * 100)} percent down.`);
    }

    function drop(id, silent) {
        moving = null;
        grips.get(id)?.classList.remove('is-moving');
        if (!silent) announce(`${entry(id, els.get(id) || document.body).label} placed.`);
    }

    /* ---------------- discovery + public API ---------------- */

    function adopt(el) {
        const id = el.dataset.dock;
        if (!id || els.get(id) === el) return;
        els.set(id, el);
        if (!el.dataset.dockBound) {
            el.dataset.dockBound = '1';
            el.addEventListener('pointerdown', (ev) => {
                if (ev.target.closest('[data-dock-grip]')) return;
                begin(ev, id, false);
            });
            el.addEventListener('dragstart', (ev) => ev.preventDefault());
            if (window.ResizeObserver) new ResizeObserver(schedule).observe(el);
            new MutationObserver(schedule).observe(el, { attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
        }
    }

    function scan() {
        document.querySelectorAll('[data-dock]').forEach(adopt);
        schedule();
    }

    window.NaaraWidgets = {
        register(def) {
            if (!def || !def.id) return;
            reg.set(def.id, { order: reg.size, ...def });
            schedule();
        },
        reset() {
            saved = {};
            stamp = Date.now();
            persistLocal();
            els.forEach((el, id) => { last.delete(id); });
            if (cfg.resetUrl) {
                fetch(cfg.resetUrl, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf || '', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).catch(() => {});
            }
            layout();
            announce('Widget positions reset.');
        },
        relayout: schedule,
        positions: () => JSON.parse(JSON.stringify(saved)),
    };

    function boot() {
        ensureLayer();
        load();
        ready = true;
        scan();
        new MutationObserver((muts) => {
            if (muts.some((m) => [...m.addedNodes, ...m.removedNodes].some((n) => n.nodeType === 1 && !n.closest?.('[data-dock-layer-root]')))) scan();
        }).observe(document.body, { childList: true, subtree: true });
        window.addEventListener('resize', schedule);
        window.addEventListener('orientationchange', schedule);
        if (window.visualViewport) { window.visualViewport.addEventListener('resize', schedule); window.visualViewport.addEventListener('scroll', schedule); }
        document.addEventListener('livewire:navigated', () => { ensureLayer(); scan(); });
        document.addEventListener('wd:reset', () => window.NaaraWidgets.reset());
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}

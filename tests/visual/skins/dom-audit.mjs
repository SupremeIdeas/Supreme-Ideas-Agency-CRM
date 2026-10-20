// DOM contrast audit shared by the page scanners (text 5.5:1 / 7:1 small, icons and field edges 3:1). Self-contained: it is serialised into the page by page.evaluate.
export const audit = () => {
  const parse = (c) => { const m = c.match(/-?[\d.]+(?:e-?\d+)?/g).map(Number); if (/^color\(/.test(c)) { const sl = c.split('/'); const al = sl[1] ? parseFloat(sl[1]) : 1; const sp = /srgb/.test(c) ? 1 : 1; return { r: m[0] * 255 * sp, g: m[1] * 255 * sp, b: m[2] * 255 * sp, a: al }; } return { r: m[0], g: m[1], b: m[2], a: m[3] === undefined ? 1 : m[3] }; };
  const lin = (v) => { v /= 255; return v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; };
  const L = (c) => .2126 * lin(c.r) + .7152 * lin(c.g) + .0722 * lin(c.b);
  const cr = (a, b2) => { const x = L(a), y = L(b2); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
  const over = (top, under) => ({ r: top.r * top.a + under.r * (1 - top.a), g: top.g * top.a + under.g * (1 - top.a), b: top.b * top.a + under.b * (1 - top.a), a: 1 });
  const stops = (bi) => (bi.match(/(?:rgba?|color)\([^)]*\)/g) || []).map(parse);
  const bgOptions = (el) => {
    const layers = []; let e = el;
    while (e && e.nodeType === 1) { const cs = getComputedStyle(e); const c = parse(cs.backgroundColor); if (cs.backgroundImage !== 'none') { const parts = []; let depth = 0, cur = ''; for (const ch of cs.backgroundImage) { if (ch === '(') depth++; if (ch === ')') depth--; if (ch === ',' && depth === 0) { parts.push(cur); cur = ''; } else cur += ch; } parts.push(cur); const sizes = cs.backgroundSize.split(/,\s*(?![^(]*\))/); const pools = []; for (const [pi, part] of parts.entries()) { const sz = (sizes[pi % sizes.length] || '').trim(); const m = sz.match(/^([\d.]+)px 100%$|^100% ([\d.]+)px$/); if (m && parseFloat(m[1] || m[2]) <= 8) continue; /* an edge strip (<= 8px, outside the text padding) is never behind a letter */ const g = stops(part).filter((s) => s.a > .05); if (!g.length) continue; if (/^\s*(repeating-)?radial-gradient/.test(part)) pools.push(...g); else if (/^\s*linear-gradient/.test(part) && g.length > 1 && g.length <= 4 && !/\d+(px|%)\s*(,|\))/.test(part.replace(/rgba?\([^)]*\)/g, ''))) { /* a smooth gradient: text sits where the fill is the blend of its stops (the pixel harness samples there) */ const av = g.reduce((a, c) => ({ r: a.r + c.r / g.length, g: a.g + c.g / g.length, b: a.b + c.b / g.length, a: a.a + c.a / g.length }), { r: 0, g: 0, b: 0, a: 0 }); layers.push({ opts: [av] }); } else layers.push({ opts: g.slice(0, 4) }); } if (pools.length) layers.push({ opts: pools.slice(0, 8) }); } if (c.a > 0) layers.push({ opts: [c] }); if (c.a >= 1) break; e = e.parentElement; }
    let combos = [{ r: 255, g: 255, b: 255, a: 1 }]; if (document.documentElement.classList.contains('dark')) combos = [{ r: 0, g: 0, b: 0, a: 1 }];
    for (let i = layers.length - 1; i >= 0; i--) { const next = []; for (const u of combos) for (const o of layers[i].opts) next.push(over(o, u)); combos = next.slice(0, 24); }
    return combos;
  };
  const opacityOf = (el) => { let o = 1; for (let e = el; e && e.nodeType === 1; e = e.parentElement) o *= Number(getComputedStyle(e).opacity); return o; };
  const out = []; const root = document.body;
  const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  const seen = new Set();
  for (let n = w.nextNode(); n; n = w.nextNode()) {
    const t = n.nodeValue.trim(); if (!t || /^[\p{Extended_Pictographic}\u200d\ufe0f\s]+$/u.test(t)) continue; const el = n.parentElement; if (!el || seen.has(el)) continue; seen.add(el);
    if (el.closest('script,style,.sr-only,[hidden],option,svg,template')) continue;
    const cs = getComputedStyle(el); if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    const r = el.getBoundingClientRect(); if (r.width < 2 || r.height < 2 || r.bottom < 0 || r.top > innerHeight + 400) continue;
    if (el.closest('[data-nx-skip-contrast], :disabled, [aria-disabled=true], .ns-gradtext, .nx-imghero, .ns-dest, .ns-bh__cover, .ns-bh__covertext, .nx-pl, .nx-glass') || cs.webkitBackgroundClip === 'text' || cs.backgroundClip === 'text') continue;
    const op = opacityOf(el); if (op < .05) continue;   // hover-only / not yet shown
    const fg0 = parse(cs.color);
    let worst = 99; let bgs = bgOptions(el);
    for (const bg of bgs) { const fg = over({ ...fg0, a: fg0.a * op }, bg); worst = Math.min(worst, cr(fg, bg)); }
    const size = parseFloat(cs.fontSize); const bold = Number(cs.fontWeight) >= 700; const need = size >= 24 || (size >= 18.66 && bold) ? 3 : 4.5;   // WCAG AA (the chat shell uses a stricter 5.5 / 7 floor of its own)
    if (worst < need) { const cl = (el.className && el.className.baseVal === undefined ? String(el.className) : '').split(/\s+/).filter((c) => /^(n|cc-|ns-)/.test(c))[0] || el.tagName.toLowerCase(); const par = (el.closest('[class*="nc__"],[class*="nn__"],[class*="ncb"],[class*="cc-"],[class*="ns-"],[class*="nx-"]')?.className || '').toString().split(/\s+/)[0]; out.push({ el: cl, par, text: t.slice(0, 28), ratio: Math.round(worst * 100) / 100, need, fg: cs.color, size }); }
  }
  // ---- Non-text contrast (WCAG 1.4.11, 3:1): icons, and the edge of a form field whose fill does not already separate it from what is behind it ----
  const vis = (el) => { const cs = getComputedStyle(el); if (cs.display === 'none' || cs.visibility === 'hidden' || opacityOf(el) < .05) return false; const r = el.getBoundingClientRect(); return r.width >= 6 && r.height >= 6 && r.bottom > 0 && r.top < innerHeight + 400 && !el.closest('script,style,.sr-only,[hidden],template,:disabled,[aria-disabled=true],[data-nx-skip-contrast],.nx-imghero,.ns-dest,.ns-bh__cover,.nx-pl,.nx-glass'); };
  const nm = (el) => (el.closest('[class*="nc__"],[class*="nn__"],[class*="ncb"],[class*="cc-"],[class*="ns-"],[class*="nx-"]')?.className || '').toString().split(/\s+/)[0] || el.tagName.toLowerCase();
  for (const svg of document.querySelectorAll('svg')) {
    if (!svg.querySelector('use,path,circle,rect,line,polyline') || !vis(svg)) continue; if (svg.closest('symbol,defs,[data-nx-skip-contrast],[role=img]')) continue; if ([...svg.querySelectorAll('[fill]')].some((n) => { const f = n.getAttribute('fill'); return f && f !== 'none' && f !== 'currentColor' && !f.startsWith('url('); })) continue;   // multi-colour brand artwork (payment logos, flags)
    const cs = getComputedStyle(svg); const fg0 = parse(cs.color); const op = opacityOf(svg); let worst = 99;
    for (const bg of bgOptions(svg)) { const fg = over({ ...fg0, a: fg0.a * op }, bg); worst = Math.min(worst, cr(fg, bg)); }
    if (worst < 3) out.push({ el: 'icon', par: nm(svg), text: '[icon] ' + (svg.querySelector('use')?.getAttribute('href') || svg.querySelector('use')?.getAttribute('xlink:href') || '').slice(0, 22), ratio: Math.round(worst * 100) / 100, need: 3, fg: cs.color, size: 0 });
  }
  for (const f of document.querySelectorAll('input:not([type=hidden]):not([type=color]):not([type=checkbox]):not([type=radio]):not([type=range]):not([type=file]),textarea,select,.cc-box,.ns-search,.nc__psearch,[data-nx-field]')) {
    if (!vis(f) || (f.matches('.cc-text') && f.closest('.cc-box'))) continue; const cs = getComputedStyle(f); const par = f.parentElement; if (!par) continue;
    if (f.matches('input,textarea,select') && f.parentElement.closest('.ns-search,.nc__psearch,.cc-box,[data-nx-field],[class*=linkcard]') && parse(cs.backgroundColor).a < .05 && !(parseFloat(cs.borderTopWidth) >= 1 && cs.borderTopStyle !== 'none')) continue;   // the wrapper is the field and is audited itself
    const fills = bgOptions(f), backs = bgOptions(par); let sep = 99; for (const a of fills) for (const b2 of backs) sep = Math.min(sep, cr(a, b2));
    if (sep >= 3) continue;
    let edge = null; const bw = parseFloat(cs.borderTopWidth); if (bw >= 1 && cs.borderTopStyle !== 'none') { const c = parse(cs.borderTopColor); if (c.a > .05) edge = c; }
    if (!edge) { let best = null; for (const m of cs.boxShadow.matchAll(/(rgba?\([^)]*\)|color\([^)]*\))\s+0px\s+0px\s+(?:0px\s+)?(\d+(?:\.\d+)?)px(?:\s+inset)?/g)) { if (parseFloat(m[2]) >= 1) { const c = parse(m[1]); if (c.a > .05 && (!best || c.a >= best.a)) best = c; } } if (best) edge = best; }
    let worst = 99; if (edge) { for (const b2 of backs) { const e2 = over(edge, b2); worst = Math.min(worst, cr(e2, b2)); } } else worst = sep;
    if (worst < 3) out.push({ el: 'border', par: nm(f), text: '[field edge] ' + (f.placeholder || f.name || f.className.toString().split(' ')[0] || f.tagName).toString().slice(0, 18), ratio: Math.round(worst * 100) / 100, need: 3, fg: edge ? 'edge' : 'none', size: 0 });
  }
  return out;
};
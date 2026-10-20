// NaaraBrain composer glass: screenshot + contrast of the placeholder, the toolbar icons and the lock badges against the translucent field, every skin x mode.
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import fs from 'node:fs';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const skins = 'surface,calm,vivid,ledger,daybreak,paper,clay,dotgrid,aurora,glass,halo,neo,noir,adire,blueprint,clarity,neon,passport,golden,vault,boarding,chipset,wavelength,airmail,handset,prism,receipt,nebula,harmattan,ankara,danfo,asooke,tide,canopy,pop'.split(',');
const OUT = '/tmp/cg'; fs.mkdirSync(OUT, { recursive: true });
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await ctx.newPage();
await p.goto('http://127.0.0.1:8765/login'); await p.fill('input[name=email]','frank@n2n.test'); await p.fill('input[name=password]','password');
await Promise.all([p.waitForURL((u)=>!u.pathname.startsWith('/login')), p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8765/brain'); await p.waitForSelector('#nx-preloader',{state:'detached',timeout:9000}).catch(()=>{}); await p.waitForSelector('.cc-box'); await p.waitForTimeout(900);
await p.addStyleTag({ content: '*{transition:none!important;animation:none!important}' });
const manifest = [];
for (const dark of [false, true]) for (const s of skins) {
  await p.evaluate(([s,d])=>{const h=document.documentElement;h.setAttribute('data-nx-skin',s);h.classList.toggle('dark',d);},[s,dark]); await p.waitForTimeout(150);
  const boxes = await p.evaluate(() => { const out = []; const add = (q, el, color, size = 14) => { const r = el.getBoundingClientRect(); if (r.width < 4) return; out.push({ q, x: r.x, y: r.y, w: r.width, h: r.height, color, size, weight: '400' }); };
    const ta = document.querySelector('.brn__dock textarea'); if (ta) add('placeholder', ta, getComputedStyle(ta, '::placeholder').color);
    document.querySelectorAll('.brn__dock .cc-btn').forEach((el, i) => { if (i < 5) add('icon', el, getComputedStyle(el).color); });
    return out; });
  const f = `${OUT}/${s}-${dark?'d':'l'}.png`; await p.screenshot({ path: f }); manifest.push({ f, label: 'composer', s, dark, boxes });
}
fs.writeFileSync(`${OUT}/manifest.json`, JSON.stringify(manifest)); await b.close(); console.log('captured', manifest.length);

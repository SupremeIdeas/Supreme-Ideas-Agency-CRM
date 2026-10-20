// Product card look + gradient-title contrast: every skin x light/dark. Each gradient stop (resolved colour) vs the card surface beside the title must be >= 4.5:1.
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import fs from 'node:fs';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const skins = 'surface,calm,vivid,ledger,daybreak,paper,clay,dotgrid,aurora,glass,halo,neo,noir,adire,blueprint,clarity,neon,passport,golden,vault,boarding,chipset,wavelength,airmail,handset,prism,receipt,nebula,harmattan,ankara,danfo,asooke,tide,canopy,pop'.split(',');
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 390, height: 2400 } }); const p = await ctx.newPage();
await p.goto('http://127.0.0.1:8765/login'); await p.fill('input[name=email]','frank@n2n.test'); await p.fill('input[name=password]','password');
await Promise.all([p.waitForURL((u)=>!u.pathname.startsWith('/login')), p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8765/dashboard'); await p.waitForSelector('#nx-preloader',{state:'detached',timeout:9000}).catch(()=>{}); await p.waitForLoadState('networkidle').catch(()=>{}); await p.waitForTimeout(1200);
await p.addStyleTag({ content: '.brn-fab,[data-brain-widget],.nn-fab{display:none!important} *{transition:none!important;animation:none!important}' });
const OUT = '/tmp/sc'; fs.mkdirSync(OUT, { recursive: true }); const manifest = [];
for (const dark of [false, true]) for (const s of skins) {
  await p.evaluate(([s,d])=>{const h=document.documentElement;h.setAttribute('data-nx-skin',s);h.classList.toggle('dark',d);},[s,dark]); await p.waitForTimeout(120);
  const cards = await p.evaluate(() => {
    const c = document.createElement('canvas'); c.width = c.height = 1; const x = c.getContext('2d', { willReadFrequently: true });
    const rgb = (v) => { x.clearRect(0,0,1,1); x.fillStyle = '#000'; x.fillStyle = v; x.fillRect(0,0,1,1); const d = x.getImageData(0,0,1,1).data; return [d[0],d[1],d[2]]; };
    return [...document.querySelectorAll('.ns-card.ns-showcase')].map((card) => { const h = card.querySelector('h3'); const r = h.getBoundingClientRect(); const cs = getComputedStyle(h);
      const probe = document.createElement('span'); document.body.appendChild(probe);
      probe.style.color = cs.getPropertyValue('--sc-ia'); const a = rgb(getComputedStyle(probe).color); probe.style.color = cs.getPropertyValue('--sc-ib'); const b2 = rgb(getComputedStyle(probe).color); probe.remove();
      return { pk: card.dataset.pk, a, b: b2, x: r.x + scrollX, y: r.y + scrollY, w: r.width, h: r.height }; });
  });
  const f = `${OUT}/${s}-${dark?'d':'l'}.png`; await p.screenshot({ path: f, fullPage: true }); manifest.push({ f, s, dark, cards });
}
fs.writeFileSync(`${OUT}/manifest.json`, JSON.stringify(manifest)); await b.close(); console.log('captured', manifest.length);

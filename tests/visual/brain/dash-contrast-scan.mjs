// Dashboard contrast scan (shared UI): every skin x light/dark on the home dashboard's hero, greeting card and product cards. Pair with contrast-scan.py (same OUT dir).
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import fs from 'node:fs';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const skins = 'surface,calm,vivid,ledger,daybreak,paper,clay,dotgrid,aurora,glass,halo,neo,noir,adire,blueprint,clarity,neon,passport,golden,vault,boarding,chipset,wavelength,airmail,handset,prism,receipt,nebula,harmattan,ankara,danfo,asooke,tide,canopy,pop'.split(',');
const OUT = process.env.OUT || '/tmp/ds'; fs.mkdirSync(OUT, { recursive: true });
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 390, height: 2400 } }); const p = await ctx.newPage();
await p.goto('http://127.0.0.1:8765/login'); await p.fill('input[name=email]','frank@n2n.test'); await p.fill('input[name=password]','password');
await Promise.all([p.waitForURL((u)=>!u.pathname.startsWith('/login')), p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8765/dashboard'); await p.waitForSelector('#nx-preloader',{state:'detached',timeout:9000}).catch(()=>{}); await p.waitForTimeout(800);
await p.waitForLoadState('networkidle').catch(()=>{}); await p.waitForTimeout(1200);
await p.addStyleTag({ content: '.brn-fab,[data-brain-widget],.nn-fab{display:none!important}' });
const SEL = ['.nx-home-hero h1', '.nx-home-hero p', '.ns-gc__name', '.ns-gc__hi b', '.ns-gc__hi span', '.ns-gc__tiptxt', '.ns-showcase h3', '.ns-showcase p', '.ns-showcase__cta'];
const manifest = [];
for (const dark of [false, true]) for (const s of skins) {
  await p.evaluate(([s,d])=>{const h=document.documentElement;h.setAttribute('data-nx-skin',s);h.classList.toggle('dark',d);},[s,dark]); await p.waitForTimeout(150);
  const boxes = await p.evaluate((SEL)=>{const out=[];for(const q of SEL){document.querySelectorAll(q).forEach((el,i)=>{if(i>2)return;const r=el.getBoundingClientRect();if(r.width<4||r.height<4)return;const cs=getComputedStyle(el);out.push({q,x:r.x+window.scrollX,y:r.y+window.scrollY,w:r.width,h:r.height,color:cs.color,size:parseFloat(cs.fontSize),weight:cs.fontWeight});});}return out;},SEL);
  const f = `${OUT}/dash-${s}-${dark?'d':'l'}.png`; await p.screenshot({ path: f, fullPage: true }); manifest.push({ f, label: 'dash', s, dark, boxes });
}
fs.writeFileSync(`${OUT}/manifest.json`, JSON.stringify(manifest)); await b.close(); console.log('captured', manifest.length);

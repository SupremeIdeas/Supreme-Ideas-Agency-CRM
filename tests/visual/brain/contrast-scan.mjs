// NaaraBrain contrast scan (MASTER-ONLY): every skin x light/dark, empty chat + an open thread. Saves a screenshot + the text boxes; contrast-scan.py samples the background beside each text box.
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import fs from 'node:fs';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const skins = 'surface,calm,vivid,ledger,daybreak,paper,clay,dotgrid,aurora,glass,halo,neo,noir,adire,blueprint,clarity,neon,passport,golden,vault,boarding,chipset,wavelength,airmail,handset,prism,receipt,nebula,harmattan,ankara,danfo,asooke,tide,canopy,pop'.split(',');
const OUT = process.env.OUT || '/tmp/cs';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await ctx.newPage();
await p.goto('http://127.0.0.1:8765/login'); await p.fill('input[name=email]','frank@n2n.test'); await p.fill('input[name=password]','password');
await Promise.all([p.waitForURL((u)=>!u.pathname.startsWith('/login')), p.click('button[type=submit]')]);
const SEL = ['.brn__headttl span', '.brn__lockname', '.brn__tag', '.brn__chip', '.brn__fine', '.brn__msg--out .brn__bubble', '.brn__msg--in .brn__bubble'];
const manifest = [];
for (const [label, url] of [['empty', '/brain'], ['thread', '/brain?t=3']]) {
  await p.goto('http://127.0.0.1:8765' + url); await p.waitForSelector('#nx-preloader',{state:'detached',timeout:9000}).catch(()=>{}); await p.waitForSelector('.brn'); await p.waitForTimeout(500);
  for (const dark of [false, true]) for (const s of skins) {
    await p.evaluate(([s,d])=>{const h=document.documentElement;h.setAttribute('data-nx-skin',s);h.classList.toggle('dark',d);document.querySelectorAll('.brn *').forEach(e=>{e.getAnimations&&e.getAnimations().forEach(a=>a.pause())});},[s,dark]);
    await p.waitForTimeout(120);
    const boxes = await p.evaluate((SEL)=>{const out=[];for(const q of SEL){document.querySelectorAll(q).forEach((el,i)=>{if(i>1)return;const r=el.getBoundingClientRect();if(r.width<4||r.height<4||r.bottom<0||r.top>844)return;const cs=getComputedStyle(q==='.brn__msg--in .brn__bubble'?(el.querySelector('p')||el):el);out.push({q,x:r.x,y:r.y,w:r.width,h:r.height,color:cs.color,size:parseFloat(cs.fontSize),weight:cs.fontWeight});});}return out;},SEL);
    const f = `${OUT}/${label}-${s}-${dark?'d':'l'}.png`; await p.screenshot({ path: f }); manifest.push({ f, label, s, dark, boxes });
  }
}
fs.writeFileSync(`${OUT}/manifest.json`, JSON.stringify(manifest)); await b.close(); console.log('captured', manifest.length);

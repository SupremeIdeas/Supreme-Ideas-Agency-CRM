// Send-button contrast (shared composer): idle and ready, every skin x light/dark. Icon vs button fill must be >= 4.5:1, the idle ring vs the box fill >= 3:1.
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const skins = 'surface,calm,vivid,ledger,daybreak,paper,clay,dotgrid,aurora,glass,halo,neo,noir,adire,blueprint,clarity,neon,passport,golden,vault,boarding,chipset,wavelength,airmail,handset,prism,receipt,nebula,harmattan,ankara,danfo,asooke,tide,canopy,pop'.split(',');
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await ctx.newPage();
await p.goto('http://127.0.0.1:8765/login'); await p.fill('input[name=email]','frank@n2n.test'); await p.fill('input[name=password]','password');
await Promise.all([p.waitForURL((u)=>!u.pathname.startsWith('/login')), p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8765/brain'); await p.waitForSelector('#nx-preloader',{state:'detached',timeout:9000}).catch(()=>{}); await p.waitForSelector('.cc-send'); await p.waitForTimeout(800);
await p.addStyleTag({ content: '*{transition:none!important;animation:none!important}' });
const measure = () => p.evaluate(() => {
  const c = document.createElement('canvas'); c.width = c.height = 1; const x = c.getContext('2d', { willReadFrequently: true });
  const rgb = (v) => { x.clearRect(0,0,1,1); x.fillStyle = '#000'; x.fillStyle = v; x.fillRect(0,0,1,1); const d = x.getImageData(0,0,1,1).data; return [d[0],d[1],d[2]]; };
  const L = (c) => { const f = (v) => { v/=255; return v<=0.03928 ? v/12.92 : ((v+0.055)/1.055)**2.4; }; return 0.2126*f(c[0])+0.7152*f(c[1])+0.0722*f(c[2]); };
  const R = (a,b) => { let la=L(a), lb=L(b); if (la<lb) [la,lb]=[lb,la]; return (la+0.05)/(lb+0.05); };
  const btn = document.querySelector('.cc-send'); const cs = getComputedStyle(btn); const box = getComputedStyle(document.querySelector('.cc-box'));
  const fill = rgb(cs.backgroundColor), ink = rgb(cs.color), boxfill = rgb(box.backgroundColor);
  // ring colour = the inset shadow's colour
  const m = cs.boxShadow.match(/(rgba?\([^)]*\)|color\([^)]*\))/); const ring = m ? rgb(m[1]) : fill;
  return { icon: R(ink, fill), ring: R(ring, boxfill), ready: btn.classList.contains('cc-ready') };
});
const bad = []; let n = 0;
for (const dark of [false, true]) for (const s of skins) {
  await p.evaluate(([s,d])=>{const h=document.documentElement;h.setAttribute('data-nx-skin',s);h.classList.toggle('dark',d);},[s,dark]); await p.waitForTimeout(120);
  const idle = await measure(); n++;
  if (idle.icon < 4.5) bad.push([s, dark ? 'dark' : 'light', 'idle icon', idle.icon.toFixed(2)]);
  if (idle.ring < 3) bad.push([s, dark ? 'dark' : 'light', 'idle ring', idle.ring.toFixed(2)]);
  await p.evaluate(() => document.querySelector('.cc-send').classList.add('cc-ready')); await p.waitForTimeout(120);
  const rdy = await measure(); n++;
  if (rdy.icon < 4.5) bad.push([s, dark ? 'dark' : 'light', 'ready icon', rdy.icon.toFixed(2)]);
  await p.evaluate(() => document.querySelector('.cc-send').classList.remove('cc-ready'));
}
console.log(n, 'states checked'); bad.forEach((r) => console.log('FAIL', r.join(' '))); console.log('failures:', bad.length);
if (process.env.SHOT) { await p.evaluate(()=>{document.documentElement.setAttribute('data-nx-skin','surface');document.documentElement.classList.add('dark');}); await p.screenshot({ path: '/home/user/NaaraSim/test-results/send-idle.png', clip: { x: 0, y: 660, width: 390, height: 140 } }); await p.evaluate(()=>document.querySelector('.cc-send').classList.add('cc-ready')); await p.screenshot({ path: '/home/user/NaaraSim/test-results/send-ready.png', clip: { x: 0, y: 660, width: 390, height: 140 } }); }
await b.close(); process.exit(bad.length ? 1 : 0);

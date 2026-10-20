// New chat button: look at rest / hover / pressed, and white-label contrast on both gradient stops in every skin x mode.
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const skins = 'surface,calm,vivid,ledger,daybreak,paper,clay,dotgrid,aurora,glass,halo,neo,noir,adire,blueprint,clarity,neon,passport,golden,vault,boarding,chipset,wavelength,airmail,handset,prism,receipt,nebula,harmattan,ankara,danfo,asooke,tide,canopy,pop'.split(',');
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const ctx = await b.newContext({ viewport: { width: 1280, height: 800 } }); const p = await ctx.newPage();
await p.goto('http://127.0.0.1:8765/login'); await p.fill('input[name=email]','frank@n2n.test'); await p.fill('input[name=password]','password');
await Promise.all([p.waitForURL((u)=>!u.pathname.startsWith('/login')), p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8765/brain'); await p.waitForSelector('#nx-preloader',{state:'detached',timeout:9000}).catch(()=>{}); await p.waitForSelector('.brn__new'); await p.waitForTimeout(700);
const btn = p.locator('.brn__new'); const clip = async (n) => { const bb = await btn.boundingBox(); await p.screenshot({ path: `test-results/newchat-${n}.png`, clip: { x: 0, y: Math.max(0, bb.y - 10), width: 340, height: bb.height + 20 } }); };
await p.evaluate(() => document.documentElement.classList.add('dark')); await p.waitForTimeout(300);
await clip('rest'); await btn.hover(); await p.waitForTimeout(800); await clip('hover'); await p.mouse.down(); await p.waitForTimeout(800); await clip('press'); await p.mouse.up(); await p.mouse.move(300, 600);
const bb = await btn.boundingBox(); console.log('button size', Math.round(bb.width), 'x', Math.round(bb.height));
const m = await p.evaluate((skins) => { const c = document.createElement('canvas'); c.width = c.height = 1; const x = c.getContext('2d', { willReadFrequently: true });
  const rgb = (v) => { x.clearRect(0,0,1,1); x.fillStyle = '#000'; x.fillStyle = v; x.fillRect(0,0,1,1); const d = x.getImageData(0,0,1,1).data; return [d[0],d[1],d[2]]; };
  const L = (c) => { const f = (v) => { v/=255; return v<=0.03928 ? v/12.92 : ((v+0.055)/1.055)**2.4; }; return 0.2126*f(c[0])+0.7152*f(c[1])+0.0722*f(c[2]); };
  const R = (a,b) => { let la=L(a), lb=L(b); if (la<lb) [la,lb]=[lb,la]; return (la+0.05)/(lb+0.05); };
  const btn = document.querySelector('.brn__new'); const out = [];
  for (const d of [false, true]) for (const s of skins) { const h = document.documentElement; h.setAttribute('data-nx-skin', s); h.classList.toggle('dark', d);
    const cs = getComputedStyle(btn); const a = rgb(cs.getPropertyValue('--brn-a')), bb2 = rgb(cs.getPropertyValue('--brn-b')); const w = [255,255,255];
    out.push([s, d ? 'dark' : 'light', Math.min(R(w, a), R(w, bb2))]); }
  return out; }, skins);
const bad = m.filter((r) => r[2] < 4.5); console.log(m.length, 'skin/mode combos; worst', Math.min(...m.map((r) => r[2])).toFixed(2), 'failing:', bad.length); bad.slice(0, 8).forEach((r) => console.log('FAIL', r.join(' ')));
await b.close(); process.exit(bad.length ? 1 : 0);

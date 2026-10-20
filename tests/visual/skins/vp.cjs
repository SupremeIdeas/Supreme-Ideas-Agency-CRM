// Viewport shot after scrolling a selector into view. SEL=.ns-showcase-grid SKIN=glass MODE=dark node vp.cjs -> out/vp-<skin>-<mode>-<w>.png
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  const skin=process.env.SKIN||'glass',mode=process.env.MODE||'dark';
  for (const [w,h] of [[430,1500],[1280,1000]]) {
    const ctx=await b.newContext({viewport:{width:w,height:h},colorScheme:mode});
    const p=await ctx.newPage();
    await p.goto(BASE+'/login',{waitUntil:'networkidle'});
    await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
    await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
    await p.goto(BASE+(process.env.URL||'/dashboard'),{waitUntil:'networkidle'});
    await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.classList.toggle('dark',m==='dark');d.dataset.nxMode=m;},[skin,mode]);
    await p.waitForTimeout(400);
    await p.evaluate((sel)=>{const e=document.querySelector(sel);if(e){const y=e.getBoundingClientRect().top+scrollY-90;scrollTo(0,y);}},process.env.SEL||'.ns-showcase-grid');
    await p.waitForTimeout(400);
    await p.screenshot({path:`out/vp-${skin}-${mode}-${w}.png`});
    await ctx.close();
  }
  await b.close();
})();

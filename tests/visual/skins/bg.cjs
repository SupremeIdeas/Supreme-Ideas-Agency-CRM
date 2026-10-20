// Page-canvas check: the platform wallpaper must not show under a skin. SKIN=glass MODE=dark node bg.cjs  -> out/bg-<skin>-<mode>-<w>.png
const path=require('path');
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
const fs=require('fs');
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});fs.mkdirSync('out',{recursive:true});
  const skin=process.env.SKIN||'glass',mode=process.env.MODE||'dark';
  for (const [w,h] of [[430,1100],[1280,900]]) {
    const ctx=await b.newContext({viewport:{width:w,height:h},colorScheme:mode});
    const p=await ctx.newPage();
    await p.goto(BASE+'/login',{waitUntil:'networkidle'});
    await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
    await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
    for (const url of (process.env.URLS||'/dashboard').split(',')) {
      await p.goto(BASE+url,{waitUntil:'networkidle'});
      await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.classList.toggle('dark',m==='dark');d.dataset.nxMode=m;},[skin,mode]);
      await p.waitForTimeout(300);
      await p.screenshot({path:`out/bg-${skin}-${mode}-${w}-${url.replace(/\W/g,'_')}.png`});
    }
    await ctx.close();
  }
  await b.close();
})();

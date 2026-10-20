// Clip a region of a page. URL= X= Y= W= H= SKIN= MODE= node clip.cjs -> out/clip-<skin>-<mode>.png (device scale 3 for small details)
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  const skin=process.env.SKIN||'glass',mode=process.env.MODE||'dark';
  const ctx=await b.newContext({viewport:{width:430,height:900},colorScheme:mode,deviceScaleFactor:3});
  const p=await ctx.newPage();
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});
  await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+(process.env.URL||'/dashboard'),{waitUntil:'networkidle'});
  await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.classList.toggle('dark',m==='dark');d.dataset.nxMode=m;},[skin,mode]);
  await p.waitForTimeout(600);
  await p.screenshot({path:`out/clip-${skin}-${mode}.png`,clip:{x:+(process.env.X||200),y:+(process.env.Y||620),width:+(process.env.W||230),height:+(process.env.H||110)}});
  await b.close();
})();

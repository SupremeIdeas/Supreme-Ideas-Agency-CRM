// Collapsed desktop side menu. SKIN= MODE= node rail.cjs -> out/rail-<skin>-<mode>.png
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  const skin=process.env.SKIN||'glass',mode=process.env.MODE||'dark';
  const ctx=await b.newContext({viewport:{width:1280,height:820},colorScheme:mode});
  const p=await ctx.newPage();
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});
  await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/dashboard',{waitUntil:'networkidle'});
  await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.classList.toggle('dark',m==='dark');d.dataset.nxMode=m;},[skin,mode]);
  await p.locator('button[aria-label="Collapse menu"]').first().click();await p.waitForTimeout(700);
  await p.screenshot({path:`out/rail-${skin}-${mode}.png`,clip:{x:0,y:0,width:520,height:420}});
  await b.close();
})();

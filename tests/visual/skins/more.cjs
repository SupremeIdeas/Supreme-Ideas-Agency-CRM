// More menu in both layouts. SKIN= MODE= node more.cjs -> out/more-<skin>-<mode>-<layout>-<w>.png
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  const skin=process.env.SKIN||'glass',mode=process.env.MODE||'dark';
  const ctx=await b.newContext({viewport:{width:430,height:900},colorScheme:mode});
  const p=await ctx.newPage();
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});
  await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/dashboard',{waitUntil:'networkidle'});
  await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.classList.toggle('dark',m==='dark');d.dataset.nxMode=m;},[skin,mode]);
  await p.locator('nav button[aria-label], nav a, nav button').filter({hasText:/more/i}).first().click().catch(async()=>{await p.locator('[aria-label="More"]').first().click();});
  await p.waitForTimeout(500);
  for (const l of ['grid','list']) {
    await p.locator(`[aria-label="${l==='grid'?'Grid':'List'} view"]`).first().click();await p.waitForTimeout(300);
    await p.screenshot({path:`out/more-${skin}-${mode}-${l}-430.png`});
  }
  await b.close();
})();

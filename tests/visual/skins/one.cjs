// one.cjs: SKIN= MODE= W= URL= [SEL=text to scroll to] -> out/one-<skin>-<mode>-<url>.png (full page)
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
(async()=>{const b=await chromium.launch({executablePath:CHROME});const mode=process.env.MODE||'dark';const p=await (await b.newContext({viewport:{width:+(process.env.W||430),height:900},colorScheme:mode})).newPage();
await p.goto(BASE+'/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type="submit"]')]);
await p.goto(BASE+(process.env.URL||'/account'),{waitUntil:'networkidle'});await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[process.env.SKIN||'canopy',mode]);await p.waitForTimeout(400);
if(process.env.SEL){const l=p.locator('text='+process.env.SEL).first();await l.scrollIntoViewIfNeeded();}
await p.screenshot({path:`out/one-${process.env.SKIN||'canopy'}-${mode}-${(process.env.URL||'/account').replace(/\W/g,'_')}.png`,fullPage:!process.env.SEL});await b.close();})();

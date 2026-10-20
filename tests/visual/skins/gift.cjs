let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:430,height:900}})).newPage();const errs=[];p.on('pageerror',e=>errs.push(String(e)));
await p.goto('http://127.0.0.1:8098/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8098/gift-cards',{waitUntil:'networkidle'});await p.waitForTimeout(2200);
await p.screenshot({path:'out/gift-1.png',fullPage:true});
await p.locator('.ns-gf__brand').first().click();await p.waitForTimeout(900);await p.screenshot({path:'out/gift-2.png'});console.log(errs);await b.close()})();

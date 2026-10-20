let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:430,height:900}})).newPage();const errs=[];p.on('pageerror',e=>errs.push(String(e)));
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8099/numbers/contacts',{waitUntil:'networkidle'});
await p.click('.ns-ct__add');await p.waitForTimeout(500);await p.screenshot({path:'out/ct-sheet.png'});
await p.keyboard.press('Escape');await p.waitForTimeout(300);await p.click('button[aria-label="Grid view"]');await p.waitForTimeout(300);await p.screenshot({path:'out/ct-grid.png'});console.log(errs);await b.close()})();

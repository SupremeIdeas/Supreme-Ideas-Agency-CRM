let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
const B='http://127.0.0.1:8099';const L=process.env.L||'fr';
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
let p=await (await b.newContext({viewport:{width:1280,height:900}})).newPage();
await p.goto(B+'/',{waitUntil:'networkidle'});await p.click('.nx-lang-chip');await p.click(`button[name=locale][value=${L}]`);await p.waitForLoadState('networkidle');
await p.screenshot({path:`out/lang-${L}-home.png`});
for (const u of ['/about','/how-it-works','/contact']){await p.goto(B+u,{waitUntil:'networkidle'});await p.screenshot({path:`out/lang-${L}${u.replace('/','-')}.png`});}
p=await (await b.newContext({viewport:{width:430,height:900}})).newPage();
await p.goto(B+'/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.click('.nx-lang-chip >> visible=true');await p.click(`button[name=locale][value=${L}]`);await p.waitForLoadState('networkidle');
await p.goto(B+'/wallet',{waitUntil:'networkidle'});await p.screenshot({path:`out/lang-${L}-wallet.png`});
await b.close()})();

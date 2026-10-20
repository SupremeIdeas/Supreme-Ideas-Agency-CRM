let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:+(process.env.W||430),height:900}})).newPage();const errs=[];p.on('pageerror',e=>errs.push(String(e)));
await p.goto('http://127.0.0.1:8098/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8098/numbers/dialer',{waitUntil:'networkidle'});await p.waitForTimeout(600);
console.log(p.url(),await p.locator('.ns-dl__key').count());await p.screenshot({path:'out/dl-0.png'});await p.click('.ns-dl__key >> nth=3');await p.click('.ns-dl__key >> nth=4');await p.waitForTimeout(300);
await p.screenshot({path:'out/dl-1.png',fullPage:true});
await p.click('.ns-dl__cc');await p.waitForTimeout(400);await p.screenshot({path:'out/dl-2.png'});
console.log(errs, await p.evaluate(()=>!!document.querySelector('[data-dialer] [data-dialer-panel]')));await b.close()})();

let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:+(process.env.W||430),height:900}})).newPage();const errs=[];p.on('pageerror',e=>errs.push(String(e)));
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8099/merchant/invoices',{waitUntil:'networkidle'});await p.waitForTimeout(500);
await p.locator('.ns-pg__item').nth(1).click();await p.waitForTimeout(700);
await p.screenshot({path:'out/inv-1.png',fullPage:true});
await p.getByRole('button',{name:/create invoice/i}).first().click();await p.waitForTimeout(600);await p.screenshot({path:'out/inv-2.png'});console.log(errs);await b.close()})();

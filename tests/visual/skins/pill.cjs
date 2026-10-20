let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:430,height:932}})).newPage();
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8099/catalogue',{waitUntil:'networkidle'});
await p.evaluate(()=>{const d=document.documentElement;d.dataset.nxSkin='vivid';d.dataset.nxMode='light';d.classList.remove('dark')});await p.waitForTimeout(400);
console.log(JSON.stringify(await p.evaluate(()=>[...document.querySelectorAll('.ns-pill')].map(e=>{const c=getComputedStyle(e);return {cls:e.className,bg:c.backgroundColor,bgi:c.backgroundImage.slice(0,60),color:c.color,par:e.parentElement.className.slice(0,40)}}).slice(0,6)),null,1));await b.close()})();

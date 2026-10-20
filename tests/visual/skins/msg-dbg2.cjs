let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:430,height:900}})).newPage();
p.on('response',async r=>{if(r.url().includes('livewire/update')){const j=await r.json();for(const c of j.components){console.log('keys',Object.keys(c),'effects',Object.keys(c.effects||{}));console.log((c.effects.html||'').slice(0,400));}}});
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8099/numbers/messages',{waitUntil:'networkidle'});
console.log((await p.evaluate(()=>document.querySelector('.ns-msg-page').outerHTML)).slice(0,300));
await p.getByRole('tab',{name:/contacts/i}).first().click();await p.waitForTimeout(800);await b.close()})();

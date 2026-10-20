let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:430,height:900}})).newPage();
p.on('pageerror',e=>console.log('ERR',String(e).slice(0,200),(e.stack||'').split('\n').slice(0,4).join('|')));
p.on('response',async r=>{if(r.url().includes('livewire')&&r.request().method()==='POST'){console.log('POST',r.status(),r.url().slice(-40));}});
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8099/numbers/messages',{waitUntil:'networkidle'});
console.log('url',p.url());console.log('roots',await p.evaluate(()=>[...document.querySelectorAll('[wire\\:id]')].map(e=>e.getAttribute('wire:name')||e.tagName)));
await p.getByRole('tab',{name:/contacts/i}).first().click();await p.waitForTimeout(800);await b.close()})();

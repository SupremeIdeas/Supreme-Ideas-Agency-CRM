let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:430,height:900}})).newPage();
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8099/numbers/messages',{waitUntil:'networkidle'});
const m=()=>p.evaluate(()=>[...document.querySelectorAll('.ns-msg,.ns-msg__list,.ns-msg__scroll,.ns-seg,.ns-msg__head')].map(e=>[e.className.slice(0,28),e.scrollWidth,e.clientWidth,e.scrollLeft,Math.round(e.getBoundingClientRect().left)]));
console.log('chats',JSON.stringify(await m()));
await p.getByRole('tab',{name:/contacts/i}).first().click();await p.waitForTimeout(600);
console.log('contacts',JSON.stringify(await m()));await b.close()})();

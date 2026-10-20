let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});const w=+(process.env.W||1280);const mode=process.env.MODE||'light';const p=await (await b.newContext({viewport:{width:w,height:w>800?820:932},colorScheme:mode})).newPage();
const errs=[];p.on('pageerror',e=>errs.push(String(e)));
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type="submit"]')]);
await p.goto('http://127.0.0.1:8099/numbers/messages?to=%2B2348012345678',{waitUntil:'networkidle'});
await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[process.env.SKIN||'surface',mode]);
await p.waitForTimeout(600);
if(process.env.DRAG){const g=p.locator('.ns-msg__grip').first();const bb=await g.boundingBox();await p.mouse.move(bb.x+6,bb.y+200);await p.mouse.down();await p.mouse.move(bb.x+120,bb.y+200,{steps:8});await p.mouse.up();await p.waitForTimeout(300);console.log('left width after drag',await p.evaluate(()=>getComputedStyle(document.querySelector('.ns-msg')).getPropertyValue('--msg-l')))}
await p.screenshot({path:`out/msg-${process.env.SKIN||'surface'}-${mode}-${w}.png`});console.log(errs.slice(0,3));await b.close()})();

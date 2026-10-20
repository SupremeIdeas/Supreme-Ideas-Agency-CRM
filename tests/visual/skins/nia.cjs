let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});const mode=process.env.MODE||'dark';const p=await (await b.newContext({viewport:{width:430,height:932},colorScheme:mode})).newPage();
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type="submit"]')]);
await p.goto('http://127.0.0.1:8099/support',{waitUntil:'networkidle'});
await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[process.env.SKIN||'glass',mode]);
await p.waitForTimeout(600);await p.screenshot({path:`out/nia-${process.env.SKIN||'glass'}-${mode}.png`});await b.close()})();

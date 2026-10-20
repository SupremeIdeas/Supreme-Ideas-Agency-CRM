// vp2.cjs: SKIN= MODE= URL= [SCROLL=px] -> out/vp2-<skin>-<mode>.png, one real viewport (430x932) so fixed canvases render as users see them
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});const mode=process.env.MODE||'dark';const p=await (await b.newContext({viewport:{width:+(process.env.W||430),height:932},colorScheme:mode})).newPage();
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type="submit"]')]);
await p.goto('http://127.0.0.1:8099'+(process.env.URL||'/dashboard'),{waitUntil:'networkidle'});
await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[process.env.SKIN||'glass',mode]);
await p.evaluate(y=>window.scrollTo(0,y),+(process.env.SCROLL||0));await p.waitForTimeout(500);
await p.screenshot({path:`out/vp2-${process.env.SKIN||'glass'}-${mode}${process.env.TAG||''}.png`});await b.close()})();

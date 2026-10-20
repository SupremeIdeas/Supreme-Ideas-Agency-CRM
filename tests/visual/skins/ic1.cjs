let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
const [,, url, skin, mode, sym]=process.argv;
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:430,height:932}})).newPage();
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto('http://127.0.0.1:8099'+url,{waitUntil:'networkidle'});
await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[skin,mode]);await p.waitForTimeout(500);
const r=await p.evaluate((sym)=>[...document.querySelectorAll('use')].filter(u=>(u.getAttribute('href')||'').endsWith(sym)).map(u=>{const s=u.closest('svg');let host=s.parentElement;const cs=getComputedStyle(s);const out={svgColor:cs.color,stroke:cs.stroke,opacity:cs.opacity,host:host.tagName+'.'+host.className.toString().slice(0,40),hostBg:getComputedStyle(host).backgroundColor,hostBgImg:getComputedStyle(host).backgroundImage.slice(0,120),hostColor:getComputedStyle(host).color};return out}),sym);
console.log(JSON.stringify(r,null,1));await b.close()})();

let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome',args:['--autoplay-policy=no-user-gesture-required']});
const ctx=await b.newContext({viewport:{width:430,height:932},colorScheme:process.env.MODE||'light'});const p=await ctx.newPage();
const errs=[];p.on('pageerror',e=>errs.push(String(e)));p.on('console',m=>{if(m.type()==='error')errs.push(m.text())});
await p.goto('http://127.0.0.1:8099/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type="submit"]')]);
await p.goto('http://127.0.0.1:8099/account/sounds',{waitUntil:'networkidle'});
await p.evaluate(([k,m])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[process.env.SKIN||'surface',process.env.MODE||'light']);
await p.click('body');await p.waitForTimeout(400);
const info=await p.evaluate(async()=>{const cfg=JSON.parse(document.querySelector('meta[name="nx-sound"]').content);const keys=Object.keys(cfg.tones);
 window.NaaraSound.unlock();const url=cfg.tones['credit_alert.default'];const buf=await window.NaaraSound.buffer(url);
 return {keys:keys.length,state:window.NaaraSound.ctx.state,dur:+buf.duration.toFixed(2),ch:buf.numberOfChannels}});
console.log(JSON.stringify(info),errs.slice(0,3));
await p.screenshot({path:`out/snd-${process.env.SKIN||'surface'}-${process.env.MODE||'light'}.png`,fullPage:false});
await p.evaluate(()=>window.scrollTo(0,700));await p.waitForTimeout(300);await p.screenshot({path:'out/snd-user2.png'});
await b.close()})();

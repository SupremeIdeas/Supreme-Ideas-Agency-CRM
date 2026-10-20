let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
const B='http://127.0.0.1:8099';const SK=(process.env.SKINS||'surface,noir,clay').split(',');
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const ctx=await b.newContext({viewport:{width:430,height:900}});const p=await ctx.newPage();
const errs=[];p.on('pageerror',e=>errs.push(e.message));
await p.goto(B+'/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
const set=async(s,m)=>p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[s,m]);
for(const [url,tag] of [['/numbers/lines','lines'],['/support','support'],['/account','account'],['/dashboard','home']]){
 await p.goto(B+url,{waitUntil:'networkidle'}).catch(()=>{});await p.waitForTimeout(500);
 for(const m of ['light','dark'])for(const s of SK){await set(s,m);await p.waitForTimeout(150);await p.screenshot({path:`out/w-${tag}-${s}-${m}.png`,fullPage:false});}
}
// notification centre + wizard on home
await p.goto(B+'/dashboard',{waitUntil:'networkidle'});
for(const m of ['light','dark'])for(const s of SK){await set(s,m);
  const bell=p.locator('button[aria-label*="otification"]').first(); if(await bell.count()){await bell.click().catch(()=>{});await p.waitForTimeout(400);await p.screenshot({path:`out/w-nc-${s}-${m}.png`});await p.keyboard.press('Escape');await bell.click().catch(()=>{});}
}
console.log('errs',errs);await b.close()})();

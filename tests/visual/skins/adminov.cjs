let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
const B='http://127.0.0.1:8099';const SK=(process.env.SKINS||'surface,noir,clay,pop,glass,paper').split(',');const W=+(process.env.W||1280);
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const ctx=await b.newContext({viewport:{width:W,height:900}});const p=await ctx.newPage();
const errs=[];p.on('pageerror',e=>errs.push(e.message));
await p.goto(B+'/login');await p.fill('input[name="email"]','supremeideasz@gmail.com');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto(B+'/adminmaster',{waitUntil:'networkidle'});console.log(p.url());
for(const mode of ['light','dark'])for(const s of SK){await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[s,mode]);await p.waitForTimeout(200);
await p.screenshot({path:`out/adm-${s}-${mode}-${W}.png`,fullPage:true});}
console.log('errs',errs);await b.close()})();

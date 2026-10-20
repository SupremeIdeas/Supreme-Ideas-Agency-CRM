let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
const B='http://127.0.0.1:8099';
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
for (const [vw,vh,tag] of [[1280,800,'d'],[430,900,'m']]) {
 const ctx=await b.newContext({viewport:{width:vw,height:vh}}); const p=await ctx.newPage();
 await p.goto(B+'/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
 for (const mode of ['light','dark']) for (const [u,n] of [['/rewards/hunt','hunt'],['/brand/get-listed','listed']]) {
   await p.goto(B+u,{waitUntil:'networkidle'});
   await p.evaluate(m=>{document.documentElement.classList.toggle('dark',m==='dark');document.documentElement.setAttribute('data-nx-mode',m)},mode);
   await p.waitForTimeout(500); await p.screenshot({path:`out/hero-${n}-${tag}-${mode}.png`}); }
 await ctx.close(); }
await b.close()})();

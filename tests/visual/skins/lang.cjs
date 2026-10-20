let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
const B='http://127.0.0.1:8099';
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
// guest, marketing
let ctx=await b.newContext({viewport:{width:1280,height:800}});let p=await ctx.newPage();
await p.goto(B+'/',{waitUntil:'networkidle'});await p.screenshot({path:'out/lang-mkt-d.png',clip:{x:0,y:0,width:1280,height:140}});
await p.click('.nx-lang-chip');await p.waitForTimeout(400);await p.screenshot({path:'out/lang-mkt-modal.png'});
await p.click('button[name=locale][value=pt]');await p.waitForLoadState('networkidle');
console.log('guest after pt:',await p.evaluate(()=>document.documentElement.lang),await p.locator('.nx-lang-chip span').first().innerText());
await p.reload({waitUntil:'networkidle'});console.log('guest persists after reload:',await p.evaluate(()=>document.documentElement.lang));
await p.goto(B+'/login',{waitUntil:'networkidle'});await p.screenshot({path:'out/lang-login.png'});
// member
ctx=await b.newContext({viewport:{width:430,height:900}});p=await ctx.newPage();
await p.goto(B+'/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto(B+'/wallet',{waitUntil:'networkidle'});await p.screenshot({path:'out/lang-m.png',clip:{x:0,y:0,width:430,height:100}});
await p.click('.nx-lang-chip >> visible=true');await p.waitForTimeout(400);await p.screenshot({path:'out/lang-m-modal.png'});
await p.click('button[name=locale][value=ar]');await p.waitForLoadState('networkidle');
console.log('member after ar:',await p.evaluate(()=>[document.documentElement.lang,document.documentElement.dir]));
await p.screenshot({path:'out/lang-m-ar.png'});
await p.click('.nx-lang-chip >> visible=true');await p.click('button[name=locale][value=en]');await p.waitForLoadState('networkidle');
console.log('member back en:',await p.evaluate(()=>document.documentElement.lang));
ctx=await b.newContext({viewport:{width:1280,height:800}});p=await ctx.newPage();
await p.goto(B+'/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto(B+'/wallet',{waitUntil:'networkidle'});await p.screenshot({path:'out/lang-d.png',clip:{x:640,y:0,width:640,height:100}});
await b.close()})();

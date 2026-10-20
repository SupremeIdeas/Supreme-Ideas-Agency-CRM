let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
const B='http://127.0.0.1:8099';
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const ctx=await b.newContext({viewport:{width:1280,height:900}});const p=await ctx.newPage();
await p.goto(B+'/login');await p.fill('input[name="email"]','supremeideasz@gmail.com');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type=submit]')]);
await p.goto(B+'/adminmaster',{waitUntil:'networkidle'});await p.waitForTimeout(600);
await p.screenshot({path:'out/adm-one.png'});
const r=await p.evaluate(()=>{const a=document.querySelector('.ns-adm');const m=a?.closest('main');const q=e=>e?JSON.stringify(e.getBoundingClientRect()):null;return [q(a),q(m),q(document.querySelector('aside')),getComputedStyle(a.parentElement).margin, a.parentElement.className, a.className]});
console.log(r);await b.close()})();

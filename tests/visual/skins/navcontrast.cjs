// Contrast of bottom-nav labels/icons (fixed chrome the page scans skip): every skin x mode. node navcontrast.cjs && python3 navcontrast.py
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require('/opt/node22/lib/node_modules/playwright'))}
const fs=require('fs');const BASE=process.env.BASE||'http://127.0.0.1:8099';const URL=process.env.URL||'/wallet';
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome'});
const p=await (await b.newContext({viewport:{width:430,height:900}})).newPage();
await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');
await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type=submit]')]);
await p.goto(BASE+'/account/appearance',{waitUntil:'networkidle'});
const all=await p.evaluate(()=>[...document.querySelectorAll('.ns-skin-card .ns-mini')].map(e=>e.dataset.nxPreview));
await p.goto(BASE+URL,{waitUntil:'networkidle'});
await p.addStyleTag({content:"*,*::before,*::after{animation:none!important;transition:none!important}"});
fs.mkdirSync('out/navc',{recursive:true});const meta=[];
for(const k of all)for(const m of ['light','dark'])for(const acc of ['teal','rose','gold']){
 await p.evaluate(([k,m,a])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxAccent=a;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark');},[k,m,acc]);
 await p.waitForTimeout(80);
 const items=await p.evaluate(()=>{const o=[];document.querySelectorAll('.nx-bottomnav a.nx-tab, .nx-bottomnav .nx-bn-more').forEach(a=>{
   const lab=a.querySelector('span.truncate');const svg=a.querySelector('svg');
   if(lab){const r=lab.getBoundingClientRect();o.push({kind:'label',on:a.classList.contains('is-on'),color:getComputedStyle(lab).color,x:r.x,y:r.y,w:r.width,h:r.height});}
   if(svg){const r=svg.getBoundingClientRect();o.push({kind:'icon',on:a.classList.contains('is-on')||a.classList.contains('nx-bn-more'),color:getComputedStyle(a).color,x:r.x,y:r.y,w:r.width,h:r.height});}
 });return o;});
 await p.addStyleTag({content:'.nx-bottomnav *{color:transparent!important} .nx-bottomnav svg{visibility:hidden!important}',});
 const f=`out/navc/${k}-${m}-${acc}.png`;await p.screenshot({path:f});
 await p.evaluate(()=>{[...document.querySelectorAll('style')].filter(s=>s.textContent.startsWith('.nx-bottomnav *{color')).forEach(s=>s.remove())});
 meta.push({skin:k,mode:m,acc,file:f,items});
}
fs.writeFileSync('out/navc/meta.json',JSON.stringify(meta));await b.close()})();

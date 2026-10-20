// Bottom-nav strip for every skin x mode (active tab visible). URL=/wallet node navmatrix.cjs && python3 navsheet.py
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
fs.mkdirSync('out/nav',{recursive:true});
for(const k of all)for(const m of ['light','dark']){
 await p.evaluate(([k,m])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxAccent='teal';d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark');},[k,m]);
 await p.waitForTimeout(120);
 await p.screenshot({path:`out/nav/${k}-${m}.png`,clip:{x:0,y:790,width:430,height:110}});
}
fs.writeFileSync('out/nav/skins.json',JSON.stringify(all));
await b.close()})();

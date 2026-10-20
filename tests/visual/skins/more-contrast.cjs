// Contrast of the More sheet (all skins x modes x layouts) -> out/cp/meta.json, then `python3 contrast-pages.py all`.
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const fs=require('fs');const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome',BASE=process.env.BASE||'http://127.0.0.1:8099';
(async()=>{const b=await chromium.launch({executablePath:CHROME});const p=await (await b.newContext({viewport:{width:430,height:1100}})).newPage();
await p.goto(BASE+'/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type="submit"]')]);
await p.goto(BASE+'/dashboard',{waitUntil:'networkidle'});await p.addStyleTag({content:'*,*::before,*::after{animation:none!important;transition:none!important}'});
await p.locator('[aria-label="More"]').first().click();await p.waitForTimeout(400);
const skins=(process.env.SKINS||'').split(',').filter(Boolean).length?process.env.SKINS.split(','):await p.evaluate(async()=>[...(await (await fetch('/account/appearance')).text()).matchAll(/data-nx-preview="([a-z0-9-]+)"/g)].map(m=>m[1]).filter((v,i,a)=>a.indexOf(v)===i));
fs.mkdirSync('out/cp',{recursive:true});const meta=[];
for(const k of skins)for(const mode of ['light','dark'])for(const lay of ['grid','list']){
 await p.evaluate(([k,m])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxMode=m;d.dataset.nxAccent='teal';d.classList.toggle('dark',m==='dark')},[k,mode]);
 await p.locator(`[aria-label="${lay==='grid'?'Grid':'List'} view"]`).first().click();await p.waitForTimeout(60);
 const items=await p.evaluate(()=>{const out=[];const root=document.querySelector('.ns-more');const w=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);while(w.nextNode()){const n=w.currentNode,t=n.textContent.trim();if(!t)continue;const e=n.parentElement,cs=getComputedStyle(e);if(e.closest('.nx-float-card,[aria-hidden=true]'))continue;const r=document.createRange();r.selectNodeContents(n);const bb=r.getBoundingClientRect();if(bb.width<2||bb.height<2||bb.bottom<0||bb.top>innerHeight)continue;out.push({sel:e.tagName,t:t.slice(0,24),c:cs.color,o:parseFloat(cs.opacity),fs:parseFloat(cs.fontSize),fw:parseInt(cs.fontWeight),x:bb.x+scrollX,y:bb.y+scrollY,w:bb.width,h:bb.height,cls:e.className.toString().slice(0,40)})}return out});
 await p.evaluate(()=>{let s=document.getElementById('nx-hide');if(!s){s=document.createElement('style');s.id='nx-hide';s.textContent='.nx-hide-text *{color:transparent!important;-webkit-text-fill-color:transparent!important;text-shadow:none!important}';document.head.appendChild(s)}document.documentElement.classList.add('nx-hide-text')});
 const f=`out/cp/more-${k}-${mode}-${lay}.png`;await p.screenshot({path:f});await p.evaluate(()=>document.documentElement.classList.remove('nx-hide-text'));
 meta.push({url:'more-'+lay,skin:k,mode,acc:'teal',file:f,items})}
fs.writeFileSync('out/cp/meta.json',JSON.stringify(meta));await b.close()})();

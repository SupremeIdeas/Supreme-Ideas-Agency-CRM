// Icon visibility (WCAG 1.4.11 non-text contrast, 3:1): every visible svg icon on the real pages, every skin x mode.
//   URLS=/dashboard,/wallet SKINS=glass node icons.cjs && python3 icons.py
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const fs=require('fs');const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome',BASE=process.env.BASE||'http://127.0.0.1:8099';
const URLS=(process.env.URLS||'/dashboard,/wallet,/numbers,/catalogue,/account,/rewards,/referrals,/journey,/receipts,/data-estimator').split(',');
(async()=>{const b=await chromium.launch({executablePath:CHROME});const ctx=await b.newContext({viewport:{width:430,height:932}});const p=await ctx.newPage();
await p.goto(BASE+'/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
await p.mouse.move(2,2);
await p.goto(BASE+'/account/appearance',{waitUntil:'networkidle'});
const skins=(process.env.SKINS||'').split(',').filter(Boolean).length?process.env.SKINS.split(','):await p.evaluate(()=>[...document.querySelectorAll('.ns-skin-card .ns-mini')].map(e=>e.dataset.nxPreview));
fs.mkdirSync('out/ic',{recursive:true});const meta=[];
for(const url of URLS){await p.goto(BASE+url,{waitUntil:'networkidle'});await p.mouse.move(2,2);
 await p.addStyleTag({content:'*,*::before,*::after{animation:none!important;transition:none!important}'});
 await p.evaluate(()=>{document.querySelectorAll('body *').forEach(e=>{const c=getComputedStyle(e).position;if((c==='fixed'||c==='sticky')&&!e.closest('.ns-modal,.ns-app,.ns-sheet'))e.style.setProperty('display','none','important')})});
 const H=await p.evaluate(()=>document.documentElement.scrollHeight);await p.setViewportSize({width:430,height:Math.min(Math.max(H,932),7000)});await p.waitForTimeout(250);
 for(const k of skins)for(const mode of ['light','dark']){
  await p.evaluate(([k,m])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxAccent='teal';d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[k,mode]);await p.waitForTimeout(120);
  const items=await p.evaluate(()=>{const out=[];for(const s of document.querySelectorAll('.ns-app svg')){const cs=getComputedStyle(s);if(cs.display==='none'||cs.visibility==='hidden'||s.querySelector('path[fill]')||s.querySelector('[id^=__lottie]')||s.closest('[aria-hidden=true]:not(svg),.nx-float-card,[x-cloak],[id*=lottie],:disabled,[aria-disabled=true]'))continue;const r=s.getBoundingClientRect();if(r.width<8||r.height<8)continue;{const t=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);if(t&&!s.contains(t)&&!t.contains(s)&&!s.parentElement.contains(t))continue;}
    const st=getComputedStyle(s);let col=st.stroke&&st.stroke!=='none'?st.stroke:(st.fill&&st.fill!=='none'?st.fill:st.color);if(col==='currentcolor')col=st.color;
    let o=1,a=s;while(a&&a!==document.body){o*=parseFloat(getComputedStyle(a).opacity);a=a.parentElement}
    out.push({c:col,o,x:r.x+scrollX,y:r.y+scrollY,w:r.width,h:r.height,name:(s.querySelector('use')?.getAttribute('href')||s.className.baseVal||'').toString().slice(0,30),par:(s.parentElement.tagName+'.'+String(s.parentElement.className).split(' ')[0])})}return out});
  await p.evaluate(()=>{let s=document.getElementById('ic-hide');if(!s){s=document.createElement('style');s.id='ic-hide';s.textContent='.ic-hide svg{visibility:hidden!important}.ic-hide *{color:transparent!important;-webkit-text-fill-color:transparent!important}';document.head.appendChild(s)}document.documentElement.classList.add('ic-hide')});
  const f=`out/ic/${url.replace(/[^a-z0-9]+/gi,'_')}-${k}-${mode}.png`;await p.screenshot({path:f,fullPage:true});await p.evaluate(()=>document.documentElement.classList.remove('ic-hide'));
  meta.push({url,skin:k,mode,file:f,items})}}
fs.writeFileSync('out/ic/meta.json',JSON.stringify(meta));await b.close()})().catch(e=>{console.error(e);process.exit(1)});

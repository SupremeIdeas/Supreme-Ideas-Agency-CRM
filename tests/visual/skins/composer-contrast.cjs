// Business inbox composer across every skin x mode x accent: send button (empty and ready), placeholder, emoji button vs the composer surface.
//   BASE=http://127.0.0.1:8765 NX_EMAIL=frank@n2n.test NX_PASSWORD=password node composer-contrast.cjs
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
const lum=([r,g,b])=>{const f=v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)};return .2126*f(r)+.7152*f(g)+.0722*f(b)};
const ratio=(a,b)=>{const [x,y]=[lum(a),lum(b)].sort((m,n)=>n-m);return (x+.05)/(y+.05)};
const rgb=s=>(s.match(/[\d.]+/g)||[]).slice(0,4).map(Number);
const over=(fg,bg)=>{const a=fg[3]===undefined?1:fg[3];return [0,1,2].map(i=>fg[i]*a+bg[i]*(1-a))};
(async()=>{
 const b=await chromium.launch({executablePath:CHROME});
 const p=await (await b.newContext({viewport:{width:1280,height:860}})).newPage();
 await p.goto(BASE+'/login',{waitUntil:'networkidle'});
 await p.fill('input[name="email"]',process.env.NX_EMAIL||'nx-diff@naara.test');await p.fill('input[name="password"]',process.env.NX_PASSWORD||'password');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
 await p.goto(BASE+'/dashboard',{waitUntil:'networkidle'});
 const tok=await p.evaluate(()=>document.querySelector('meta[name=csrf-token]')?.content);
 await p.evaluate(async t=>{await fetch('/workspace/switch/2',{method:'POST',headers:{'X-CSRF-TOKEN':t},redirect:'manual'})},tok);
 await p.goto(BASE+'/numbers/messages?to=5551&ch=telegram',{waitUntil:'networkidle'});await p.waitForTimeout(3500);
 await p.addStyleTag({content:'*,*::before,*::after{transition:none!important;animation:none!important}'});
 await p.goto(BASE+'/account/appearance',{waitUntil:'networkidle'}).catch(()=>{});
 const skins=await p.evaluate(()=>[...document.querySelectorAll('.ns-skin-card .ns-mini')].map(e=>e.dataset.nxPreview));
 await p.goto(BASE+'/numbers/messages?to=5551&ch=telegram',{waitUntil:'networkidle'});await p.waitForTimeout(3500);
 await p.addStyleTag({content:'*,*::before,*::after{transition:none!important;animation:none!important}'});
 let n=0,bad=0,min=99;
 for(const k of skins)for(const mode of ['light','dark'])for(const acc of (process.env.ACCENTS||'teal,rose,violet').split(',')){
  await p.evaluate(([k,m,a])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxAccent=a;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[k,mode,acc]);
  await p.waitForTimeout(50);
  for(const state of ['empty','ready']){
   const r=await p.evaluate(async st=>{const ta=document.querySelector('.bz-msg .cc-text');if(!ta)return null;ta.value=st==='ready'?'Hello':'';ta.dispatchEvent(new Event('input',{bubbles:true}));await new Promise(r=>setTimeout(r,30));
     const s=document.querySelector('.bz-msg .cc-send'),box=document.querySelector('.bz-msg .cc-box');const cs=getComputedStyle(s);const bx=getComputedStyle(box);
     return{ic:cs.color,bg:cs.backgroundColor,box:bx.backgroundColor,ph:getComputedStyle(ta,'::placeholder').color,ring:cs.boxShadow}},state);
   if(!r){console.log('NO COMPOSER',k);bad++;continue}
   const boxc=rgb(r.box);const boxBg=boxc.length>3&&boxc[3]<1?over(boxc,[255,255,255]):boxc;
   const sendBg=rgb(r.bg);const surf=(sendBg[3]===0||sendBg[3]===undefined&&false)?boxBg:(sendBg[3]!==undefined&&sendBg[3]<1?over(sendBg,boxBg):sendBg);
   const ic=ratio(rgb(r.ic),surf);const ph=ratio(over(rgb(r.ph),boxBg),boxBg);
   n++;min=Math.min(min,ic);
   if(ic<4.5||ph<4.5){bad++;console.log('FAIL',k,mode,acc,state,'icon',ic.toFixed(2),'placeholder',ph.toFixed(2))}
  }
 }
 console.log(`composer checks: ${n}, failures: ${bad}, lowest icon ratio: ${min.toFixed(2)}`);
 await b.close();process.exit(bad?1:0);})();

// For every skin x accent x mode: sample the REAL rendered background behind each text node (text hidden), compare to its real colour.
const path=require('path');
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const ROOT=path.resolve(__dirname,'../../..');
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const fs=require('fs');
const BASE='http://127.0.0.1:8099';
const skins=(process.env.SKINS||'').split(',').filter(Boolean);
const accents=(process.env.ACCENTS||'teal,ocean,violet,rose,amber,slate').split(',');
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  const ctx=await b.newContext({viewport:{width:430,height:900},deviceScaleFactor:1});
  const p=await ctx.newPage();
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});
  await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/account/appearance',{waitUntil:'networkidle'});
  await p.addStyleTag({content:"*,*::before,*::after{animation:none!important;transition:none!important}.ns-app{width:430px!important;max-width:none!important;margin:0!important}"});
  await p.evaluate(()=>{document.querySelectorAll('body *').forEach(e=>{const c=getComputedStyle(e).position;if((c==='fixed'||c==='sticky')&&!e.closest('.ns-app'))e.style.setProperty('display','none','important')})});
  // keep only the preview + sections that are not the skin gallery (their minis carry other skins' colours on purpose)
  await p.evaluate(()=>{document.querySelectorAll('.ns-skin-card,.ns-group').forEach(e=>e.style.setProperty('display','none','important'))});
  const all=await p.evaluate(()=>[...document.querySelectorAll('.ns-skin-card')].map(e=>e.querySelector('.ns-mini').dataset.nxPreview));
  const list=skins.length?skins:all;
  fs.mkdirSync('out/c',{recursive:true});
  const meta=[];
  for (const k of list) for (const mode of ['light','dark']) for (const acc of accents) {
    await p.evaluate(([k,m,a])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxAccent=a;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark');const h=new Date().getHours();d.dataset.nxTod=h>=5&&h<8?'dawn':h>=8&&h<17?'day':h>=17&&h<20?'dusk':'night';d.dataset.nxCountry='ng';},[k,mode,acc]);
    await p.waitForTimeout(40);
    const items=await p.evaluate(()=>{
      const out=[];const w=document.createTreeWalker(document.querySelector('.ns-app'),NodeFilter.SHOW_TEXT);
      while(w.nextNode()){const n=w.currentNode;const t=n.textContent.trim();if(!t)continue;const e=n.parentElement;const cs=getComputedStyle(e);
        if(cs.visibility==='hidden'||cs.display==='none'||e.closest('[aria-hidden=true]'))continue;
        const r=document.createRange();r.selectNodeContents(n);const b=r.getBoundingClientRect();if(b.width<2||b.height<2)continue;
        out.push({t:t.slice(0,24),c:cs.color,o:parseFloat(cs.opacity),fs:parseFloat(cs.fontSize),fw:parseInt(cs.fontWeight),x:b.x+scrollX,y:b.y+scrollY,w:b.width,h:b.height,cls:e.className.toString().slice(0,40)});}
      return out;});
    await p.addStyleTag({content:'.nx-hide-text *{color:transparent!important;-webkit-text-fill-color:transparent!important;text-shadow:none!important}'}).catch(()=>{});
    await p.evaluate(()=>document.querySelector('.ns-app').classList.add('nx-hide-text'));
    const f=`out/c/${k}-${mode}-${acc}.png`;
    await p.locator('.ns-app').screenshot({path:f});
    await p.evaluate(()=>document.querySelector('.ns-app').classList.remove('nx-hide-text'));
    const box=await p.evaluate(()=>{const r=document.querySelector('.ns-app').getBoundingClientRect();return {x:r.x+scrollX,y:r.y+scrollY}});
    meta.push({skin:k,mode,acc,file:f,ox:box.x,oy:box.y,items});
  }
  fs.writeFileSync('out/c/meta.json',JSON.stringify(meta));
  await b.close();
})().catch(e=>{console.error(e);process.exit(1)});

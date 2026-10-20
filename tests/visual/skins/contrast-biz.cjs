// Contrast scan over real converted pages (not just the Appearance page): every skin x mode x accent on each URL.
//   URLS="/numbers,/numbers?modal=verify" SKINS=neo,glass ACCENTS=teal,rose node contrast-pages.cjs && python3 contrast-pages.py
const path=require('path');
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const fs=require('fs');
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
const URLS=(process.env.URLS||'/numbers,/numbers?modal=verify,/numbers?modal=rent,/numbers?modal=line,/catalogue?plan=2,/checkout/2').split(',');
const accents=(process.env.ACCENTS||'teal').split(',');
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  const ctx=await b.newContext({viewport:{width:+(process.env.VW||430),height:932},deviceScaleFactor:1});
  const p=await ctx.newPage();
  if(process.env.ROOT)await p.addInitScript(r=>{window.__ROOT=r},process.env.ROOT);   // ROOT=main for admin pages (they are not inside .ns-app)
  await p.goto(BASE+(process.env.LOGIN||'/login'),{waitUntil:'networkidle'});
  await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/dashboard',{waitUntil:'networkidle'});
  { const tok=await p.evaluate(()=>document.querySelector('meta[name=csrf-token]')?.content); await p.evaluate(async([t,id])=>{await fetch('/workspace/switch/'+id,{method:'POST',headers:{'X-CSRF-TOKEN':t},redirect:'manual'})},[tok,'2']); }
  fs.mkdirSync((process.env.OUT||'out/biz'),{recursive:true});
  await p.goto(BASE+'/account/appearance',{waitUntil:'networkidle'});
  const all=(process.env.SKINS||'').split(',').filter(Boolean).length?(process.env.SKINS).split(','):await p.evaluate(()=>[...document.querySelectorAll('.ns-skin-card .ns-mini')].map(e=>e.dataset.nxPreview));
  const meta=[];
  for (const url of URLS){
    await p.goto(BASE+url,{waitUntil:'networkidle'});
    await p.addStyleTag({content:"*,*::before,*::after{animation:none!important;transition:none!important}"});
    // fixed floating chrome (nav, widgets) is platform chrome, not part of the skin
    await p.evaluate(()=>{document.querySelectorAll('body *').forEach(e=>{const c=getComputedStyle(e).position;if((c==='fixed'||c==='sticky')&&!e.closest('.ns-modal,.ns-app,.ns-sheet,.nn'))e.style.setProperty('display','none','important')})});
    // The skin canvas is `background-attachment: fixed` (it covers the viewport edge to edge). A tall full-page screenshot only paints it for
    // the first viewport, which would make everything below the fold read as white. Grow the viewport to the page height so what is sampled is
    // what a real viewport shows at every scroll position.
    if (!url.includes('modal=')) { const H=await p.evaluate(()=>document.documentElement.scrollHeight); await p.setViewportSize({width:+(process.env.VW||430),height:Math.min(Math.max(H,932),7000)}); await p.waitForTimeout(300); }
    for (const k of all) for (const mode of ['light','dark']) for (const acc of accents){
      await p.evaluate(([k,m,a])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxAccent=a;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark');const h=new Date().getHours();d.dataset.nxTod=h>=5&&h<8?'dawn':h>=8&&h<17?'day':h>=17&&h<20?'dusk':'night';d.dataset.nxCountry='ng';},[k,mode,acc]);
      await p.waitForTimeout(160);
      const vpOnly=url.includes('modal=');
      const items=await p.evaluate((vpOnly)=>{
        const out=[];const modal=[...document.querySelectorAll('.ns-modal')].filter(m=>m.getClientRects().length);const roots=modal.length?modal:[...document.querySelectorAll(window.__ROOT||'.ns-app')];   // behind a modal the page is dimmed by the scrim: only the modal is judged
        for(const root of roots){const w=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);
          while(w.nextNode()){const n=w.currentNode;const t=n.textContent.trim();if(!t)continue;const e=n.parentElement;const cs=getComputedStyle(e);
            if(cs.visibility==='hidden'||cs.display==='none'||e.closest('[aria-hidden=true],.nx-float-card,[x-cloak],[disabled]'))continue;
            const r=document.createRange();r.selectNodeContents(n);const b=r.getBoundingClientRect();if(b.width<2||b.height<2)continue;
            if(vpOnly&&(b.bottom<0||b.top>innerHeight))continue;
            const grad=e.classList.contains('ns-gradtext');const clip=(cs.webkitBackgroundClip==='text'||cs.backgroundClip==='text')&&!grad;   // gradient-filled headline: its colour is a brand gradient, not a flat ink
            const sel=[e,e.parentElement,e.parentElement&&e.parentElement.parentElement].filter(Boolean).map(x=>x.tagName.toLowerCase()+'.'+String(x.className).split(' ').filter(Boolean).slice(0,2).join('.')).join(' < ');
            if(clip||e.closest('.nx-imghero')||b.left>innerWidth||parseFloat(cs.opacity)<0.05)continue;
            {let a=e.parentElement,hid=false;while(a&&a!==document.body){const o=getComputedStyle(a).overflowX;if(o!=='visible'){const ar=a.getBoundingClientRect();if(b.left>=ar.right-1||b.right<=ar.left+1){hid=true;break}}a=a.parentElement}if(hid)continue}   // gradient-clip headline / admin-controlled banner artwork
            {const t0=document.elementFromPoint(b.x+b.width/2,b.y+b.height/2);if(t0&&!e.contains(t0)&&!t0.contains(e))continue;}
            for(const col of (grad?(cs.backgroundImage.match(/(?:rgb|color)\([^)]*\)/g)||[]).filter((v,i,a)=>a.indexOf(v)===i):[cs.color]))out.push({sel,t:t.slice(0,24),c:col,o:parseFloat(cs.opacity),fs:parseFloat(cs.fontSize),fw:parseInt(cs.fontWeight),x:b.x+scrollX,y:b.y+scrollY,w:(()=>{const er=e.getBoundingClientRect();return (getComputedStyle(e).overflowX!=='visible'&&b.right>er.right)?Math.max(2,er.right-b.left):b.width})(),h:b.height,cls:e.className.toString().slice(0,40)});}}
        return out;},vpOnly);
      await p.evaluate(()=>{let s=document.getElementById('nx-hide');if(!s){s=document.createElement('style');s.id='nx-hide';s.textContent='.nx-hide-text *{color:transparent!important;-webkit-text-fill-color:transparent!important;text-shadow:none!important}.nx-hide-text .ns-gradtext{background-image:none!important}';document.head.appendChild(s)}document.documentElement.classList.add('nx-hide-text')});
      const f=`${process.env.OUT||'out/biz'}/${url.replace(/[^a-z0-9]+/gi,'_')}-${k}-${mode}-${acc}.png`;
      await p.screenshot({path:f,fullPage:!vpOnly});
      await p.evaluate(()=>document.documentElement.classList.remove('nx-hide-text'));
      meta.push({url,skin:k,mode,acc,file:f,items});
    }
  }
  fs.writeFileSync((process.env.OUT||'out/biz')+'/meta.json',JSON.stringify(meta));
  await b.close();
})().catch(e=>{console.error(e);process.exit(1)});

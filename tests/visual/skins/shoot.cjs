// Run from this folder: node shoot.cjs   (needs the app on BASE, default http://127.0.0.1:8099, and a verified member NX_EMAIL/NX_PASSWORD).
// Compares every wireframe skin preview with the production one. Output: ./out. Then: python3 diff.py 0.02
// Captures wireframe + production previews per skin x mode into ./out/{wf,prod}/<skin>-<mode>-<what>.png
const path=require('path');
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const ROOT=path.resolve(__dirname,'../../..');
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const fs=require('fs');
const WF='file://'+ROOT+'/docs/appearance/wireframes/naara-dashboard-system-';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
const FORCE="*,*::before,*::after{font-family:'DejaVu Sans',sans-serif !important;animation:none !important;transition:none !important;caret-color:transparent}";
const only=(process.env.ONLY||'').split(',').filter(Boolean);
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  fs.mkdirSync('out/wf',{recursive:true});fs.mkdirSync('out/prod',{recursive:true});
  // ---- wireframe
  const wfSkins={};
  for (const f of ['v9-surface-core13','v15-new22-skins-lab']) {
    const p=await b.newPage({viewport:{width:430,height:900},deviceScaleFactor:1});
    await p.goto(WF+f+'.html');await p.waitForTimeout(400);
    await p.addStyleTag({content:FORCE+'.mini{display:block;width:360px}.prev{width:360px;box-sizing:border-box}'});
    await p.evaluate(()=>go('appearance'));
    const keys=await p.evaluate(()=>[...document.querySelectorAll('.mini')].map(e=>e.dataset.skin));
    for (const k of keys){ if(wfSkins[k]) continue; wfSkins[k]=1; if(only.length&&!only.includes(k)) continue;
      for (const mode of ['light','dark']){
        await p.evaluate(([k,m])=>{const d=document.documentElement;d.dataset.skin=k;d.dataset.accent='teal';d.dataset.theme=m;},[k,mode]);
        await p.waitForTimeout(60);
        await p.locator(`.mini[data-skin="${k}"]`).first().screenshot({path:`out/wf/${k}-${mode}-mini.png`});
        await p.locator('.prev').first().screenshot({path:`out/wf/${k}-${mode}-prev.png`});
      }}
    await p.close();
  }
  // ---- production
  const ctx=await b.newContext({viewport:{width:430,height:900},deviceScaleFactor:1});
  const p=await ctx.newPage();
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});
  await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/account/appearance',{waitUntil:'networkidle'});
  await p.addStyleTag({content:FORCE+'.ns-mini{display:block;width:360px}.ns-preview{width:360px;box-sizing:border-box}.ns-app{width:430px !important;max-width:none !important;margin:0 !important}'});
  await p.evaluate(()=>{document.querySelectorAll('body *').forEach(e=>{const c=getComputedStyle(e).position;if((c==='fixed'||c==='sticky')&&!e.closest('.ns-app'))e.style.setProperty('display','none','important')})});
  console.log('url',p.url());
  for (const k of Object.keys(wfSkins)){ if(only.length&&!only.includes(k)) continue;
    for (const mode of ['light','dark']){
      await p.evaluate(([k,m])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxAccent='teal';d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark');const h=new Date().getHours();d.dataset.nxTod=h>=5&&h<8?'dawn':h>=8&&h<17?'day':h>=17&&h<20?'dusk':'night';d.dataset.nxCountry='ng';document.querySelectorAll('.ns-app').forEach(a=>a.dataset.cc='NG');},[k,mode]);
      await p.waitForTimeout(60);
      console.log('minis',await p.locator('.ns-mini').count(), await p.locator('.ns-mini').first().getAttribute('data-nx-preview'));const mini=p.locator(`.ns-mini[data-nx-preview="${k}"]`).first();
      if(await mini.count()) await mini.screenshot({path:`out/prod/${k}-${mode}-mini.png`}); else console.log('no mini for',k);
      await p.locator('.ns-preview').first().screenshot({path:`out/prod/${k}-${mode}-prev.png`});
    }}
  await b.close();
})().catch(e=>{console.error(e);process.exit(1)});

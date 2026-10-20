// Skin matrix: every skin x light/dark x 430/1280 x the converted pages. No reload per skin (the skin is a root attribute).
// Reports: JS/console errors, horizontal overflow, elements poking out of the viewport, and that the skin really repaints the canvas.
// Output: out/matrix.json + out/matrix-<mode>.png contact sheet inputs (dashboard @430). Run: node matrix.cjs ; python3 matrix_report.py
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const fs=require('fs');
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE=process.env.BASE||'http://127.0.0.1:8099';
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const SKINS=(process.env.SKINS||'surface,neo,glass,dotgrid,clay,adire,airmail,ankara,asooke,aurora,blueprint,boarding,calm,canopy,chipset,clarity,danfo,daybreak,golden,halo,handset,harmattan,ledger,nebula,neon,noir,paper,passport,pop,prism,receipt,tide,vault,vivid,wavelength').split(',');
const PAGES=(process.env.URLS||'/dashboard,/wallet,/numbers,/catalogue,/account,/rewards,/referrals,/journey,/receipts,/data-estimator').split(',');
(async()=>{
  const b=await chromium.launch({executablePath:CHROME}); fs.mkdirSync('out/matrix',{recursive:true});
  const res=[]; const errs={};
  for (const mode of ['light','dark']) for (const w of [430,1280]) {
    const ctx=await b.newContext({viewport:{width:w,height:900},colorScheme:mode}); const p=await ctx.newPage();
    p.on('pageerror',e=>{(errs[p.url()]=errs[p.url()]||[]).push('pageerror: '+e.message)});
    p.on('console',m=>{if(m.type()==='error'&&!/Failed to load resource/.test(m.text()))(errs[p.url()]=errs[p.url()]||[]).push('console: '+m.text().slice(0,160))});
    await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
    await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
    for (const url of PAGES) {
      await p.goto(BASE+url,{waitUntil:'networkidle'}).catch(()=>{}); await p.waitForTimeout(400);
      if (!/\/login/.test(p.url())===false) { res.push({mode,w,url,skin:'*',error:'redirected to login'}); continue; }
      const sigs=new Set();
      for (const skin of SKINS) {
        await p.evaluate(([s,m])=>{const d=document.documentElement;d.dataset.nxSkin=s;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark');},[skin,mode]);
        await p.waitForTimeout(60);
        const m=await p.evaluate(()=>{
          const W=innerWidth; const bs=getComputedStyle(document.body);
          const out=[...document.querySelectorAll('main *, .ns-app *')].filter(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.height>0&&(r.right>W+2||r.left<-2)&&getComputedStyle(e).position!=='fixed'&&!e.closest('.nx-imghero,[class*=overflow-x],[style*=overflow],.nx-scroll,.ns-scroll,.ns-rail,.ns-dests,.ns-cats')}).length;
          return {overflowX:document.documentElement.scrollWidth>W+1, poking:out, bg:bs.backgroundColor+'|'+bs.backgroundImage.slice(0,60), applied:document.documentElement.dataset.nxSkin};
        });
        sigs.add(m.bg);
        res.push({mode,w,url,skin,...m});
        if (url==='/dashboard'&&w===430) await p.screenshot({path:`out/matrix/${skin}-${mode}.png`});
      }
      res.push({mode,w,url,skin:'*distinct-canvases',n:sigs.size});
    }
    await ctx.close();
  }
  fs.writeFileSync('out/matrix.json',JSON.stringify({res,errs},null,1)); await b.close(); console.log('done',res.length);
})();

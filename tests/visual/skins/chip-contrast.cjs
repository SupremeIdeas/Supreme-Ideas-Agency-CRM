// Language-chip contrast across every skin x mode x accent (owner 2026-10-05: "apply contrast discipline across all skins, no exceptions").
//   BASE=http://127.0.0.1:8765 NX_EMAIL=frank@n2n.test NX_PASSWORD=password node chip-contrast.cjs
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
const EMAIL=process.env.NX_EMAIL||'nx-diff@naara.test', PASS=process.env.NX_PASSWORD||'password';
const URLS=(process.env.URLS||'/numbers').split(',');
const lum=([r,g,b])=>{const f=v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)};return .2126*f(r)+.7152*f(g)+.0722*f(b)};
const ratio=(a,b)=>{const [x,y]=[lum(a),lum(b)].sort((m,n)=>n-m);return (x+.05)/(y+.05)};
const rgb=s=>(s.match(/[\d.]+/g)||[]).slice(0,4).map(Number);
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  const p=await (await b.newContext({viewport:{width:430,height:932}})).newPage();
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});
  await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/account/appearance',{waitUntil:'networkidle'});
  const skins=(process.env.SKINS||'').split(',').filter(Boolean).length?process.env.SKINS.split(','):await p.evaluate(()=>[...document.querySelectorAll('.ns-skin-card .ns-mini')].map(e=>e.dataset.nxPreview));
  let bad=0,n=0,min=99;
  for(const url of URLS){
    await p.goto(BASE+url,{waitUntil:'networkidle'});
    await p.addStyleTag({content:"*,*::before,*::after{animation:none!important;transition:none!important}"});
    for(const k of skins)for(const mode of ['light','dark'])for(const acc of (process.env.ACCENTS||'teal,rose,violet').split(',')){
      await p.evaluate(([k,m,a])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxAccent=a;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[k,mode,acc]);
      await p.waitForTimeout(60);
      const r=await p.evaluate(()=>{const e=[...document.querySelectorAll('.nx-lang-chip')].find(x=>x.getClientRects().length);if(!e)return null;const c=getComputedStyle(e),t=e.querySelector('span'),ic=e.querySelector('svg');return{col:getComputedStyle(t).color,icon:getComputedStyle(ic).color,bg:c.backgroundColor,bd:c.borderColor,op:c.opacity}});
      if(!r){console.log('NO CHIP',url,k);bad++;continue}
      n++;const bg=rgb(r.bg);const alpha=bg[3]===undefined?1:bg[3];
      const t=ratio(rgb(r.col),bg),i=ratio(rgb(r.icon),bg);min=Math.min(min,t,i);
      if(alpha<1||t<4.5||i<3||parseFloat(r.op)<1){bad++;console.log('FAIL',url,k,mode,acc,'text',t.toFixed(2),'icon',i.toFixed(2),'alpha',alpha)}
    }
  }
  console.log(`chip checks: ${n}, failures: ${bad}, lowest ratio: ${min.toFixed(2)}`);
  await b.close();process.exit(bad?1:0);
})();

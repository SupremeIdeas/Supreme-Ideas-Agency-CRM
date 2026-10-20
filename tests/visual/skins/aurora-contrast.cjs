// The channel aurora on the Business inbox conversation header: sample the rendered pixels (text hidden) at both ends of the drifting animation, in every skin x mode x
// channel, and check white text (title, and the 94% white subtitle) holds >= 4.5:1 on the WORST pixel. Also prints the brightest pixel so a tune is easy.
//   BASE=http://127.0.0.1:8765 NX_EMAIL=frank@n2n.test NX_PASSWORD=password [VW=390] [SKINS=a,b] [OUT=/tmp/aurora] node aurora-contrast.cjs
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE=process.env.BASE||'http://127.0.0.1:8765';const OUT=process.env.OUT||'/tmp/aurora';
require('fs').mkdirSync(OUT,{recursive:true});
const lin=v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)};
const lum=(r,g,b)=>.2126*lin(r)+.7152*lin(g)+.0722*lin(b);
(async()=>{
 const b=await chromium.launch({executablePath:CHROME});
 const p=await (await b.newContext({viewport:{width:+(process.env.VW||390),height:800},deviceScaleFactor:1})).newPage();
 await p.goto(BASE+'/login',{waitUntil:'networkidle'});
 await p.fill('input[name="email"]',process.env.NX_EMAIL||'frank@n2n.test');await p.fill('input[name="password"]',process.env.NX_PASSWORD||'password');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
 await p.goto(BASE+'/dashboard',{waitUntil:'networkidle'});
 const tok=await p.evaluate(()=>document.querySelector('meta[name=csrf-token]')?.content);
 await p.evaluate(async t=>{await fetch('/workspace/switch/2',{method:'POST',headers:{'X-CSRF-TOKEN':t},redirect:'manual'})},tok);
 await p.goto(BASE+'/account/appearance',{waitUntil:'networkidle'});
 const all=process.env.SKINS?process.env.SKINS.split(','):await p.evaluate(()=>[...document.querySelectorAll('.ns-skin-card .ns-mini')].map(e=>e.dataset.nxPreview));
 await p.goto(BASE+'/numbers/messages?to=5551&ch=telegram',{waitUntil:'networkidle'});await p.waitForSelector('.nc__thead',{timeout:15000});await p.waitForTimeout(1500);
 let n=0,bad=0,worst=0,worstAt='';
 for(const k of all)for(const mode of ['light','dark'])for(const ch of ['telegram','whatsapp','instagram','sms'])for(const end of [0,1]){
  await p.evaluate(([k,m,ch,end])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark');
   const t=document.querySelector('.bz-msg .nc__thread');t.dataset.ch=ch;
   let s=document.getElementById('au-test');if(!s){s=document.createElement('style');s.id='au-test';document.head.append(s)}
   s.textContent=`.nc__thead > *{visibility:hidden!important}.nc__thead::before{animation-play-state:paused!important;animation-duration:16s!important;animation-delay:${end?-16:0}s!important;animation-fill-mode:both!important}`;
  },[k,mode,ch,end]);
  await p.waitForTimeout(60);
  const el=await p.$('.bz-msg .nc__thead');const png=(await el.screenshot()).toString('base64');
  const r=await p.evaluate(async png=>{const i=new Image();i.src='data:image/png;base64,'+png;await i.decode();const c=document.createElement('canvas');c.width=i.width;c.height=i.height;const x=c.getContext('2d');x.drawImage(i,0,0);const d=x.getImageData(0,0,c.width,c.height).data;
    const L=(r,g,b)=>{const f=v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)};return .2126*f(r)+.7152*f(g)+.0722*f(b)};
    let mx=0,mr=0,mg=0,mb=0;for(let j=0;j<d.length-c.width*4*3;j+=4){const l=L(d[j],d[j+1],d[j+2]);if(l>mx){mx=l;mr=d[j];mg=d[j+1];mb=d[j+2]}}return{mx,mr,mg,mb}},png);
  // white title on the brightest pixel; subtitle is 94% white over that pixel
  const ratio=1.05/(r.mx+.05);const sub=lum(...[0,1,2].map(i=>255*.94+[r.mr,r.mg,r.mb][i]*.06))+.05;const ratio2=sub/(r.mx+.05);
  const m=Math.min(ratio,ratio2);n++;if(m<4.5){bad++;console.log('FAIL',k,mode,ch,end?'end':'start','worst-pixel',[r.mr,r.mg,r.mb].join(','),'ratio',m.toFixed(2))}
  if(!worst||m<worst){worst=m;worstAt=`${k}/${mode}/${ch}`}
 }
 console.log(`aurora checks: ${n}, failures: ${bad}, lowest ratio: ${worst.toFixed(2)} (${worstAt})`);
 await b.close();process.exit(bad?1:0);})();

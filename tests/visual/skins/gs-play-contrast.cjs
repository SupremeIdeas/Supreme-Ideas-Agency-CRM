// GIF Studio play button (and the other round controls): icon vs button contrast in every skin x mode, in the Business inbox composer.
//   BASE=http://127.0.0.1:8765 node gs-play-contrast.cjs
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';const BASE=process.env.BASE||'http://127.0.0.1:8765';
const lin=v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)};const L=([r,g,b])=>.2126*lin(r)+.7152*lin(g)+.0722*lin(b);
const rgb=s=>(s.match(/[\d.]+/g)||[]).slice(0,4).map(Number);const ratio=(a,b)=>{const [x,y]=[L(a),L(b)].sort((m,n)=>n-m);return (x+.05)/(y+.05)};
(async()=>{const b=await chromium.launch({executablePath:CHROME});const p=await (await b.newContext({viewport:{width:390,height:800}})).newPage();
 await p.goto(BASE+'/login');await p.fill('input[name="email"]',process.env.NX_EMAIL||'frank@n2n.test');await p.fill('input[name="password"]',process.env.NX_PASSWORD||'password');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
 await p.goto(BASE+'/dashboard',{waitUntil:'networkidle'});const t=await p.evaluate(()=>document.querySelector('meta[name=csrf-token]')?.content);
 await p.evaluate(async t=>{await fetch('/workspace/switch/2',{method:'POST',headers:{'X-CSRF-TOKEN':t},redirect:'manual'})},t);
 await p.goto(BASE+'/account/appearance',{waitUntil:'networkidle'});const skins=await p.evaluate(()=>[...document.querySelectorAll('.ns-skin-card .ns-mini')].map(e=>e.dataset.nxPreview));
 await p.goto(BASE+'/numbers/messages?to=5551&ch=telegram',{waitUntil:'networkidle'});await p.waitForSelector('.cc-text');await p.waitForTimeout(1200);
 await p.click('.bz-msg .cc [class*="gif"]');await p.waitForTimeout(300);
 const clip=await p.evaluate(async()=>{const c=document.createElement('canvas');c.width=160;c.height=90;const x=c.getContext('2d');const rec=new MediaRecorder(c.captureStream(10),{mimeType:'video/webm'});const ch=[];rec.ondataavailable=e=>ch.push(e.data);rec.start();const t0=performance.now();await new Promise(r=>{const f=()=>{x.fillStyle='hsl('+((performance.now()-t0)/10)+',70%,45%)';x.fillRect(0,0,160,90);if(performance.now()-t0<1500)requestAnimationFrame(f);else r()};f()});rec.stop();await new Promise(r=>rec.onstop=r);return Array.from(new Uint8Array(await new Blob(ch,{type:'video/webm'}).arrayBuffer()))});
 const [fc]=await Promise.all([p.waitForEvent('filechooser'),p.getByText('Video clip → GIF').first().click()]);await fc.setFiles({name:'c.webm',mimeType:'video/webm',buffer:Buffer.from(clip)});await p.waitForSelector('.cc-gs');await p.waitForTimeout(1200);
 let n=0,bad=0,min=99;
 for(const k of skins)for(const mode of ['light','dark']){
  await p.evaluate(([k,m])=>{const d=document.documentElement;d.dataset.nxSkin=k;d.dataset.nxMode=m;d.classList.toggle('dark',m==='dark')},[k,mode]);await p.waitForTimeout(40);
  const r=await p.evaluate(()=>[...document.querySelectorAll('.cc-gs .cc-gs-ib')].map(b=>{const s=getComputedStyle(b),svg=getComputedStyle(b.querySelector('svg'));return{play:b.classList.contains('cc-gs-play'),fg:svg.fill&&svg.fill!=='none'?svg.fill:svg.stroke,col:s.color,bg:s.backgroundColor}}));
  for(const x of r){const bg=rgb(x.bg);const fg=rgb(x.fg.startsWith('rgb')?x.fg:x.col);const bgOpaque=bg.length>3&&bg[3]<1?null:bg;const surf=bgOpaque||[255,255,255];const c=ratio(fg,surf);n++;min=Math.min(min,c);if(c<4.5){bad++;console.log('FAIL',k,mode,x.play?'play':'step','icon',c.toFixed(2),x.fg,x.bg)}}
 }
 console.log(`round-control checks: ${n}, failures: ${bad}, lowest: ${min.toFixed(2)}`);await b.close();process.exit(bad?1:0)})();

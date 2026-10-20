// Business inbox composer end to end on a phone: the full Naara feature set is offered, a file send reaches the inbox endpoint (and a refusal is shown as a sentence),
// and the GIF Studio keeps Make / Save / Use pinned and visible.
//   BASE=http://127.0.0.1:8765 NX_EMAIL=frank@n2n.test NX_PASSWORD=password OUT=/tmp/inbox-e2e node inbox-composer-e2e.cjs
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE=process.env.BASE||'http://127.0.0.1:8765';const OUT=process.env.OUT||'/tmp/inbox-e2e';
require('fs').mkdirSync(OUT,{recursive:true});
let fails=0;const ok=(c,m)=>{console.log((c?'PASS ':'FAIL ')+m);if(!c)fails++};
(async()=>{
 const b=await chromium.launch({executablePath:CHROME,args:['--autoplay-policy=no-user-gesture-required']});
 const p=await (await b.newContext({viewport:{width:390,height:800},deviceScaleFactor:2,hasTouch:true})).newPage();
 p.on('pageerror',e=>{console.log('PAGEERROR',e.message);fails++});
 await p.goto(BASE+'/login',{waitUntil:'networkidle'});
 await p.fill('input[name="email"]',process.env.NX_EMAIL||'frank@n2n.test');await p.fill('input[name="password"]',process.env.NX_PASSWORD||'password');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
 await p.goto(BASE+'/dashboard',{waitUntil:'networkidle'});
 const tok=await p.evaluate(()=>document.querySelector('meta[name=csrf-token]')?.content);
 await p.evaluate(async t=>{await fetch('/workspace/switch/2',{method:'POST',headers:{'X-CSRF-TOKEN':t},redirect:'manual'})},tok);
 await p.goto(BASE+'/numbers/messages?to=5551&ch=telegram',{waitUntil:'networkidle'});await p.waitForSelector('.cc-text',{timeout:15000});await p.waitForTimeout(1500);
 await p.screenshot({path:OUT+'/1-composer.png'});

 // 1. features on the toolbar
 const tools=await p.evaluate(()=>[...document.querySelectorAll('.bz-msg .cc button')].map(x=>(x.getAttribute('aria-label')||x.title||x.textContent||'').trim()).filter(Boolean));
 console.log('toolbar:',JSON.stringify(tools));
 for(const f of ['emoji','gif','attach','schedule','bold','text styles'])ok(tools.some(t=>t.toLowerCase().includes(f)),`toolbar offers ${f}`);

 // 2. attach a file and send: the request goes to the inbox endpoint
 await p.setInputFiles('.bz-msg .cc input[type=file]',{name:'quote.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF')}).catch(e=>ok(false,'attach input: '+e.message));
 await p.fill('.bz-msg .cc-text','Here is the quote').catch(()=>{});
 await p.waitForTimeout(400);await p.screenshot({path:OUT+'/2-attached.png'});
 const req=p.waitForResponse(r=>r.url().includes('/business/inbox/send'),{timeout:20000}).catch(()=>null);
 await p.click('.bz-msg .cc-send');
 const resp=await req;ok(!!resp,'composer posts to /business/inbox/send');
 if(resp){const j=await resp.json().catch(()=>({}));console.log('send ->',resp.status(),JSON.stringify(j));ok(resp.status()===200||(resp.status()===422&&!!j.message),'answered with a result or a plain-sentence refusal');}
 await p.waitForTimeout(1200);await p.screenshot({path:OUT+'/3-after-send.png'});

 // 3. GIF Studio: make a 3s test clip in the page, open the studio, check the action bar stays in view
 await p.click('.bz-msg .cc [class*="gif"]').catch(()=>{});await p.waitForTimeout(500);await p.screenshot({path:OUT+'/4-gifpanel.png'});
 const clip=await p.evaluate(async()=>{const c=document.createElement('canvas');c.width=320;c.height=180;const x=c.getContext('2d');const s=c.captureStream(15);const rec=new MediaRecorder(s,{mimeType:'video/webm'});const ch=[];rec.ondataavailable=e=>ch.push(e.data);rec.start();const t0=performance.now();
   await new Promise(r=>{const f=()=>{const t=(performance.now()-t0)/1000;x.fillStyle=`hsl(${t*90},70%,45%)`;x.fillRect(0,0,320,180);x.fillStyle='#fff';x.font='bold 60px sans-serif';x.fillText(t.toFixed(1),90,110);if(t<3)requestAnimationFrame(f);else r()};f()});
   rec.stop();await new Promise(r=>rec.onstop=r);const blob=new Blob(ch,{type:'video/webm'});return Array.from(new Uint8Array(await blob.arrayBuffer()))});
 const [chooser]=await Promise.all([p.waitForEvent('filechooser',{timeout:8000}).catch(()=>null),p.getByText('Video clip → GIF',{exact:false}).first().click().catch(()=>{})]);
 if(chooser){await chooser.setFiles({name:'clip.webm',mimeType:'video/webm',buffer:Buffer.from(clip)});await p.waitForSelector('.cc-gs',{timeout:15000}).catch(()=>{});await p.waitForTimeout(1800);}
 const gs=await p.$('.cc-gs');ok(!!gs,'GIF Studio opens from a video');
 if(gs){await p.screenshot({path:OUT+'/5-studio.png'});
  const vis=await p.evaluate(()=>{const f=document.querySelector('.cc-gs-foot');if(!f)return null;const r=f.getBoundingClientRect();const pr=f.closest('.cc-gmaker').getBoundingClientRect();return{top:r.top,bottom:r.bottom,pbottom:pr.bottom,vh:innerHeight,btns:[...f.querySelectorAll('button')].map(x=>x.textContent.trim())}});
  console.log('studio foot',JSON.stringify(vis));
  ok(vis&&vis.bottom<=vis.vh+1&&vis.bottom<=vis.pbottom+1,'Make / Save / Use bar is inside the viewport and the panel without scrolling');}
 await b.close();console.log(fails?`FAILED ${fails}`:'ALL PASS');process.exit(fails?1:0);})();

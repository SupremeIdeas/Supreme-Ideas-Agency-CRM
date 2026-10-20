// Preloader Studio custom upload: upload light.gif + dark.json in the Studio, save, screenshot; then check the public overlay per mode.
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE=process.env.BASE||'http://127.0.0.1:8099', DIR=process.env.FILES;
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  const ctx=await b.newContext({viewport:{width:1280,height:1000},colorScheme:'dark'});
  const p=await ctx.newPage();
  p.on('pageerror',e=>console.log('PAGEERROR',e.message));
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});
  await p.fill('input[name="email"]','supremeideasz@gmail.com');await p.fill('input[name="password"]','password');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/adminmaster/preloader-studio',{waitUntil:'networkidle'});
  console.log('studio:',p.url());
  await p.click('button:has-text("Your own animation")');
  await p.waitForSelector('text=Light mode');
  const inputs=await p.$$('input[type=file]');
  console.log('file inputs',inputs.length);
  await inputs[0].setInputFiles(DIR+'/light.gif'); await p.waitForTimeout(1500);
  await inputs[1].setInputFiles(DIR+'/dark.json'); await p.waitForTimeout(1500);
  await p.screenshot({path:'out/pl-studio.png',fullPage:true});
  { const cb=p.locator('label:has-text("Show a preloader") input'); if(!(await cb.isChecked())) await cb.check(); await p.waitForTimeout(500); }
  await p.click('button:has-text("Save")'); await p.waitForTimeout(1200);
  console.log('saved text:',await p.locator('text=Saved').count());
  for (const mode of ['light','dark']) {
    const c2=await b.newContext({viewport:{width:900,height:700},colorScheme:mode});
    const q=await c2.newPage();
    await q.addInitScript(m=>{try{localStorage.setItem('theme',m)}catch(e){}},mode);
    await q.goto(BASE+'/login',{waitUntil:'domcontentloaded'});
    await q.waitForSelector('.nx-pl-custom img, .nx-pl-custom svg',{timeout:5000}).catch(()=>{});
    await q.screenshot({path:`out/pl-overlay-${mode}.png`});
    console.log(mode,'custom el:',await q.evaluate(()=>{const e=document.querySelector('.nx-pl-custom');return e?{img:!!e.querySelector('img'),svg:!!e.querySelector('svg'),url:e.getAttribute('data-light-url')}:null}));
    await c2.close();
  }
  await b.close();
})();

// Screenshot an admin page. URL=/adminmaster/security SKIN= MODE= W= node admin.cjs -> out/admin-<name>.png (full page)
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const EMAIL=process.env.ADMIN_EMAIL||'supremeideasz@gmail.com', PASS=process.env.ADMIN_PASSWORD||'password';
const BASE=process.env.BASE||'http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  const mode=process.env.MODE||'dark', w=+(process.env.W||1280);
  const ctx=await b.newContext({viewport:{width:w,height:+(process.env.H||900)},colorScheme:mode});
  const p=await ctx.newPage();
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});
  await p.fill('input[name="email"]',EMAIL);await p.fill('input[name="password"]',PASS);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  console.log('after login:',p.url());
  await p.goto(BASE+(process.env.URL||'/adminmaster/security'),{waitUntil:'networkidle'});
  console.log('page:',p.url());
  await p.waitForTimeout(500);
  await p.screenshot({path:`out/admin-${process.env.NAME||'page'}-${mode}-${w}.png`,fullPage:process.env.FULL==='1'});
  await b.close();
})();

// Naara Pro badge: brand profile + Hunt card + account (member), admin directory (admin). BRAND=<id>
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
async function login(b,email,mode,w){const ctx=await b.newContext({viewport:{width:w,height:900},colorScheme:mode});const p=await ctx.newPage();
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]',email);await p.fill('input[name="password"]','password');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);return p;}
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  for (const mode of ['light','dark']) {
    const p=await login(b,'nx-diff@naara.test',mode,430);
    await p.goto(BASE+'/rewards/hunt/'+process.env.BRAND,{waitUntil:'networkidle'}).catch(()=>{});
    console.log(p.url());
    await p.waitForTimeout(600);await p.screenshot({path:`out/pro-profile-${mode}.png`});
    await p.goto(BASE+'/account',{waitUntil:'networkidle'});await p.waitForTimeout(400);await p.screenshot({path:`out/pro-account-${mode}.png`});
  }
  const a=await login(b,'supremeideasz@gmail.com','dark',1100);
  await a.goto(BASE+'/adminmaster/brand-directory',{waitUntil:'networkidle'});console.log(a.url());await a.waitForTimeout(500);
  await a.screenshot({path:'out/pro-admin.png',fullPage:true});
  await b.close();
})();

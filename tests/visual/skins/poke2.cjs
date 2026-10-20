let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
(async()=>{const b=await chromium.launch({executablePath:CHROME});const p=await (await b.newContext({viewport:{width:430,height:900}})).newPage();
await p.goto(BASE+'/login');await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');await Promise.all([p.waitForNavigation().catch(()=>{}),p.click('button[type="submit"]')]);
await p.goto(BASE+'/catalogue',{waitUntil:'networkidle'});await p.evaluate(()=>{document.documentElement.dataset.nxSkin='neo'});await p.waitForTimeout(300);
console.log(await p.evaluate(()=>{const W=innerWidth;const e=[...document.querySelectorAll('main *, .ns-app *')].find(e=>{const r=e.getBoundingClientRect();return r.width>0&&(r.right>W+2)&&e.tagName==='BUTTON'});return e?e.outerHTML.slice(0,300)+' | parent: '+e.parentElement.className:'none'}));
await p.screenshot({path:'out/cat-neo-430.png'});await b.close();})();

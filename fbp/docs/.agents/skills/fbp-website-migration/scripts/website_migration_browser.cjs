// Optional public DOM capture. All browser GETs go through the Python scope/budget/robots gate.
const {chromium}=require('playwright');
const http=require('http');
let input='';process.stdin.setEncoding('utf8');process.stdin.on('data',s=>input+=s);
process.stdin.on('end',async()=>{
 const config=JSON.parse(input);
 const relay=(payload)=>new Promise((resolve,reject)=>{
  const data=JSON.stringify(payload);
  const request=http.request(config.bridge,{method:'POST',headers:{Authorization:'Bearer '+config.token,'Content-Type':'application/json','Content-Length':Buffer.byteLength(data)}},r=>{
   let out='';r.setEncoding('utf8');r.on('data',s=>out+=s);r.on('end',()=>{try{if(r.statusCode!==200)throw Error('Scoped transport rejected request');resolve(JSON.parse(out));}catch(e){reject(e);}});
  });request.on('error',reject);request.end(data);
 });
 const browser=await chromium.launch({headless:true,executablePath:config.executable,args:['--no-sandbox']});
 try{
  const context=await browser.newContext({serviceWorkers:'block',acceptDownloads:false});
  if(typeof context.routeWebSocket!=='function')throw Error('Playwright with routeWebSocket is required');
  await context.routeWebSocket('**/*',socket=>socket.close());
  const failures=[];
  await context.route('**/*',async route=>{
   const request=route.request();
   if(!['GET','HEAD'].includes(request.method())){failures.push({type:'method',url:request.url().split('?')[0]});await route.abort();return;}
   try{
    const response=await relay({url:request.url(),document:request.isNavigationRequest()});
    await route.fulfill({status:response.status,headers:response.headers,body:Buffer.from(response.body,'base64')});
   }catch(e){failures.push({type:'scope-or-fetch',url:request.url().split('?')[0]});await route.abort();}
  });
  const page=await context.newPage();
  const response=await page.goto(config.url,{waitUntil:'networkidle',timeout:config.timeout});
  // No clicks, logins, form submission or download acceptance.
  process.stdout.write(JSON.stringify({url:page.url(),status:response.status(),html:await page.content(),failures}));
  await context.close();
 }catch(e){process.stderr.write('Public browser rendering failed\n');process.exitCode=1;}
 finally{await browser.close();}
});

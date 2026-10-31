// Test runtime only: creates fictional rows and removes only its own rows in finally.
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=process.env.SAMPLE_TEST_ROOT,url=process.env.APP_TEST_URL,out=process.env.SAMPLE_TEST_OUTPUT_DIR;
assert.ok(root&&url&&out,'Explicit test runtime, URL and output directory required');
const cli=(cmd,data={})=>{const r=JSON.parse(execFileSync('php',['fbp/cli.php',cmd,'--json='+JSON.stringify(data)],{cwd:root,encoding:'utf8'}));assert.notEqual(r.ok,false);return r};
const rows=t=>cli('data_list',{table:t,max:10000}).items;
const marker='突合検証'+require('node:crypto').randomUUID(),left='sample_recognition_items',right='sample_receipt_items',links='sample_reconciliation_links',events='sample_reconciliation_events';
const owned={left:[],right:[]};
const notes=Object.fromEntries(cli('db_tables_list').items.filter(x=>[left,right,links,events].includes(x.tb_name)).map(x=>[x.tb_name,x.id]));assert.equal(Object.keys(notes).length,4,'Install definitions first');
const settle=page=>page.waitForFunction(()=>!window.jQuery||jQuery.active===0);
const wait=async test=>{for(let i=0;i<40;i++){if(test())return;await new Promise(r=>setTimeout(r,100))}throw Error('Expected persisted state missing')};
const ajax=(page,data)=>page.evaluate(({data,endpoint})=>{const f=new FormData();for(const [k,v]of Object.entries(data))f.append(k,String(v));return window.appcon(endpoint,f)},{data,endpoint:url});
(async()=>{
 fs.mkdirSync(out,{recursive:true});
 const browser=await chromium.launch({headless:true,executablePath:process.env.SAMPLE_BROWSER_PATH||'/usr/bin/google-chrome',args:['--no-sandbox']});
 const opts={viewport:{width:1440,height:1000}};
 if(process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME)opts.httpCredentials={username:process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME,password:process.env.PLAYWRIGHT_TEST_BASIC_AUTH_PASSWORD,origin:new URL(url).origin};
 try{
 const context=await browser.newContext(opts),page=await context.newPage();page.setDefaultTimeout(12000);
 const errors=[];page.on('pageerror',e=>errors.push(e.message));

 const add=(side,amount,label)=>{const t=side==='left'?left:right;const id=cli('data_add',{table:t,data:{scope_key:marker,currency:'JPY',occurred_on:Math.floor(Date.now()/1000),counterparty:marker,description:label,amount,eligibility:0}}).id;owned[side].push(id);return id};
 const l1=add('left',72000,'L1'),l2=add('left',10000,'L2');let r1,r2;
 await page.goto(url+(url.includes('?')?'&':'?')+'class=login&function=page');
 await page.locator('input[name=login_id]').waitFor();
 {
  await page.fill('input[name=login_id]',process.env.APP_LOGIN_ID);await page.fill('input[name=password]',process.env.APP_PASSWORD);
  await Promise.all([page.waitForURL(/class=base|base\*page/),page.locator('#login_form button,button[type=submit]').first().click()]);
 }
 await page.waitForLoadState('load');
 await page.locator('input[name=login_id]').waitFor({state:'hidden'});
 await page.waitForFunction(()=>typeof window.appcon==='function'&&document.getElementById('new_windowID')&&window.Cookies&&sessionStorage.getItem('windowID')===Cookies.get('windowID')&&document.getElementById('new_windowID').innerHTML==='');
 await page.waitForTimeout(300);


 // Receipt records originate from an actual uploaded bank statement CSV.
 const bankCsv=Buffer.from('入出金日,摘要,入金額,出金額,残高,取引ID\n2026/11/01,'+marker+' R1,40000,0,40000,id-1\n2026/11/02,'+marker+' R2,32000,0,72000,id-2\n2026/11/03,架空出金,0,1000,71000,id-3\n2026/11/04,利息,100,0,71100,id-4\n');
 async function importCsv(repeat=false){
  await settle(page);
  await ajax(page,{class:'db_exe',function:'page',db_id:notes[right]});
  await page.getByRole('button',{name:'通帳CSV取込',exact:true}).click();
  const upload=page.locator('#sample_statement_csv_form');await upload.waitFor();
  await upload.locator('input[name=scope_key]').fill(marker);await upload.locator('input[name=account_key]').fill('sample-main');
  await upload.locator('input[type=file][name=file]').setInputFiles({name:'fictional-statement.csv',mimeType:'text/csv',buffer:bankCsv});
  await upload.getByRole('button',{name:'取込内容を確認',exact:true}).click();
  const preview=page.locator('#sample_statement_csv_commit');await preview.waitFor();
  if(repeat)await preview.getByText(/新規 0 件 ／ 取込済み 4 件/).waitFor();
  await preview.getByRole('button',{name:'取り込む',exact:true}).click();await upload.waitFor({state:'hidden'});await settle(page);
 }
 await importCsv();await wait(()=>rows(right).filter(x=>x.scope_key===marker).length===4);
 const imported=rows(right).filter(x=>x.scope_key===marker);owned.right.push(...imported.map(x=>x.id));
 r1=imported.find(x=>x.description===marker+' R1').id;r2=imported.find(x=>x.description===marker+' R2').id;
 assert.equal(imported.filter(x=>Number(x.eligibility)===2).length,2,'withdrawal and interest excluded');
 await importCsv(true);assert.equal(rows(right).filter(x=>x.scope_key===marker).length,4,'repeat import duplicated rows');
 const form=page.locator('#sample_reconciliation_form');
 async function open(side,id,label='突合'){
  await settle(page);
  if(await form.isVisible()){await page.locator('.multi_dialog:has(#sample_reconciliation_form) .multi_dialog_close').click();await form.waitFor({state:'hidden'});}
  await settle(page);
  await ajax(page,{class:'db_exe',function:'page',db_id:notes[side==='left'?left:right]});
  await settle(page);
  const row=page.locator('tr.db_exe_selectable_row[data-row-id="'+id+'"]');await row.waitFor();
  await row.getByRole('button',{name:label,exact:true}).click();await form.waitFor();
 }
 await open('left',l1);
 await form.getByRole('button',{name:'選択分を追加',exact:true}).click();await form.locator('.error_token').filter({hasText:'選択'}).waitFor();
 await form.locator('input[value="'+r1+'"][name="target_ids[]"]').check();
 await form.locator('.recon-preview').filter({hasText:'-32,000'}).waitFor();
 await form.locator('button[data-target_id="'+r1+'"]').click();await form.waitFor({state:'hidden'});
 await wait(()=>rows(left).find(x=>x.id===l1)?.group_difference===-32000);
 await open('right',r2);await form.locator('input[value="'+l1+'"][name="target_ids[]"]').check();
 await form.locator('.recon-preview').filter({hasText:/差額 0 円/}).waitFor();
 await form.getByRole('button',{name:'選択分を追加',exact:true}).click();await form.waitFor({state:'hidden'});
 await wait(()=>rows(right).find(x=>x.id===r1)?.group_difference===0);
 await open('right',r1);await form.locator('button[data-target_id="'+l2+'"]').click();await form.waitFor({state:'hidden'});
 await wait(()=>rows(left).find(x=>x.id===l2)?.group_recognition===82000);
 await open('left',l1,'グループ詳細');
 await form.getByText(/グループ全体：計上 82,000 円/).waitFor();
 await page.locator('.fr_notification:visible').waitFor({state:'hidden'}).catch(()=>{});
 await page.screenshot({path:path.join(out,'reconciliation-desktop.png'),fullPage:true});
 // Close detail using the framework control, never add custom close buttons to the sample.
 await page.locator('.multi_dialog:has(#sample_reconciliation_form) .multi_dialog_close').click();await form.waitFor({state:'hidden'});
 await open('left',l1,'突合解除');
 const edge=rows(links).find(x=>x.recognition_id===l1&&x.receipt_id===r1&&Number(x.active)===1);assert.ok(edge);
 await form.locator('button[data-link_id="'+edge.id+'"]').click();
 await wait(()=>rows(links).find(x=>x.id===edge.id)?.active==0);
 await form.waitFor();await form.locator('button[data-link_id]').waitFor();
 await wait(()=>rows(left).find(x=>x.id===l1)?.group_difference===-40000&&rows(left).find(x=>x.id===l2)?.group_difference===30000);
 // Remove from the opposite side too, leaving L2->R1 intact.
 await open('right',r2,'突合解除');const edge2=rows(links).find(x=>x.recognition_id===l1&&x.receipt_id===r2&&Number(x.active)===1);
 await form.locator('button[data-link_id="'+edge2.id+'"]').click();await wait(()=>rows(left).find(x=>x.id===l1)?.link_count===0);
 await open('left',l1);await form.locator('input[value="'+r1+'"][name="target_ids[]"]').check();await form.locator('input[value="'+r2+'"][name="target_ids[]"]').check();
 await form.getByRole('button',{name:'選択分を追加',exact:true}).click();await form.waitFor({state:'hidden'});
 assert.equal(rows(links).filter(x=>owned.left.includes(x.recognition_id)&&Number(x.active)===1).length,3);
 // Standard edit must update the cache seen from BOTH lists.
 await ajax(page,{class:'db_exe',function:'page',db_id:notes[left]});
 const row=page.locator('tr.db_exe_selectable_row[data-row-id="'+l2+'"]');await row.locator('[data-function="edit"]').click();
 const edit=page.locator('.multi_dialog:visible').filter({has:page.locator('input[name=amount]')});
 await edit.locator('input[name=amount]').waitFor();await edit.locator('input[name=amount]').fill('15000');
 await edit.locator('[data-function="edit_exe"]').first().click();
 await wait(()=>rows(right).find(x=>x.id===r2)?.group_recognition===87000);
 await page.setViewportSize({width:390,height:844});await open('left',l1);await page.waitForTimeout(300);
 const bounds=await form.boundingBox();assert.ok(bounds&&bounds.width<405,'Dialog overflow '+JSON.stringify(bounds));
 assert.equal(errors.length,0,errors.join('\n'));
 console.log(JSON.stringify({ok:true,mismatchSaved:true,bothSides:true,multiAdd:true,bothSideUnlink:true,splitPreservesOthers:true,standardEditRefresh:true,desktopAndMobile:true,csvUploadAndRepeat:true,withdrawalAndInterestExcluded:true,marker}));
 }finally{
  await browser.close();
  for(const e of rows(events))if(owned.left.includes(e.recognition_id)&&owned.right.includes(e.receipt_id))cli('data_delete',{table:events,id:e.id});
  for(const l of rows(links))if(owned.left.includes(l.recognition_id)&&owned.right.includes(l.receipt_id))cli('data_delete',{table:links,id:l.id});
  for(const row of rows(right))if(row.scope_key===marker&&!owned.right.includes(row.id))owned.right.push(row.id);
  for(const [side,table]of [['left',left],['right',right]])for(const id of owned[side])cli('data_delete',{table,id});
 }
})().catch(e=>{console.error(e.stack||e.message);process.exit(1)});

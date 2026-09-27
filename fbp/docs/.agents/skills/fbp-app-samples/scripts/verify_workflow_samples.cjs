// Integration verifier: installs no schema; creates and removes only its own test records.
// Install all three workflow samples in an explicitly resolved TEST runtime first.
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const root=process.env.SAMPLE_TEST_ROOT,out=process.env.SAMPLE_TEST_OUTPUT_DIR,url=process.env.APP_TEST_URL;
assert.ok(root&&out&&url,'Explicit test root, URL and output directory required');
const cli=(command,data={})=>JSON.parse(execFileSync('php',['fbp/cli.php',command,'--json='+JSON.stringify(data)],{cwd:root,encoding:'utf8'}));
const rows=table=>cli('data_list',{table,max:10000}).items;
const marker='検証'+require('node:crypto').randomUUID();
const parentIds=new Set();
const settle=page=>page.waitForFunction(()=>!window.jQuery||jQuery(':animated').length===0);
const notes=Object.fromEntries(cli('db_tables_list').items.filter(x=>['sample_contacts','sample_contact_history','sample_cases','sample_intakes'].includes(x.tb_name)).map(x=>[x.tb_name,x.id]));
assert.equal(Object.keys(notes).length,4,'Install the three sample definitions first');
const ajax=async(page,data)=>page.evaluate(data=>{const f=new FormData();Object.entries(data).forEach(([k,v])=>f.append(k,String(v)));window.appcon('app.php',f)},data);
const waitRows=async(table,predicate)=>{for(let i=0;i<30;i++){let r=rows(table);if(predicate(r))return r;await new Promise(r=>setTimeout(r,150))}throw Error('Saved result missing: '+table)};
(async()=>{
 fs.mkdirSync(out,{recursive:true});
 const browser=await chromium.launch({headless:true,executablePath:process.env.SAMPLE_BROWSER_PATH||'/usr/bin/google-chrome',args:['--no-sandbox']});
 const options={viewport:{width:1440,height:1000}};
 if(process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME)options.httpCredentials={username:process.env.PLAYWRIGHT_TEST_BASIC_AUTH_USERNAME,password:process.env.PLAYWRIGHT_TEST_BASIC_AUTH_PASSWORD,origin:new URL(url).origin};
 let page,publicPage;
 try{
 const context=await browser.newContext(options);page=await context.newPage();page.setDefaultTimeout(8000);
 page.on('pageerror',e=>console.log('PAGEERROR',e.message));
 await page.goto(url,{waitUntil:'load',timeout:30000});
 if(await page.locator('input[name=login_id]').count()){
 await page.fill('input[name=login_id]',process.env.APP_LOGIN_ID);await page.fill('input[name=password]',process.env.APP_PASSWORD);
 await Promise.all([page.waitForURL(/class=base|base\*page/,{timeout:20000}),page.locator('#login_form button,button[type=submit]').first().click()]);
 }
 await page.waitForLoadState('load');await page.waitForFunction(()=>typeof appcon==='function');
 // Parent and child relation using the actual side panel.
 const parent=cli('data_add',{table:'sample_contacts',data:{name:marker,contact_name:'架空 担当',email:'sample@example.invalid'}}).id;parentIds.add(Number(parent));
 await ajax(page,{class:'db_exe',function:'page',db_id:notes.sample_contacts});
 const side=page.locator('[data-function="rows_child"][data-db_id="'+notes.sample_contact_history+'"][data-parent_id="'+parent+'"]');await side.first().click();
 await page.locator('#work_area_second [data-function="add_child"]').click();
 await page.locator('[data-function="add_child_exe"]').waitFor();const childForm=page.locator('form').filter({has:page.locator('[data-function="add_child_exe"]')});await page.locator('[data-function="add_child_exe"]').click();await page.waitForFunction(()=>[...document.querySelectorAll('.error_subject')].some(x=>x.textContent.trim()));await childForm.locator('input[name=subject]').fill(marker+'履歴');
 await page.locator('input[name=contacted_on]').click();await page.locator('.fbp-original-datepicker-day').filter({hasText:/^15$/}).click();
 await page.locator('input[name=staff]').fill('架空 担当');await page.locator('textarea[name=detail]').fill('相談内容の確認');
 await page.locator('[data-function="add_child_exe"]').click();
 await waitRows('sample_contact_history',r=>r.some(x=>x.subject===marker+'履歴'&&Number(x.parent_id)===Number(parent)));
 await page.locator('#work_area_second').getByText(marker+'履歴',{exact:true}).waitFor();
 await settle(page);await page.screenshot({path:path.join(out,'parent-child-desktop.png'),fullPage:true});
 await page.locator('#work_area_second [data-function="edit_child"]').first().click();
 await page.locator('[data-function="edit_child_exe"]').waitFor();await page.locator('textarea[name=detail]').fill('編集後の相談内容');await page.locator('[data-function="edit_child_exe"]').click();
 await waitRows('sample_contact_history',r=>r.some(x=>x.subject===marker+'履歴'&&x.detail==='編集後の相談内容'));
 await page.locator('[data-function="edit_child_exe"]').waitFor({state:'detached'});
 await page.setViewportSize({width:390,height:844});await settle(page);await page.screenshot({path:path.join(out,'parent-child-mobile.png'),fullPage:true});await page.setViewportSize({width:1440,height:1000});
 console.log('parent-child add/edit and parent association passed');
 await page.locator('#work_area_second .work_area_second_action_button').filter({hasText:/^close$/}).click();
 // Create through Standard Screen; transition controls must not be normal edit fields.
 await ajax(page,{class:'db_exe',function:'page',db_id:notes.sample_cases});
 await page.locator('[data-function="add"][data-db_id="'+notes.sample_cases+'"]').first().click();
 await page.locator('[data-function="add_exe"]').waitFor();const caseForm=page.locator('form').filter({has:page.locator('[data-function="add_exe"]')});assert.equal(await page.locator('[id="'+await caseForm.getAttribute('id')+'"]').count(),1,'Search and dialog form IDs collide');await page.locator('[data-function="add_exe"]').click();await page.waitForFunction(()=>[...document.querySelectorAll('.error_title')].some(x=>x.textContent.trim()));await caseForm.locator('input[name=title]').fill(marker+'案件');await page.locator('textarea[name=detail]').fill('状態変更の検証');
 await page.locator('[data-function="add_exe"]').click();
 await waitRows('sample_cases',r=>r.some(x=>x.title===marker+'案件'&&String(x.status)==='0'));
 for(const [reason,state] of [['対応開始','1'],['対応完了','2']]){
 await page.locator('tr').filter({hasText:marker+'案件'}).locator('[data-class="sample_case_transition"]').first().click();
 const note=page.locator('textarea[name=note]');await note.waitFor();
 if(state==='1'){await page.locator('[data-class="sample_case_transition"][data-function="save"]').click();await page.locator('.error_note').filter({hasText:'入力してください'}).waitFor()}
 await note.fill(reason);await page.locator('[data-class="sample_case_transition"][data-function="save"]').click();
 await waitRows('sample_cases',r=>r.some(x=>x.title===marker+'案件'&&String(x.status)===state));
 await note.waitFor({state:'detached'});
 }
 await page.getByText('対応完了',{exact:false}).first().waitFor();await page.locator('.fr_notification').filter({hasText:'状態を変更しました。'}).last().waitFor({state:'hidden'});await page.screenshot({path:path.join(out,'status-desktop.png'),fullPage:true});
 console.log('Standard Screen creation and 0->1->2 with required reason passed');
 // A separate anonymous app session shares only gateway Basic authentication.
 const anon=await browser.newContext(options);publicPage=await anon.newPage();publicPage.setDefaultTimeout(8000);
 const publicUrl=process.env.SAMPLE_PUBLIC_URL;assert.ok(publicUrl,'Pass the sample_public_intake page URL generated by get_APP_URL');
 await publicPage.goto(publicUrl,{waitUntil:'load',timeout:30000});
 await publicPage.locator('[data-function="confirm"]').click();await publicPage.locator('.error_name').filter({hasText:'入力してください'}).waitFor();
 await publicPage.locator('input[name=name]').fill(marker+'受付');await publicPage.locator('input[name=email]').fill('sample@example.invalid');await publicPage.locator('textarea[name=message]').fill('架空のご相談です。\n<script>window.sampleInjected=true</script>');
 await publicPage.screenshot({path:path.join(out,'public-desktop.png'),fullPage:true});
 await publicPage.locator('[data-function="confirm"]').click();await publicPage.locator('[data-function="save"]').waitFor();
 assert.equal(rows('sample_intakes').filter(x=>x.name===marker+'受付').length,0);
 assert.equal(await publicPage.evaluate(()=>!!window.sampleInjected),false);
 await settle(publicPage);await publicPage.screenshot({path:path.join(out,'public-confirm-desktop.png'),fullPage:true});
 const send=publicPage.waitForRequest(r=>r.method()==='POST'&&(r.postData()||'').includes('sample_public_intake')&&(r.postData()||'').includes('save'));await publicPage.locator('[data-function="save"]').click();const sent=await send;
 await publicPage.getByRole('heading',{name:'受付が完了しました'}).waitFor({timeout:15000});
 assert.equal(rows('sample_intakes').filter(x=>x.name===marker+'受付').length,1);const headers=Object.fromEntries(Object.entries(sent.headers()).filter(([k])=>!['cookie','authorization','content-length'].includes(k)));await anon.request.post(sent.url(),{headers,data:sent.postDataBuffer()});assert.equal(rows('sample_intakes').filter(x=>x.name===marker+'受付').length,1,'Repeated send duplicated intake');
 await ajax(page,{class:'db_exe',function:'page',db_id:notes.sample_intakes});await page.getByText(marker+'受付',{exact:true}).waitFor();
 await page.screenshot({path:path.join(out,'intakes-desktop.png'),fullPage:true});
 await page.locator('tr').filter({hasText:marker+'受付'}).locator('[data-function="edit"][data-class="db_exe"]').first().click();await page.locator('[data-function="edit_exe"]').first().waitFor();
 await page.locator('select[name=status]').last().selectOption('2',{force:true});await page.locator('[data-function="edit_exe"]').first().click();await waitRows('sample_intakes',r=>r.some(x=>x.name===marker+'受付'&&String(x.status)==='2'));
 await page.locator('[data-function="edit_exe"]').first().waitFor({state:'detached'});
 for(const p of [page,publicPage])await p.setViewportSize({width:390,height:844});
 await publicPage.goto(publicUrl,{waitUntil:'load'});await publicPage.locator('input[name=name]').fill('架空 太郎');await publicPage.locator('input[name=email]').fill('sample@example.invalid');await publicPage.locator('textarea[name=message]').fill('画面幅の確認');
 await publicPage.screenshot({path:path.join(out,'public-mobile.png'),fullPage:true});
 await publicPage.locator('[data-function="confirm"]').click();await publicPage.locator('[data-function="save"]').waitFor();await settle(publicPage);await publicPage.screenshot({path:path.join(out,'public-confirm-mobile.png'),fullPage:true});
 const metrics=await publicPage.evaluate(()=>({viewport:innerWidth,scroll:document.documentElement.scrollWidth}));assert.ok(metrics.scroll<=metrics.viewport,'Public page overflows mobile');
 await page.screenshot({path:path.join(out,'intakes-mobile.png'),fullPage:true});
 const result={ok:true,checks:['parent-child-add-edit','status-and-required-reason','anonymous-intake','escaped-confirmation','repeat-send','admin-reception-status-edit','desktop-mobile'],metrics};fs.writeFileSync(path.join(out,'result.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
 }catch(e){
 if(page)await page.screenshot({path:path.join(out,'failure-admin.png'),fullPage:true});
 if(publicPage)await publicPage.screenshot({path:path.join(out,'failure-public.png'),fullPage:true});
 console.error('Check failed; screenshots saved in the explicit output directory.');
 throw e;
 }finally{
 await browser.close();
 for(const [table,key] of [['sample_contact_history','subject'],['sample_cases','title'],['sample_intakes','name'],['sample_contacts','name']])for(const row of rows(table))if(String(row[key]).startsWith(marker)||(table==='sample_contact_history'&&parentIds.has(Number(row.parent_id))))cli('data_delete',{table,id:row.id});
 }
})().catch(e=>{console.error(e.stack);process.exitCode=1});

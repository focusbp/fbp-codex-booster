<?php
// Isolated provider/storage doubles. Never calls Square/OpenAI or modifies app data.
$web=$argv[1]??'';$tmp=$argv[2]??'';
if(!is_dir($web.'/fbp')||!is_dir($tmp))throw new RuntimeException('Provide web test root and existing isolated temp directory');
class FakeDb {
    public array $rows=[];public string $kind;public Controller $ctl;
    function __construct(Controller $ctl,string $kind){$this->ctl=$ctl;$this->kind=$kind;}
    function select(...$args){return $this->kind==='settings'?[$this->ctl->cfg]:[];}
    function getall(...$args){return $this->rows;}
    function filter($key,$value,...$args){return array_values(array_filter($this->rows,fn($r)=>($r[$key]??null)===$value));}
    function get($id){return $this->rows[$id]??[];}
    function insert(&$row){$id=count($this->rows)+1;$row['id']=$id;$this->rows[$id]=$row;return $id;}
    function update($row){$this->rows[$row['id']]=array_replace($this->rows[$row['id']]??[],$row);return true;}
    function delete($id){unset($this->rows[$id]);return true;}
    function set_strict_field_length($v){}
}
class Controller {
    public array $cfg=[],$session=[],$post=[],$errors=[],$assigned=[],$dbs=[];public object $dirs;public int $charges=0;public bool $fail=false,$mismatch=false,$sandbox=true;public string $ai='{}';
    function __construct(string $tmp){$this->dirs=(object)['datadir'=>$tmp];}
    function get_setting(){return ['force_testmode'=>$this->sandbox?1:0,'square_application_id'=>'sandbox-mock','square_access_token'=>'mock','square_location_id'=>'mock','chatgpt_api_key'=>'mock','chatgpt_api_url'=>'mock','chatgpt_api_model'=>'mock'];}
    function get_session($k){if($k==='testserver')return $this->sandbox;return $this->session[$k]??null;}
    function set_session($k,$v){$this->session[$k]=$v;}
    function set_square(...$v){}
    function square_regist_customer(...$v){return 'customer';}function square_regist_card(...$v){return 'card';}
    function square_payment(...$v){++$this->charges;return !$this->fail;}
    function square_get_payment_result(){return ['id'=>'payment','status'=>'COMPLETED','amount_money'=>['amount'=>$this->mismatch?1:2400,'currency'=>'JPY']];}
    function is_app_admin(){return true;}function get_db_setting($table){return ['menu_name'=>'OCR mock','screen_build_type'=>0,'list_type'=>4,'post_action_class'=>''];}
    function POST($k=null){return $k===null?$this->post:($this->post[$k]??null);}
    function db($table,$class=null){return $this->dbs[$table]??=new FakeDb($this,$table);}
    function assign($k,$v){$this->assigned[$k]=$v;}function show_multi_dialog(...$v){}function invoke(...$v){}
    function show_notification_text($v){$this->assigned['notification']=$v;}
    function res_error_message($k,$v){$this->errors[$k]=$v;}
    function chatGPT_image_analysis(...$v){return $this->ai;}
    function close_multi_dialog(...$v){}function reload_work_area(){}function count_res_error_message(){return count($this->errors);}
}
require $web.'/classes/app/feature_sample_payment/feature_sample_payment.php';
require $web.'/classes/app/feature_excel_ocr/feature_excel_ocr.php';
$target=(new ReflectionClass('feature_excel_ocr'))->getConstant('TABLE');
$checks=0;
function check($v,$message){global $checks;if(!$v)throw new RuntimeException($message);++$checks;}
function rejects(callable $f){try{$f();}catch(Throwable $e){return true;}return false;}
$order=['id'=>1,'total'=>2400,'payment_state'=>'pending','name'=>'sample','email'=>'sample@example.invalid','address'=>'sample','request_key'=>'mock-request'];
$c=new Controller($tmp);$db=new FakeDb($c,'orders');$db->rows[1]=$order;
$r=feature_sample_payment::pay($c,$db,1);check($r['payment_state']==='paid'&&$c->charges===1,'sandbox success');
feature_sample_payment::pay($c,$db,1);check($c->charges===1,'paid callback does not charge twice');
$c->sandbox=false;check(rejects(fn()=>feature_sample_payment::pay($c,$db,1)),'production refused');
$c->sandbox=true;$c->fail=true;$db->rows[1]=$order;
check(rejects(fn()=>feature_sample_payment::pay($c,$db,1))&&$db->rows[1]['payment_state']==='unknown','failure marked unknown');
$count=$c->charges;check(rejects(fn()=>feature_sample_payment::pay($c,$db,1))&&$c->charges===$count,'unknown never retries');
$c->fail=false;$c->mismatch=true;$db->rows[1]=$order;check(rejects(fn()=>feature_sample_payment::pay($c,$db,1))&&$db->rows[1]['payment_state']==='unknown','amount mismatch refused');
@mkdir($tmp.'/excel_grid');file_put_contents($tmp.'/excel_grid/settings.dat','isolated marker');
$c=new Controller($tmp);$c->cfg=['revision'=>1,'columns'=>['a'=>['title'=>'会社名','type'=>'text','mode'=>'input','generated'=>true],'b'=>['title'=>'自動計算','type'=>'number','mode'=>'calculation','generated'=>true,'formula'=>'1']]];
check(array_keys(feature_excel_ocr::inputFields($c->cfg))===['a'],'computed fields excluded');
check(str_contains(feature_excel_ocr::prompt($c->cfg),'会社名')&&!str_contains(feature_excel_ocr::prompt($c->cfg),'自動計算'),'prompt follows definition');
check(array_keys(feature_excel_ocr::inputFields(['columns'=>['code'=>['title'=>'商品コード','type'=>'text','mode'=>'input','generated'=>true],'name'=>['title'=>'商品名','type'=>'text','mode'=>'input','generated'=>true]]]))===['code','name'],'standard note field names supported');
$old=feature_excel_ocr::fingerprint($c->cfg);$c->cfg['columns']['c']=['title'=>'人数','type'=>'number','mode'=>'input','generated'=>true];$c->cfg['revision']=2;
check(array_keys(feature_excel_ocr::inputFields($c->cfg))===['a','c'],'new column included without code changes');
check(feature_excel_ocr::fingerprint($c->cfg)!==$old,'definition fingerprint changes');
$ocr=new feature_excel_ocr();$ocr->run($c);$p=$c->session['feature_ocr_'.$target];
$png=$tmp.'/mock.png';file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jz1sAAAAASUVORK5CYII='));
define('CLI_APP_CALL',true);$_FILES=['file'=>['error'=>0,'tmp_name'=>$png]];$c->post=['token'=>$p['token']];$c->ai='{"a":"サンプル会社","c":3,"b":999,"evil":"blocked"}';
$ocr->read($c);check(!$c->errors,'mock OCR read succeeds');$p=$c->session['feature_ocr_'.$target];check(array_keys($p['row'])===['a','c'],'unknown/computed model keys discarded');
$ocr->review($c);check(array_keys($c->assigned['fields'])===['a','c'],'review fields generated dynamically');
$c->post=['token'=>$p['token'],'a'=>'修正した会社','c'=>'5','b'=>'999','evil'=>'blocked'];$ocr->save($c);
check(!$c->errors,'OCR save succeeds');$saved=$c->dbs[$target]->get(1);check($saved['a']==='修正した会社'&&$saved['c']==='5'&&!isset($saved['evil'])&&(float)$saved['b']===1.0,'corrected values saved and computed column recalculated');
$ocr->save($c);check(count($c->dbs[$target]->rows)===1,'duplicate save does not add row');
$c->errors=[];$ocr->run($c);$p=$c->session['feature_ocr_'.$target];$p['row']=['a'=>'old','c'=>'2'];$c->session['feature_ocr_'.$target]=$p;$c->post=['token'=>$p['token'],'a'=>'old','c'=>'2'];$c->cfg['revision']=3;$ocr->save($c);
check(isset($c->errors['result'])&&count($c->dbs[$target]->rows)===1,'stale definition rejected');
check(rejects(fn()=>feature_excel_ocr::normalize(ExcelGrid::column('a',['type'=>'date']),'2026-02-30')),'invalid date refused');
check(rejects(fn()=>feature_excel_ocr::normalize(ExcelGrid::column('a',['type'=>'dropdown','options'=>['1'=>'選択肢']]),'unknown')),'unknown option refused');
require $web.'/classes/app/feature_product_schema/feature_product_schema.php';
$c=new Controller($tmp);$c->cfg=['revision'=>1,'columns'=>['a'=>['title'=>'コード','type'=>'text','mode'=>'input','generated'=>true],'b'=>['title'=>'商品名','type'=>'text','mode'=>'input','generated'=>true],'c'=>['title'=>'価格','type'=>'number','mode'=>'input','generated'=>true]],'column_order'=>['b','a','c']];
check(array_keys(feature_product_schema::fields($c))===['b','a','c'],'shared schema follows column moves');
$r=feature_product_schema::product($c,['a'=>'CODE','b'=>'NAME','c'=>'1200']);check($r['code']==='CODE'&&$r['price']==='1200','business roles independent of display position');
$c->cfg['columns']['e']=['title'=>'追加項目','type'=>'text','mode'=>'input','generated'=>true];$c->cfg['column_order'][]='e';
check(array_keys(feature_product_schema::fields($c))===['b','a','c','e'],'shared schema includes newly defined columns');
$c->cfg['columns']['f']=['title'=>'計算','type'=>'number','mode'=>'calculation','generated'=>true];$c->cfg['column_order'][]='f';
check(isset(feature_product_schema::fields($c)['f'])&&!isset(feature_product_schema::fields($c,true)['f']),'computed columns export only');
// Batch extraction validates shape, count and field allowlist before any writes.
check(str_contains(feature_excel_ocr::prompt($c->cfg,true),'配列'),'multi prompt requests array');
$batch=feature_excel_ocr::extractedRows($c->cfg,[['a'=>'A','c'=>1,'evil'=>'blocked'],['a'=>'B','c'=>2]],true);check(count($batch)===2&&!isset($batch[0]['evil']),'batch allowlist and two rows');
check(rejects(fn()=>feature_excel_ocr::extractedRows($c->cfg,[],true)),'empty batch rejected');
check(rejects(fn()=>feature_excel_ocr::extractedRows($c->cfg,array_fill(0,21,['a'=>'A']),true)),'oversize batch rejected without truncation');
$c=new Controller($tmp);$c->cfg=['revision'=>10,'columns'=>['a'=>['title'=>'会社名','type'=>'text','mode'=>'input','generated'=>true],'c'=>['title'=>'人数','type'=>'number','mode'=>'input','generated'=>true]]];$ocr->run($c);$p=$c->session['feature_ocr_'.$target];$c->post=['token'=>$p['token'],'read_mode'=>'multiple'];$c->ai='[{"a":"A","c":1},{"a":"B","c":2}]';$ocr->read($c);$p=$c->session['feature_ocr_'.$target];check(count($p['rows'])===2&&$p['multiple'],'multi read stores rows');$ocr->review($c);check(count($c->assigned['rows'])===2,'batch review includes all rows');
$c->post=['token'=>$p['token'],'r0_a'=>'修正A','r0_c'=>'3','r1_a'=>'修正B','r1_c'=>'invalid'];$ocr->save($c);check(count($c->dbs[$target]->rows)===0,'all rows validated before first insert');
$c->errors=[];$c->post['r1_c']='4';$ocr->save($c);check(!$c->errors&&count($c->dbs[$target]->rows)===2,'batch save writes two corrected rows');$ocr->save($c);check(count($c->dbs[$target]->rows)===2,'batch duplicate save refused');
require_once $web.'/fbp/interface/McpSubjectInterface.php';require_once $web.'/fbp/interface/McpActionInterface.php';require_once $web.'/fbp/interface/McpFunctionInterface.php';require_once $web.'/classes/app/feature_product_mcp/feature_product_mcp.php';
$c=new Controller($tmp);$c->cfg=['revision'=>11,'columns'=>['a'=>['title'=>'コード','type'=>'text','mode'=>'input','generated'=>true],'b'=>['title'=>'名前','type'=>'text','mode'=>'input','generated'=>true]]];$c->db('user')->rows[1]=['id'=>1,'status'=>0,'user_type'=>0];$subject=new McpSubject('fbp_user',1);$request=fn($args,$who=null)=>new McpFunctionRequest([],$args,$who);
check(rejects(fn()=>feature_product_mcp::execute($c,$request(['values'=>['a'=>'X'],'confirm'=>true]),'add')),'MCP unauthenticated mutation refused');
check(rejects(fn()=>feature_product_mcp::execute($c,$request(['values'=>['a'=>'X']],$subject),'add')),'MCP mutation requires confirmation');
feature_product_mcp::execute($c,$request(['values'=>['a'=>'X','b'=>'初期'],'confirm'=>true],$subject),'add');check($c->db(feature_product_schema::TABLE)->get(1)['b']==='初期','MCP add');
feature_product_mcp::execute($c,$request(['id'=>1,'values'=>['b'=>'変更'],'confirm'=>true],$subject),'update');check($c->db(feature_product_schema::TABLE)->get(1)['b']==='変更','MCP update preserves other fields');
check(rejects(fn()=>feature_product_mcp::execute($c,$request(['id'=>1,'values'=>['evil'=>'bad'],'confirm'=>true],$subject),'update')),'MCP unknown field refused');
feature_product_mcp::execute($c,$request(['id'=>1,'confirm'=>true],$subject),'delete');check(!$c->db(feature_product_schema::TABLE)->get(1),'MCP delete');
$c=new Controller($tmp);$c->cfg=['revision'=>12,'columns'=>['a'=>['title'=>'コード','type'=>'text','mode'=>'input','generated'=>true],'b'=>['title'=>'名前','type'=>'text','mode'=>'input','generated'=>true]]];$c->db('settings','excel_grid');$c->db('states','excel_grid');$c->db(feature_product_schema::TABLE);$key=hash('sha256','submission-test');$first=feature_product_schema::saveSubmission($c,$key,['a'=>'CHECK','b'=>'入力']);$again=feature_product_schema::saveSubmission($c,$key,['a'=>'CHECK','b'=>'入力']);check($first['id']===$again['id']&&count($c->db(feature_product_schema::TABLE)->rows)===1,'submission repeated save keeps same note row');check($c->db(feature_product_schema::TABLE)->get($first['id'])['b']==='入力','submission uses physical note fields');
echo json_encode(['ok'=>true,'checks'=>$checks,'external_api_calls'=>0],JSON_UNESCAPED_UNICODE)."\n";

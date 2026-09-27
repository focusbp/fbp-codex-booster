<?php
// Isolated behavioral checks: no FBP runtime, network, production data or notifications.
if(PHP_SAPI!=='cli')exit;
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
interface CodegenActionInterface {public function run(Controller $ctl);}
class SampleMemoryDb {
    public array $rows=[];
    function get($id){return $this->rows[$id]??[];}
    function insert(&$row){$id=count($this->rows)+1;$row['id']=$id;$this->rows[$id]=$row;return $id;}
    function update(&$row){$this->rows[$row['id']]=array_replace($this->rows[$row['id']],$row);}
    function select($key,$value,...$args){return array_values(array_filter($this->rows,fn($r)=>($r[$key]??null)===$value));}
}
class Controller {
    public array $post=[],$session=['login'=>true],$assigned=[],$errors=[],$tables=[],$events=[];public bool $admin=true;
    function POST($key=null){return $key===null?$this->post:($this->post[$key]??null);}
    function get_session($key){return $this->session[$key]??null;}
    function set_session($key,$value){$this->session[$key]=$value;}
    function is_app_admin(){return $this->admin;}
    function get_constant_array(...$args){return [0=>'未対応',1=>'対応中',2=>'完了'];}
    function get_login_user_id(){return 42;}
    function decrypt($v){return str_starts_with($v,'enc:')?substr($v,4):'';}
    function decrypt_post($key){return $this->decrypt($this->post[$key]??'');}
    function db($table){return $this->tables[$table]??=new SampleMemoryDb();}
    function assign($key,$value){$this->assigned[$key]=$value;}
    function res_error_message($key,$value){$this->errors[$key]=$value;}
    function set_check_login($flag){}
    function show_notification_text($text){$this->events[]=['notice',$text];}
    function show_multi_dialog(...$args){$this->events[]=['dialog',$args];}
    function close_multi_dialog(...$args){$this->events[]=['close',$args];}
    function show_public_pages(...$args){$this->events[]=['public',$args];}
    function reload_work_area(){$this->events[]=['reload'];}
    function res_redirect($url){$this->events[]=['redirect',$url];}
    function get_APP_URL($class,$function){return '/'.$class.'*'.$function;}
}
$assets=dirname(__DIR__).'/assets';
foreach(['parent-child-history','status-transition','public-intake'] as $sample){
    $manifest=json_decode(file_get_contents($assets.'/'.$sample.'/'.$sample.'.json'),true,512,JSON_THROW_ON_ERROR);
    foreach($manifest['files'] as $file)if(str_ends_with($file,'.php'))require_once $assets.'/'.$sample.'/'.$file;
    check(!isset($manifest['seed']),'Installer manifest must not include seed data');
}
$c=new Controller();$db=$c->db('sample_cases');$row=['title'=>'サンプル案件','status'=>'0','version'=>0,'change_history'=>''];$db->insert($row);
$transition=new sample_case_transition();$c->post=['id'=>'enc:1'];$c->admin=false;$transition->run($c);check(!isset($c->assigned['token']),'Non-admin opened transition');
$c->admin=true;$transition->run($c);$token=$c->assigned['token'];
$c->post=['token'=>$token,'note'=>''];$events=count($c->events);$transition->save($c);check(isset($c->errors['note'])&&count($c->events)===$events&&$db->get(1)['status']==='0','Validation redrew or changed state');
$c->post=['token'=>'wrong','note'=>'対応開始'];$transition->save($c);check($db->get(1)['status']==='0','Invalid grant saved');
$c->post=['token'=>$token,'note'=>'対応開始','status'=>'2'];$transition->save($c);check($db->get(1)['status']==='1'&&$db->get(1)['version']===1,'Transition accepted target tampering');
$saved=$db->get(1);$transition->save($c);check($db->get(1)===$saved,'Repeated save added history');
$c->post=['id'=>'enc:1'];$transition->run($c);$stale=$c->assigned['token'];$fresh=$db->get(1);$fresh['version']++;$db->update($fresh);
$c->post=['token'=>$stale,'note'=>'完了'];$transition->save($c);check($db->get(1)['status']==='1','Stale dialog changed state');
$c->post=['id'=>'enc:1'];$transition->run($c);$c->post=['token'=>$c->assigned['token'],'note'=>'完了'];$transition->save($c);check($db->get(1)['status']==='2'&&substr_count($db->get(1)['change_history'],'担当ID')===2,'History/state mismatch');
$finished=$db->get(1);$c->post=['id'=>'enc:1','_post_action_from'=>'add'];(new sample_cases_post_action())->run($c);check($db->get(1)===$finished,'Forged add hook reset completed state');
$filter=new sample_cases_visibility_filter();$c->post=['title'=>'訂正'];check($filter->can_access($c,'edit_exe'),'Ordinary edit denied');$c->post['status']='0';check(!$filter->can_access($c,'edit_exe')&&!$filter->can_access($c,'duplicate'),'Standard Screen can bypass state rules');
$c=new Controller();$c->session=[];$c->admin=false;$public=new sample_public_intake($c);$public->page($c);$formToken=$c->assigned['token'];
check(!$c->db('sample_intakes')->rows,'Opening intake inserted data');
$c->post=['token'=>$formToken,'name'=>['bad'],'email'=>'bad','message'=>'問い合わせ'];$public->confirm($c);check(isset($c->errors['name'],$c->errors['email']),'Malformed intake accepted');
$c->errors=[];$c->post=['token'=>$formToken,'name'=>str_repeat('😀',100),'email'=>'sample@example.invalid','message'=>'問い合わせ'];$public->confirm($c);check(isset($c->errors['name'])&&!$c->db('sample_intakes')->rows,'UTF-8 storage overflow accepted');
$c->post=['token'=>$formToken,'name'=>'架空 太郎','email'=>'sample@example.invalid','message'=>'<script>alert(1)</script>'];$public->confirm($c);$firstConfirmation=$c->assigned['token'];
$public->confirm($c);$confirm=$c->assigned['token'];check($firstConfirmation!==$confirm,'Reconfirmation retained stale grant');
$c->post=['token'=>$firstConfirmation];$public->save($c);check(!$c->db('sample_intakes')->rows,'Stale confirmation inserted data');
$c->post=['token'=>$confirm,'name'=>'改ざん','status'=>'2'];$public->save($c);$public->save($c);
check(count($c->db('sample_intakes')->rows)===1&&$c->db('sample_intakes')->get(1)['name']==='架空 太郎'&&$c->db('sample_intakes')->get(1)['status']==='0','Duplicate/tampered intake');
$other=new Controller();$other->session=[];$other->tables=$c->tables;$other->post=['token'=>$confirm];(new sample_public_intake($other))->save($other);check(count($c->db('sample_intakes')->rows)===1&&isset($other->errors['token']),'Another session reused grant');
$c->errors=[];$c->post=['token'=>$formToken,'name'=>'再利用','email'=>'sample@example.invalid','message'=>'再利用'];$public->confirm($c);check(isset($c->errors['token']),'Consumed form can reconfirm');
$c->errors=[];$public->page($c);$expired=$c->session['sample_public_intake'];$expired['expires']=time()-1;$c->session['sample_public_intake']=$expired;$c->post=['token'=>$expired['token']];$public->confirm($c);check(isset($c->errors['token']),'Expired form accepted');
$f=new sample_intakes_visibility_filter();check(!$f->can_access($c,'rows'),'Anonymous read allowed');$c->admin=true;$c->session['login']=true;$c->post=['status'=>'2'];check($f->can_access($c,'edit_exe'),'Intake status edit denied');$c->post['request_key']='changed';check(!$f->can_access($c,'edit_exe'),'Intake duplicate key editable');
// Verify install behavior in an explicitly supplied scratch directory.
$scratch=$argv[1]??'';check($scratch!==''&&is_dir($scratch),'Pass an existing empty scratch directory');
$install=__DIR__.'/install_workflow_sample.php';
foreach(['parent-child-history','status-transition','public-intake'] as $sample){
    $root=$scratch.'/'.$sample;check(!file_exists($root),'Scratch target exists');mkdir($root.'/classes/app',0775,true);
    $call=function()use($install,$sample,$root){$p=proc_open([PHP_BINARY,$install,$sample,'--code-root='.$root],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$err];};
    [$exit,$out]=$call();check($exit===0&&json_decode($out,true)['data_inserted']===0,'Code install failed');
    $hashes=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($it as $file)if($file->isFile())$hashes[$file->getPathname()]=hash_file('sha256',$file->getPathname());
    [$exit]=$call();check($exit!==0,'Installer overwrote existing class');foreach($hashes as $file=>$hash)check(hash_file('sha256',$file)===$hash,'Refused install changed files');
    check(!is_dir($root.'/classes/data'),'Code install created runtime data');
}
echo "workflow samples: permission, validation, state/history, stale/repeated requests, public confirmation, installer isolation and overwrite refusal passed\n";

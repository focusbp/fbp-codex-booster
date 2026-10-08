<?php
if(PHP_SAPI!=='cli'||$argc!==3||file_exists($argv[2]))exit(2);
interface Controller {}
class Controller_class implements Controller {
    public bool $testserver=true;
    function get_session($key){return $key==='testserver'?$this->testserver:null;}
    public array $post=[],$errors=[],$pool=[],$response=[];
    static function getInstance(){return null;}
    function __construct(public string $root,public string $fbp){}
    function encrypt($id){return 'test-'.$id;}
    function get_dsp_channel(){return 'cli';}function freeze_dsp_channel(){}
    function get_prohibit_new_db(){return false;}function set_prohibit_new_db($v){}
    function db($name,$class){$key=$class.'/'.$name;if(isset($this->pool[$key]))return $this->pool[$key];@mkdir($this->root.'/classes/data/'.$class,0770,true);$fmt=$class==='db'?$this->root.'/fmt':$this->fbp.'/app/dsp/fmt';return $this->pool[$key]=new fixed_file_manager($name,$this->root.'/classes/data/'.$class,$fmt,['controller'=>$this,'database_class'=>$class]);}
    function POST($key=null){return $key===null?$this->post:($this->post[$key]??null);}
    function assign(...$args){}function t($key){return $key;}function show_multi_dialog(...$args){}function close_multi_dialog(...$args){}function reload_area(...$args){}
    function clear_error_message(){$this->errors=[];}function res_error_message($field,$text){$this->errors[$field]=$text;}
    function res_json($value){$this->response=$value;}
}
$fbp=$argv[1];$root=$argv[2];require $fbp.'/lib/fixed_file_manager/fixed_file_manager.php';require $fbp.'/app/dsp/dsp.php';putenv('FBP_FFM_LOG_DISABLE=1');
function check($value){global $checks;if(!$value)throw new RuntimeException('Assertion failed');$checks++;}
function removeTree($dir){foreach(array_diff(scandir($dir),['.','..'])as $name){$p=$dir.'/'.$name;is_dir($p)?removeTree($p):unlink($p);}rmdir($dir);}
$checks=0;$ctl=null;
try{
 mkdir($root.'/fmt',0770,true);file_put_contents($root.'/fmt/db.fmt',"id,24,N\ntb_name,100,T\nmenu_name,100,T\n");$ctl=new Controller_class($root,$fbp);$note=['tb_name'=>'reservations','menu_name'=>'Reservations'];$ctl->db('db','db')->insert($note);$panel=new dsp($ctl);$db=$ctl->db('policies','dsp');
 $valid=['id'=>0,'note_id'=>$note['id'],'operation'=>'update','mode'=>'custom','conditions'=>'Owner or manager'];
 foreach([['mode'=>'invalid'],['conditions'=>''],['note_id'=>'1junk'],['note_id'=>999],['operation'=>'get'],['conditions'=>['bad']],['conditions'=>str_repeat('x',6001)]]as $change){$ctl->post=array_replace($valid,$change);$ctl->errors=[];$panel->save($ctl);check((bool)$ctl->errors);check(count($db->getall())===0);}
 $ctl->post=$valid;$ctl->errors=[];$panel->save($ctl);$rows=$db->getall();check(count($rows)===1&&$rows[0]['conditions']==='Owner or manager');$id=$rows[0]['id'];
 $ctl->post=$valid;$ctl->errors=[];$panel->save($ctl);check(isset($ctl->errors['operation'])&&count($db->getall())===1);
 $ctl->post=['note_id'=>$note['id']];$panel->generate_template($ctl);check(str_contains($ctl->response['php'],'implementation_required'));check(!isset($ctl->response['definition_hashes']));check(!is_dir($root.'/classes/app/_dsp'));
 $ctl->post=array_replace($valid,['id'=>$id,'mode'=>'allow']);$ctl->errors=[];$panel->save($ctl);check($db->get($id)['mode']==='allow'&&$db->get($id)['conditions']==='');
 $ctl->post=['id'=>$id];$panel->delete_exe($ctl);check($db->getall()===[]);
 $ctl->post=['command'=>'add','note'=>'reservations','operation'=>'Read','mode'=>'Deny'];$panel->cli_command($ctl);check($ctl->response['ok']===true);$cliId=$ctl->response['item']['id'];
 $ctl->post=['command'=>'add','note'=>'reservations','operation'=>'read','mode'=>'Allow'];$panel->cli_command($ctl);check($ctl->response['ok']===false&&isset($ctl->response['errors']['operation']));
 $ctl->post=['command'=>'edit','id'=>$cliId,'mode'=>'Allow'];$panel->cli_command($ctl);check($ctl->response['item']['mode']==='allow'&&$ctl->response['item']['note_id']===$note['id']);
 $ctl->post=['command'=>'list','note'=>'reservations','mode'=>'ALLOW'];$panel->cli_command($ctl);check(count($ctl->response['items'])===1);
 $ctl->testserver=false;$ctl->post=['command'=>'delete','id'=>$cliId];$panel->cli_command($ctl);check($ctl->response['error']==='test_only'&&$db->get($cliId)!==null);$ctl->testserver=true;
 $ctl->post=['command'=>'delete','id'=>$cliId];$panel->cli_command($ctl);check($db->getall()===[]);
 $readonlyRoot=$root.'/readonly';mkdir($readonlyRoot.'/fmt',0770,true);copy($root.'/fmt/db.fmt',$readonlyRoot.'/fmt/db.fmt');
 $reader=new Controller_class($readonlyRoot,$fbp);$seed=['tb_name'=>'example','menu_name'=>'Example'];$reader->db('db','db')->insert($seed);
 foreach(['notes','list','get']as $command){$reader->post=['function'=>'cli_command','command'=>$command,'id'=>999];$readPanel=new dsp($reader);$readPanel->cli_command($reader);check(!is_file($readonlyRoot.'/classes/data/dsp/policies.dat'));}
 foreach($reader->pool as $handle)$handle->close();
 $forbidden=new Controller_class($root.'/forbidden',$fbp);$forbidden->testserver=false;$forbidden->post=['function'=>'cli_command','command'=>'add'];new dsp($forbidden);check($forbidden->response['error']==='test_only'&&$forbidden->pool===[]);
 echo json_encode(['passed'=>$checks,'duplicate_rejected'=>true,'conditions_validated'=>true,'settings_do_not_create_runtime_code'=>true])."\n";
}finally{if($ctl)foreach($ctl->pool as $db)$db->close();if(is_dir($root))removeTree($root);}

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
    function get_dsp_system_subject(){return null;}function get_classname(){return 'dsp';}
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
 mkdir($root.'/fmt',0770,true);file_put_contents($root.'/fmt/db.fmt',"id,24,N\ntb_name,100,T\nmenu_name,100,T\n");$ctl=new Controller_class($root,$fbp);$note=['tb_name'=>'reservations','menu_name'=>'Reservations'];$ctl->db('db','db')->insert($note);
 // Existing definition data must survive the appended class/table fields.
 mkdir($root.'/legacyfmt',0770,true);file_put_contents($root.'/legacyfmt/policies.fmt',"id,24,N\nnote_id,24,N\noperation,12,T\nmode,12,T\nconditions,6000,T\n");
 mkdir($root.'/classes/data/dsp',0770,true);
 $legacy=new fixed_file_manager('policies',$root.'/classes/data/dsp',$root.'/legacyfmt',['controller'=>$ctl,'database_class'=>'dsp']);
 $legacyRow=['note_id'=>$note['id'],'operation'=>'delete','mode'=>'deny','conditions'=>''];$legacy->insert($legacyRow);$legacy->close();
 $panel=new dsp($ctl);$db=$ctl->db('policies','dsp');check($db->get($legacyRow['id'])['mode']==='deny');check($db->get($legacyRow['id'])['note_id']===$note['id']);check($db->get($legacyRow['id'])['db_class']==='');$db->delete($legacyRow['id']);
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
 // Two classes with the same table name remain different physical DSP targets.
 foreach(['account','exam'] as $class){mkdir($root.'/classes/app/'.$class.'/fmt',0770,true);file_put_contents($root.'/classes/app/'.$class.'/'.$class.'.php','<?php');file_put_contents($root.'/classes/app/'.$class.'/fmt/student.fmt',"id,24,N\nname,100,T\n");}
 $panel=new dsp($ctl);
 // Framework fallback follows runtime class precedence; outside-root fmt links are excluded.
 foreach(['catalog_app','catalog_framework'] as $dir){mkdir($root.'/'.$dir.'/shared/fmt',0770,true);file_put_contents($root.'/'.$dir.'/shared/shared.php','<?php');file_put_contents($root.'/'.$dir.'/shared/fmt/'.($dir==='catalog_app'?'app':'framework').'.fmt',"id,24,N\n");}
 mkdir($root.'/catalog_framework/framework_only/fmt',0770,true);file_put_contents($root.'/catalog_framework/framework_only/framework_only.php','<?php');file_put_contents($root.'/catalog_framework/framework_only/fmt/table.fmt',"id,24,N\n");
 file_put_contents($root.'/outside.fmt',"id,24,N\n");symlink($root.'/outside.fmt',$root.'/catalog_app/shared/fmt/outside.fmt');
 $catalog=DspTargetCatalog::customTargets($root.'/catalog_app',$root.'/catalog_framework');$catalogKeys=array_column($catalog,'target');check(in_array('db:shared/app',$catalogKeys,true));check(in_array('db:framework_only/table',$catalogKeys,true));check(!in_array('db:shared/framework',$catalogKeys,true));check(!in_array('db:shared/outside',$catalogKeys,true));
 $ctl->post=['command'=>'targets'];$panel->cli_command($ctl);$keys=array_column($ctl->response['items'],'target');check(in_array('db:account/student',$keys,true)&&in_array('db:exam/student',$keys,true)&&in_array('db:user/user',$keys,true));
 $ctl->post=['command'=>'add','db_class'=>'account','db_table'=>'student','operation'=>'read','mode'=>'deny'];$panel->cli_command($ctl);check($ctl->response['ok']);$customId=$ctl->response['item']['id'];check($ctl->response['item']['note_id']===0);check($ctl->response['item']['target']==='db:account/student');
 $panel->cli_command($ctl);check(!$ctl->response['ok']&&isset($ctl->response['errors']['operation']));
 $ctl->post=['command'=>'add','db_class'=>'exam','db_table'=>'student','operation'=>'read','mode'=>'allow'];$panel->cli_command($ctl);check($ctl->response['ok']);$otherId=$ctl->response['item']['id'];
 foreach([['db_class'=>'../account','db_table'=>'student'],['db_class'=>'account','db_table'=>'absent'],['db_class'=>'account','db_table'=>'student/../student'],['db_class'=>'account'],['target'=>'db:missing/student']] as $invalid){$ctl->post=['command'=>'add','operation'=>'add','mode'=>'allow']+$invalid;$panel->cli_command($ctl);check(!$ctl->response['ok']);check(count($db->getall())===2);}
 $ctl->post=['command'=>'list','db_class'=>'account','db_table'=>'student'];$panel->cli_command($ctl);check(count($ctl->response['items'])===1&&$ctl->response['items'][0]['id']===$customId);
 foreach([['note'=>'reservations','db_class'=>'account','db_table'=>'student'],['target'=>'db:account/student','note_id'=>$note['id']]] as $ambiguous){$ctl->post=['command'=>'add','operation'=>'add','mode'=>'allow']+$ambiguous;$panel->cli_command($ctl);check(!$ctl->response['ok']);}
 $ctl->post=['command'=>'edit','id'=>$customId,'mode'=>'custom','conditions'=>'Owner only'];$panel->cli_command($ctl);check($ctl->response['item']['db_class']==='account'&&$ctl->response['item']['db_table']==='student');
 $ctl->post=['db_class'=>'account','db_table'=>'student'];$panel->generate_template($ctl);$template=$ctl->response;check(isset($template['registry']['account/student']));check($template['rules']['read']['conditions']==='Owner only');check(str_contains($template['php'],'implementation_required'));
 $ctl->post=['db_class'=>'exam','db_table'=>'student'];$panel->generate_template($ctl);check($template['registry']['account/student']['class']!==$ctl->response['registry']['exam/student']['class']);
 $ctl->post=['target'=>'db:account/student'];$panel->generate_template($ctl);check($ctl->response===$template);check(!is_dir($root.'/classes/app/_dsp'));
 // Generated class/table registry must bind to the existing independent data path.
 $ctl->post=['command'=>'edit','id'=>$customId,'mode'=>'deny'];$panel->cli_command($ctl);check($ctl->response['ok']);
 $ctl->post=['db_class'=>'account','db_table'=>'student'];$panel->generate_template($ctl);$runtimeTemplate=$ctl->response;
 mkdir($root.'/classes/data/account',0770,true);$data=new fixed_file_manager('student',$root.'/classes/data/account',$root.'/classes/app/account/fmt',['controller'=>$ctl,'database_class'=>'account']);$student=['name'=>'Existing'];$data->insert($student);$data->close();
 $dataHash=hash_file('sha256',$root.'/classes/data/account/student.dat');$fmtHash=hash_file('sha256',$root.'/classes/app/account/fmt/student.fmt');
 mkdir($root.'/classes/app/_dsp',0770,true);$entry=$runtimeTemplate['registry']['account/student'];file_put_contents($root.'/classes/app/_dsp/'.$entry['file'],$runtimeTemplate['php']);file_put_contents($root.'/classes/app/_dsp/registry.php','<?php return '.var_export($runtimeTemplate['registry'],true).';');
 $data=new fixed_file_manager('student',$root.'/classes/data/account',$root.'/classes/app/account/fmt',['controller'=>$ctl,'database_class'=>'account']);$denied=false;try{$data->get($student['id']);}catch(DspException $expected){$denied=$expected->getDetails()['reason']==='operation_denied';}check($denied);$data->close();
 check(hash_file('sha256',$root.'/classes/data/account/student.dat')===$dataHash);check(hash_file('sha256',$root.'/classes/app/account/fmt/student.fmt')===$fmtHash);
 mkdir($root.'/classes/data/exam',0770,true);$otherData=new fixed_file_manager('student',$root.'/classes/data/exam',$root.'/classes/app/exam/fmt',['controller'=>$ctl,'database_class'=>'exam']);$examRow=['name'=>'Separate class'];$otherData->insert($examRow);check($otherData->get($examRow['id'])['name']==='Separate class');$otherData->close();
 // A common DB alias cannot bypass note/operation duplicate detection.
 $ctl->post=['command'=>'add','db_class'=>'common','db_table'=>'reservations','operation'=>'read','mode'=>'allow'];$panel->cli_command($ctl);check($ctl->response['ok']&&$ctl->response['item']['note_id']===$note['id']);$aliasId=$ctl->response['item']['id'];
 $ctl->post=['command'=>'add','note'=>'reservations','operation'=>'read','mode'=>'deny'];$panel->cli_command($ctl);check(!$ctl->response['ok']&&isset($ctl->response['errors']['operation']));
 foreach([$customId,$otherId,$aliasId] as $deleteId){$ctl->post=['command'=>'delete','id'=>$deleteId];$panel->cli_command($ctl);check($ctl->response['ok']);}
 $readonlyRoot=$root.'/readonly';mkdir($readonlyRoot.'/fmt',0770,true);copy($root.'/fmt/db.fmt',$readonlyRoot.'/fmt/db.fmt');
 $reader=new Controller_class($readonlyRoot,$fbp);$seed=['tb_name'=>'example','menu_name'=>'Example'];$reader->db('db','db')->insert($seed);
 foreach(['notes','targets','list','get','generate_template']as $command){$reader->post=['function'=>'cli_command','command'=>$command,'id'=>999,'note'=>'example'];$readPanel=new dsp($reader);$readPanel->cli_command($reader);check(!is_file($readonlyRoot.'/classes/data/dsp/policies.dat'));}
 foreach($reader->pool as $handle)$handle->close();
 $forbidden=new Controller_class($root.'/forbidden',$fbp);$forbidden->testserver=false;$forbidden->post=['function'=>'cli_command','command'=>'add'];new dsp($forbidden);check($forbidden->response['error']==='test_only'&&$forbidden->pool===[]);
 echo json_encode(['passed'=>$checks,'duplicate_rejected'=>true,'conditions_validated'=>true,'settings_do_not_create_runtime_code'=>true])."\n";
}finally{if($ctl)foreach($ctl->pool as $db)$db->close();if(is_dir($root))removeTree($root);}

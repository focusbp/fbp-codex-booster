<?php
/** Generated code is exercised through FFM. No runtime definition DB access is allowed. */
if(PHP_SAPI!=='cli'||$argc!==3||file_exists($argv[2]))exit(2);
interface Controller {}
class Controller_class implements Controller {
    public bool $guard=false;
    static function getInstance(){return null;}
    function encrypt($id){return 'test-'.$id;}
    function get_dsp_channel(){return 'cli';}
    function freeze_dsp_channel(){}
    function get_prohibit_new_db(){return $this->guard;}
    function set_prohibit_new_db($value){$this->guard=$value;}
    function db(...$args){throw new RuntimeException('Unexpected runtime DB access');}
}
$fbp=rtrim($argv[1],'/');$workspace=$argv[2];require $fbp.'/lib/fixed_file_manager/fixed_file_manager.php';require $fbp.'/lib/DspPolicyTemplate.php';
putenv('FBP_FFM_LOG_DISABLE=1');$checks=0;
function verify($ok){global $checks;if(!$ok)throw new RuntimeException('Assertion failed');$checks++;}
function operation($callback,$allowed){try{$callback();verify($allowed);}catch(DspException $e){verify(!$allowed);}}
function removeTree($dir){foreach(array_diff(scandir($dir),['.','..'])as $n){$p=$dir.'/'.$n;is_dir($p)?removeTree($p):unlink($p);}rmdir($dir);}
try {
 for($mask=0;$mask<17;$mask++) {
  $root=$workspace.'/'.$mask;mkdir($root.'/classes/data/_common',0770,true);mkdir($root.'/classes/app/_dsp',0770,true);mkdir($root.'/fmt',0770,true);
  file_put_contents($root.'/fmt/reservations.fmt',"id,24,N\nname,30,T\n");$ctl=new Controller_class;
  $db=new fixed_file_manager('reservations',$root.'/classes/data/_common',$root.'/fmt',['controller'=>$ctl,'database_class'=>'common']);$row=['name'=>'Original'];$db->insert($row);$db->close();
  $rules=[];foreach(['add','read','update','delete']as $i=>$op)$rules[$op]=['mode'=>($mask>>$i)&1?'deny':'allow','conditions'=>''];
  if($mask===16)$rules['update']=['mode'=>'custom','conditions'=>'Owner only'];
  $class='TemplatePolicy'.$mask;$result=DspPolicyTemplate::generate($class,$rules);file_put_contents($root.'/classes/app/_dsp/'.$class.'.php',$result['php']);
  file_put_contents($root.'/classes/app/_dsp/registry.php','<?php return '.var_export(['common/reservations'=>['file'=>$class.'.php','class'=>$class]],true).';');
  $db=new fixed_file_manager('reservations',$root.'/classes/data/_common',$root.'/fmt',['controller'=>$ctl,'database_class'=>'common']);
  $hash=hash_file('sha256',$db->get_path_dat());operation(fn()=>$db->get(1),$rules['read']['mode']==='allow');
  operation(fn()=>$db->update(['id'=>1,'name'=>'Changed']),$rules['update']['mode']==='allow');
  if($rules['update']['mode']!=='allow')verify(hash_file('sha256',$db->get_path_dat())===$hash);
  $row=['name'=>'New'];operation(function()use($db,&$row){$db->insert($row);},$rules['add']['mode']==='allow');
  operation(fn()=>$db->delete(1),$rules['delete']['mode']==='allow');verify($ctl->guard===false);$db->close();
 }
 $empty=$workspace.'/empty';mkdir($empty.'/classes/data/_common',0770,true);mkdir($empty.'/fmt',0770,true);file_put_contents($empty.'/fmt/reservations.fmt',"id,24,N\nname,30,T\n");
 $db=new fixed_file_manager('reservations',$empty.'/classes/data/_common',$empty.'/fmt',['controller'=>new Controller_class,'database_class'=>'common']);$row=['name'=>'Allow'];$id=$db->insert($row);$db->update(['id'=>$id,'name'=>'Updated']);verify($db->get($id)['name']==='Updated');$db->delete($id);verify($db->getall()===[]);$db->close();
 echo json_encode(['passed'=>$checks,'combinations'=>16,'custom_pending'=>'denied','undefined'=>'all_allow','runtime_definition_db_access'=>0])."\n";
}finally{if(is_dir($workspace))removeTree($workspace);}

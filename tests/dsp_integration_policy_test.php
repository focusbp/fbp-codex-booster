<?php
/** Disposable real FFM storage; run against the synchronized test framework. */
if (PHP_SAPI !== 'cli' || $argc !== 3 || file_exists($argv[2])) exit(2);
interface Controller {}
class Controller_class implements Controller {
    public string $entry = 'project_integration_api', $channel = 'api';
    public array $subject = ['kind'=>'api', 'verified'=>true], $setting = [];
    public bool $guard = false;
    public static function getInstance() { return null; }
    function get_classname() { return $this->entry; }
    function get_dsp_channel() { return $this->channel; }
    function get_dsp_system_subject() { return $this->subject; }
    function get_session($key) { return $this->setting; }
    function get_prohibit_new_db() { return $this->guard; }
    function set_prohibit_new_db($v) { $this->guard=$v; }
    function freeze_dsp_channel() {}
    function encrypt($v) { return 'fixture-'.$v; }
    function db(...$args) { throw new RuntimeException('App dependencies must not be opened'); }
}
require $argv[1].'/fbp/lib/fixed_file_manager/fixed_file_manager.php';
$checks=0;
function check($ok,$label) { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
function denied(callable $f) { try {$f();} catch(DspException $e) {check(true,'denied');return;} throw new RuntimeException('Expected refusal'); }
$root=$argv[2];$dir=$root.'/classes/data/_common';$fmt=$root.'/fmt';
mkdir($dir,0770,true);mkdir($fmt,0770,true);mkdir($root.'/classes/app/_dsp',0770,true);
file_put_contents($fmt.'/task.fmt',"id,12,N\nparent_id,12,N\nstatus,4,N\ncode_change_allowed,1,N\nlast_comment_at,12,N\n");
$seed=new fixed_file_manager('task',$dir,$fmt);$r=['parent_id'=>7,'status'=>3,'code_change_allowed'=>0];$seed->insert($r);$seed->close();
// Broken app rules must never load for the authenticated, fixed path.
file_put_contents($root.'/classes/app/_dsp/registry.php','<?php throw new RuntimeException("App registry loaded");');
$c=new Controller_class;
$open=fn()=>new fixed_file_manager('task',$dir,$fmt,['controller'=>$c,'database_class'=>'common']);
$db=$open();check($db->get(1)['status']===3,'Read before resumption');
$db->update(['id'=>1,'status'=>0]);check($db->get(1)['status']===0,'Resume despite broken app DSP');
denied(fn()=>$db->update(['id'=>1,'code_change_allowed'=>1]));
denied(fn()=>$db->update(['id'=>1,'parent_id'=>8]));
denied(fn()=>$db->update(['id'=>1,'status'=>1]));
denied(fn()=>$db->delete(1));
$c->subject=[];denied(fn()=>$db->get(1));
$c->subject=['kind'=>'api','verified'=>true];check(count($db->getall())===1,'Proof restoration');$db->close();
foreach (['task_api','db_api','public_project_support','unrelated_api'] as $entry) {
 $c->entry=$entry;check(DspIntegrationPolicy::resolve($c,'api','common','task')===null,'Unrelated entry retains app policy');
}
$c->entry='project_integration_api';
foreach (['admin','public','mcp','cron','cli'] as $channel) check(DspIntegrationPolicy::resolve($c,$channel,'common','task')===null,'Wrong channel');
$c->subject=['kind'=>'api','verified'=>'true'];check(DspIntegrationPolicy::resolve($c,'api','common','task')===null,'Strict proof');
$c->subject=['kind'=>'api','verified'=>true];
check(DspIntegrationPolicy::resolve($c,'api','common','invoice')===null,'Unrelated DB');
check(DspRuntime::integrationPolicy($root.'/classes/data/common_7','task',$c,'api','common')===null,'Partitions excluded');
$c->setting=['dsp_disabled'=>1];$db=$open();denied(fn()=>$db->delete(1));$db->close();
// Cached DB opened during bootstrap must acquire fixed rules after HMAC succeeds.
$c->subject=[];$db=$open();$c->subject=['kind'=>'api','verified'=>true];check($db->get(1)['status']===0,'Cached handle after verification');$db->close();
$c->entry='integration_settings';
$p=DspIntegrationPolicy::resolve($c,'api','setting','setting');check($p!==null,'Target endpoint');
$p->authorizeUpdate(['id'=>1,'line_accesstoken'=>'old'],['id'=>1,'line_accesstoken'=>'new'],['id','line_accesstoken']);check(true,'Allow setting field');
denied(fn()=>$p->authorizeUpdate(['id'=>1,'dsp_disabled'=>0],['id'=>1,'dsp_disabled'=>1],['dsp_disabled']));
denied(fn()=>$p->authorizeDelete(['id'=>1]));
check(DspIntegrationPolicy::resolve($c,'api','common','task')===null,'Target cannot manage task');
$c->subject=[];check(DspIntegrationPolicy::resolve($c,'cli','external_keys','external_keys')!==null,'Local CLI settings worker');
check(DspIntegrationPolicy::resolve($c,'api','external_keys','external_keys')===null,'Unverified HTTP');
$c->subject=['kind'=>'api','verified'=>true];$c->entry='project_integration_api';
$itemsDir=$root.'/classes/data/project_integration';mkdir($itemsDir,0770,true);
file_put_contents($fmt.'/items.fmt',"id,12,N\nkey,64,T\npart,4,N\ndocument,255,T\n");
$db=new fixed_file_manager('items',$itemsDir,$fmt,['controller'=>$c,'database_class'=>'project_integration']);
$chunk=['key'=>'fixture','part'=>1,'document'=>'synthetic'];$db->insert($chunk);
check($db->select('key','fixture',true)[0]['document']==='synthetic','Queue insert/read');
$db->update(['id'=>$chunk['id'],'document'=>'changed']);check($db->get($chunk['id'])['document']==='changed','Queue update');
$db->delete($chunk['id']);check($db->get($chunk['id'])===null,'Queue cleanup');$db->close();
$p=DspIntegrationPolicy::resolve($c,'api','common','task_history');
$saved=['role'=>1,'message'=>'[INTEGRATION_SAVED request=1]'];$p->authorizeInsert($saved);
$p->authorizeUpdate(['role'=>1,'message'=>'[INTEGRATION_SUBMITTED request=1 batch=x]'],$saved,['message']);check(true,'Integration history');
denied(fn()=>$p->authorizeInsert(['role'=>2,'message'=>'unrelated']));
denied(fn()=>$p->authorizeUpdate(['role'=>1,'message'=>'customer text'],$saved,['message']));
$c->subject=[];$c->setting=[];
try {$db=$open();throw new RuntimeException('Unverified entry skipped broken registry');}
catch (DspException $e) {check(true,'Unverified entry retains app load failure');}
check(!$c->guard,'Connection guard restored');
echo "PASS: $checks fixed integration policy checks\n";

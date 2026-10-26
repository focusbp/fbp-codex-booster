<?php
require __DIR__.'/../classes/app/simple_ec/Store.php';
require __DIR__.'/../classes/app/simple_ec/Visibility.php';
require __DIR__.'/../classes/app/simple_ec/Mail.php';
require __DIR__.'/../classes/app/simple_ec_products/simple_ec_products.php';
require __DIR__.'/../classes/app/simple_ec_cron/simple_ec_cron.php';
class FixtureDb {function __construct(public array $rows=[]){ }function getall(){return $this->rows;}}
class Controller {
    public array $types=[0=>'物販'],$post=[],$errors=[],$response=[];public int $dbCalls=0,$mailCalls=0,$role=0;public string $login='admin@example.invalid';public bool $test=false;public int $mode=0;
    function get_constant_array($key,$empty=false){return $this->types;}function get_login_id(){return $this->login;}function get_login_type(){return $this->role;}function POST($key=null){return $key===null?$this->post:($this->post[$key]??null);}function res_error_message(...$args){$this->errors[]=$args;}
    function get_setting(){return ['force_testmode'=>$this->mode,'timezone'=>'Asia/Tokyo','smtp_email_test'=>'fixed@example.invalid'];}function get_session($key){return $this->test;}function get_dsp_system_subject(){return [];}function res_json($value){$this->response=$value;}function db(...$args){$this->dbCalls++;return new FixtureDb;}
    function send_mail_prepared_format(...$args){$this->mailCalls++;}function assign(...$args){}
}
function check($value,$message){if(!$value)throw new RuntimeException($message);}
function reject(callable $f){try{$f();}catch(RuntimeException $e){return;}throw new LogicException('Expected rejection');}
$c=new Controller;$shop=['login_id'=>'shop','paytype'=>0,'soryo'=>200];
foreach([[0],[1],[2],[3],[4],[0,4]] as $selected){
 $c->types=array_combine($selected,array_map(fn($k)=>'種別'.$k,$selected));SimpleEcStore::configureTypes(SimpleEcTypes::labels($c));
 foreach(range(0,4) as $type){
  check(SimpleEcTypes::allows($c,$type)===in_array($type,$selected,true),'selected subset uses stable semantic IDs');
  $p=['id'=>1,'login_id'=>'shop','type'=>$type,'hyoji'=>0,'code1'=>'標準','price1'=>100,'teiki_getugaku'=>100,'zeiritu'=>10,'zaiko_check'=>1,'zaiko1'=>2];$call=fn()=>SimpleEcStore::quote($shop,[1=>$p],['1:1'=>['id'=>1,'variant'=>1,'count'=>1]]);
  if(in_array($type,$selected,true)){$q=$call();check($q['total']===($type===0?310:(in_array($type,[2,3],true)?0:110)),'type-specific price/tax/shipping');}else reject($call);
 }
}
$c->types=[1=>'独自のイベント名'];check(array_keys(SimpleEcTypes::labels($c))===[1],'event-only never gets renumbered to physical');
$c->types=[8=>'unknown'];reject(fn()=>SimpleEcTypes::labels($c));$c->types=[];reject(fn()=>SimpleEcTypes::labels($c));$c->types=[0=>'物販'];
$c->post=['type'=>'4'];(new simple_ec_products)->save($c);check(count($c->errors)===1&&$c->dbCalls===0,'forged disabled product type rejected before DB writes');
(new simple_ec_cron)->run($c);check(($c->response['reason']??'')==='subscription_not_enabled'&&$c->dbCalls===0,'unused subscription cron makes no DB/provider calls');
$v=new SimpleEcVisibility;check(!$v->can_show_menu($c,'sec_contracts',[]),'unused contract menu hidden');$c->types=[4=>'定期'];check($v->can_show_menu($c,'sec_contracts',[]),'registered subscription enables contract menu');check(!$v->can_show_menu($c,'sec_secrets',[]),'card identifiers stay private');
$c->role=1;check(!SimpleEcStore::administrator($c),'normal users are not administrators');check(!$v->can_access($c,'page','sec_orders',[]),'normal user cannot read orders');$c->role=0;
$d=['runtime'=>new FixtureDb];check(!SimpleEcPaymentGuard::enabled($d),'empty settings keep acceptance off');reject(fn()=>SimpleEcPaymentGuard::recurringStartMonth($d));
$d['runtime']->rows=[['id'=>19,'live_enabled'=>1,'mail_enabled'=>0,'cron_enabled'=>0,'recurring_start_date'=>strtotime('2032-04-01')]];check(SimpleEcPaymentGuard::enabled($d),'singleton ID is not assumed to be 1');check(SimpleEcPaymentGuard::recurringStartMonth($d)==='2032-04','recurrence starts from deployment-specific settings');
foreach([false,true] as $sandbox){$c->test=$sandbox;$c->mode=$sandbox?1:0;check(SimpleEcMail::send($c,$d,['unused'])===0&&$c->mailCalls===0,'mail OFF applies in production and Sandbox');}
check(SimpleEcMail::recipient(['recipient'=>'real@example.invalid','event_key'=>'preview:one:shop'],['smtp_email_test'=>'fixed@example.invalid'],[],false)==='fixed@example.invalid','Sandbox shop notices use fixed test recipient');
$d['runtime']->rows[]=['id'=>20,'live_enabled'=>1];reject(fn()=>SimpleEcPaymentGuard::enabled($d));
echo "PASS: all type subsets, forged submissions, menu/cron exclusion, generic admin authorization, configurable start month, singleton identity and disabled SMTP\n";

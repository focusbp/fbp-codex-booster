<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../classes/app/simple_ec/ProductionPayment.php';
if(!getenv('SIMPLE_EC_FRAMEWORK_ROOT') || !is_file(rtrim(getenv('SIMPLE_EC_FRAMEWORK_ROOT'),'/').'/cli.php') || !getenv('SIMPLE_EC_TEST_TEMP') || !is_dir(getenv('SIMPLE_EC_TEST_TEMP')))throw new RuntimeException('Use the bundled runner with explicit framework and temporary roots.');
class Controller_class {public static function getInstance(){return null;}}
putenv('FBP_FFM_LOG_DISABLE=1');require rtrim((string)getenv('SIMPLE_EC_FRAMEWORK_ROOT'),'/').'/lib/fixed_file_manager/fixed_file_manager.php';
class Controller {
 function __construct(private string $root){}
 function get_constant_array($key,$empty=false){return [0=>'物販'];}
 function get_setting(){return ['force_testmode'=>0,'square_application_id'=>'production-mock','square_access_token'=>'fake-token'];}function get_session($k){return false;}function POST($k){return 'fake-nonce';}function set_square(...$args){}
 function square_regist_customer(...$a){file_put_contents($this->root.'/customers',"1\n",FILE_APPEND);return 'mock-customer';}
 function square_regist_card(...$a){file_put_contents($this->root.'/cards',"1\n",FILE_APPEND);return 'mock-card';}
 function square_payment(...$a){file_put_contents($this->root.'/payments',"1\n",FILE_APPEND);usleep(200000);return true;}
 function square_get_payment_result(){return ['id'=>'mock-payment','status'=>'COMPLETED','amount_money'=>['amount'=>1100,'currency'=>'JPY']];}
}
function fixtureDb($root){$db=[];foreach(['orders','payments','secrets','shops','runtime'] as $t)$db[$t]=new fixed_file_manager($t,$root.'/data',$root.'/fmt');return $db;}
if(($argv[1]??'')==='worker'){$db=fixtureDb($argv[2]);SimpleEcProductionPayment::pay(new Controller($argv[2]),$db,$db['orders']->get(1),fn()=>'mock-location');exit;}
$root=rtrim((string)getenv('SIMPLE_EC_TEST_TEMP'),'/').'/simple-ec-lock-'.bin2hex(random_bytes(4));mkdir($root.'/data',0700,true);mkdir($root.'/fmt',0700);
$spec=['orders'=>['record_key'=>255,'metadata_json'=>1000,'login_id'=>255,'name'=>255,'from_name'=>255,'mail'=>255,'gokei'=>24,'type'=>24,'status'=>24,'paytype'=>255],'payments'=>['name'=>255,'mail'=>255,'status'=>24,'environment'=>40,'login_id'=>255,'order_id'=>24,'period'=>40,'amount'=>24,'state'=>40,'source'=>100,'result'=>100,'idempotency_key'=>100,'created_at'=>24,'payment_id'=>100,'error'=>2000],'secrets'=>['record_key'=>255,'login_id'=>255,'source_table'=>100,'payload'=>12000],'shops'=>['login_id'=>255,'paytype'=>24],'runtime'=>['record_key'=>255,'live_enabled'=>24]];
foreach($spec as $t=>$fields){$fmt="id,24,N\n";foreach($fields as $k=>$length)$fmt.=$k.','.$length.','.($length===24?'N':'T')."\n";file_put_contents($root.'/fmt/'.$t.'.fmt',$fmt);}
$db=fixtureDb($root);$r=['record_key'=>'precutover','live_enabled'=>1];$db['runtime']->insert($r);$r=['login_id'=>'shop-fixture','paytype'=>2];$db['shops']->insert($r);$r=['record_key'=>'live:lock-test','metadata_json'=>'{"checkout_state":"ready"}','login_id'=>'shop-fixture','name'=>'fixture','from_name'=>'fixture','mail'=>'test@example.invalid','gokei'=>1100,'type'=>0,'status'=>0];$db['orders']->insert($r);foreach($db as $f)$f->close();unset($db);
$workers=[];for($i=0;$i<2;$i++){$proc=proc_open([PHP_BINARY,__FILE__,'worker',$root],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$workers[]=[$proc,$pipes];}
foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);if(proc_close($proc)!==0)throw new RuntimeException($out.$err);}
foreach(['payments','customers','cards'] as $f)if(file_get_contents($root.'/'.$f)!=="1\n")throw new RuntimeException('Duplicate '.$f);
function removeFixture($root){foreach(array_diff(scandir($root),['.','..']) as $n){$p=$root.'/'.$n;is_dir($p)?removeFixture($p):unlink($p);}rmdir($root);}removeFixture($root);
echo "PASS: concurrent FFM workers create exactly one mock customer, card and payment\n";

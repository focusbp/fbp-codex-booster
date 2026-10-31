<?php
if(PHP_SAPI!=='cli')exit;
$fw=getenv('RECONCILIATION_FRAMEWORK_ROOT');$tmp=getenv('RECONCILIATION_TEMP_ROOT');
if(!$fw||!is_file($fw.'/cli.php')||!$tmp||!is_dir($tmp))throw new RuntimeException('Use the bundled runner with explicit roots.');
class Controller_class {static function getInstance(){return null;}}
putenv('FBP_FFM_LOG_DISABLE=1');require $fw.'/lib/fixed_file_manager/fixed_file_manager.php';
require __DIR__.'/../classes/app/sample_reconciliation/Store.php';
class Controller {
 private array $pool=[];
 function __construct(private string $root){}
 function db($t){return $this->pool[$t]??=new fixed_file_manager($t,$this->root.'/data',$this->root.'/fmt');}
}
if(($argv[1]??'')==='worker'){
 $s=new SampleReconciliationStore(new Controller($argv[2]));
 $expected=$argv[3]==='fresh'?$s->fingerprint():$argv[3];
 try{$s->add('left',1,[1],1,'worker',$expected);}catch(InvalidArgumentException $e){if(!str_contains($e->getMessage(),'更新'))throw $e;echo "stale rejected\n";}
 exit;
}
$root=$tmp.'/reconciliation-lock-'.bin2hex(random_bytes(5));mkdir($root.'/data',0700,true);mkdir($root.'/fmt',0700);
$m=json_decode(file_get_contents(__DIR__.'/../many-to-many-reconciliation.json'),true,512,JSON_THROW_ON_ERROR);
foreach($m['notes'] as $note){$fmt="id,24,N\n";foreach($note['fields'] as $f)$fmt.=$f['parameter_name'].','.$f['length'].','.((in_array($f['type'],['number','date','world_datetime'],true))?'N':'T')."\n";file_put_contents($root.'/fmt/'.$note['table']['tb_name'].'.fmt',$fmt);}
$ctl=new Controller($root);$s=new SampleReconciliationStore($ctl);
foreach([$s::LEFT=>72000,$s::RIGHT=>40000] as $table=>$amount){$r=['scope_key'=>'sample','currency'=>'JPY','amount'=>$amount,'eligibility'=>0];$ctl->db($table)->insert($r);}
$expected=$s->fingerprint();foreach([$s::LEFT,$s::RIGHT,$s::LINKS,$s::EVENTS] as $table)$ctl->db($table)->close();unset($s,$ctl);gc_collect_cycles();
function workers($root,$token){$all=[];for($i=0;$i<2;$i++){$p=proc_open([PHP_BINARY,__FILE__,'worker',$root,$token],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$all[]=[$p,$pipes];}foreach($all as [$p,$pipes]){$o=stream_get_contents($pipes[1]);$e=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);if(proc_close($p)!==0)throw new RuntimeException($o.$e);}}
try{
 workers($root,$expected);workers($root,'fresh');
 $ctl=new Controller($root);$s=new SampleReconciliationStore($ctl);$snapshot=$s->snapshot();
 if(count($snapshot['links'])!==1||count($ctl->db($s::EVENTS)->getall())!==1)throw new RuntimeException('Duplicate edge/audit under real FFM concurrency');
 if((int)$ctl->db($s::LEFT)->get(1)['group_difference']!==-32000)throw new RuntimeException('Incorrect cached difference');
 foreach([$s::LEFT,$s::RIGHT,$s::LINKS,$s::EVENTS] as $table)$ctl->db($table)->close();unset($s,$ctl);gc_collect_cycles();echo "PASS: concurrent real FFM stale rejection and duplicate prevention; mismatch retained\n";
}finally{$remove=function($p)use(&$remove){if(is_file($p)||is_link($p)){unlink($p);return;}foreach(array_diff(scandir($p),['.','..']) as $n)$remove($p.'/'.$n);rmdir($p);};$remove($root);}

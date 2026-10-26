<?php
require __DIR__.'/../classes/app/simple_ec/RecurringEngine.php';
class MemoryRecurringDb {
 public array $rows=[];function __construct(array $rows=[]){foreach($rows as $r)$this->rows[$r['id']]=$r;}
 function getall(){return array_values($this->rows);}function select($k,$v){return array_values(array_filter($this->rows,fn($r)=>($r[$k]??null)===$v));}
 function insert(&$r){$r['id']=count($this->rows)+1;$this->rows[$r['id']]=$r;return $r['id'];}function update($r){$this->rows[$r['id']]=$r;}
}
function check($x,$m){if(!$x)throw new RuntimeException($m);}
function fixture(){return ['contracts'=>new MemoryRecurringDb([['id'=>1,'record_key'=>'contract-a','login_id'=>'shop-fixture','status'=>1,'getugaku'=>1000,'date_last_kessai'=>0,'date_shoki_kessai'=>0,'end_date'=>0,'mail'=>'fixture@example.invalid']]),'payments'=>new MemoryRecurringDb(),'outbox'=>new MemoryRecurringDb()];}
$d=fixture();$charges=0;$provider=function($c,$key)use(&$charges){$charges++;return ['id'=>'fixture-'.$key,'status'=>'COMPLETED','amount_money'=>['amount'=>1000,'currency'=>'JPY']];};
SimpleEcRecurringEngine::process($d,$provider,'2030-10');SimpleEcRecurringEngine::process($d,$provider,'2030-10');check($charges===1,'no monthly duplicate');
// Interruption after ledger success is recovered without a second provider call.
$d['contracts']->rows[1]['date_last_kessai']=0;SimpleEcRecurringEngine::process($d,$provider,'2030-10');check($charges===1,'no charge during recovery');check($d['contracts']->rows[1]['date_last_kessai']===strtotime('2030-10-01'),'last payment recovered');
for($i=1;$i<15;$i++){$month=date('Y-m',strtotime('2030-10-01 +'.$i.' months'));SimpleEcRecurringEngine::process($d,$provider,$month);SimpleEcRecurringEngine::process($d,$provider,$month);}
check($charges===15 && $d['contracts']->rows[1]['status']===1,'unlimited subscription after fifteen months');check(!isset($d['contracts']->rows[1]['kaisu_count']),'no count dependency');
$d['contracts']->rows[1]['status']=9;SimpleEcRecurringEngine::process($d,$provider,'2028-01');check($charges===15,'ended contracts excluded');
$d=fixture();$charges=0;$failure=function()use(&$charges){$charges++;throw new RuntimeException('uncertain network result');};SimpleEcRecurringEngine::process($d,$failure,'2030-10');$d['contracts']->rows[1]['status']=1;SimpleEcRecurringEngine::process($d,$failure,'2030-10');check($charges===1,'uncertain charge held');check(count($d['outbox']->rows)===1,'notification only queued');
$d=fixture();$d['contracts']->rows[1]['date_shoki_kessai']=strtotime('2030-11-01');$called=false;SimpleEcRecurringEngine::process($d,function()use(&$called){$called=true;},'2030-10');check(!$called,'future start excluded');
$d=fixture();$d['contracts']->rows[1]['getugaku']=str_pad('1000',255,'0',STR_PAD_LEFT);SimpleEcRecurringEngine::process($d,$provider,'2030-10');check($d['payments']->rows[1]['amount']===1000,'numeric string amounts normalized for ledger');
$d=fixture();$preview=$d['contracts']->rows[1];$preview['id']=2;$preview['record_key']='preview:sandbox-contract';$d['contracts']->rows[2]=$preview;
SimpleEcRecurringEngine::process($d,$provider,'2030-10',true);check(count($d['payments']->rows)===1 && $d['payments']->rows[1]['contract_id']===2,'sandbox scheduler excludes production contracts');
$d=fixture();$notices=[];$notify=function($event)use(&$notices){$notices[]=$event;};SimpleEcRecurringEngine::process($d,$failure,'2030-10',false,null,$notify);check(count($notices)===1 && $notices[0]==='recurring-error:'.SimpleEcStore::paymentKey('contract-a','2030-10'),'error notification called during recurring failure');
$d=fixture();$notifications=[];$url='https://example.invalid/simple_ec_public*card?code='.str_repeat('a',64);
SimpleEcRecurringEngine::process($d,function(){throw new RuntimeException('test decline');},'2030-10',false,null,function($key)use(&$notifications){$notifications[]=$key;},fn($contract)=>['subject'=>'カード変更のお願い','body'=>$url]);
check(count($notifications)===1 && str_contains($d['outbox']->rows[1]['body'],$url),'error notice contains card registration link');
echo "Subscription recurrence: unlimited monthly billing, idempotency, recovery, termination, future start and uncertain-result hold passed offline\n";

$a=fixture();$b=fixture();$a['contracts']->rows[1]['record_key']='live:unique-app-a:contract';$b['contracts']->rows[1]['record_key']='live:unique-app-b:contract';$keys=[];
$provider=function($contract,$key)use(&$keys){$keys[]=$key;return ['id'=>'mock-payment','status'=>'COMPLETED','amount_money'=>['amount'=>1000,'currency'=>'JPY']];};
SimpleEcRecurringEngine::process($a,$provider,'2033-01');SimpleEcRecurringEngine::process($b,$provider,'2033-01');check(count($keys)===2&&$keys[0]!==$keys[1],'same local contract IDs in different apps never reuse a merchant idempotency key');
echo "Cross-app recurring keys use unique contract identity rather than local numeric ID\n";

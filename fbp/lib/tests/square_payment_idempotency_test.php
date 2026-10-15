<?php
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/mysquare.php';
final class BillingFakeResponse {
 function __construct(private array $body,private bool $error=false){}
 function getBody(){return json_encode($this->body);}
 function isError(){return $this->error;}
}
final class BillingFakeApi {
 public array $requests=[];public bool $decline=false;
 function createPayment($request){$this->requests[]=$request;return $this->decline?new BillingFakeResponse(['errors'=>[['code'=>'GENERIC_DECLINE']]],true):new BillingFakeResponse(['payment'=>['id'=>'fake-payment','status'=>'COMPLETED']]);}
 function getPayment($id){return new BillingFakeResponse(['payment'=>['id'=>$id,'status'=>'COMPLETED']]);}
}
final class BillingFakeClient {function __construct(public BillingFakeApi $api){}function getPaymentsApi(){return $this->api;}}
function checkSquare($v,$m){if(!$v)throw new RuntimeException($m);}
$square=new mysquare('fake-token',true);$api=new BillingFakeApi();$p=new ReflectionProperty($square,'client');$p->setValue($square,new BillingFakeClient($api));
$key=str_repeat('a',40);
$square->payment('customer','card',5500,'JPY',$key);$square->payment('customer','card',5500,'JPY',$key);
checkSquare($api->requests[0]->getIdempotencyKey()===$key && $api->requests[1]->getIdempotencyKey()===$key,'key changed');
checkSquare($api->requests[0]->getReferenceId()===$key,'missing durable reference');
$square->payment('customer','card',5500,'JPY');checkSquare($api->requests[2]->getIdempotencyKey()!=='','legacy key missing');
$api->decline=true;checkSquare($square->payment('customer','card',5500,'JPY',$key)===false,'decline accepted');
checkSquare($square->get_payment_result()['errors'][0]['code']==='GENERIC_DECLINE','structured decline lost');
checkSquare($square->retrieve_payment('known-id')['id']==='known-id','lookup failed');
try{$square->payment('customer','card',5500,'JPY',str_repeat('a',46));throw new RuntimeException('long key accepted');}catch(InvalidArgumentException $e){}
echo "PASS: stable Square idempotency/reference, legacy compatibility, structured rejection, retrieval; no network\n";

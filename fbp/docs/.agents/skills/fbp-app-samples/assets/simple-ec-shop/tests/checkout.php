<?php
namespace Square {
 class Environment {const PRODUCTION='production';}
 class SquareClient {
  function __construct($config){}function getMerchantsApi(){return $this;}function retrieveMerchant($id){return $this;}function isSuccess(){return true;}function getResult(){return $this;}function getMerchant(){return $this;}function getMainLocationId(){return 'mock-default-location';}
 }
}
namespace {
require __DIR__.'/../classes/app/simple_ec_public/simple_ec_public.php';
class CheckoutMemoryDb {
 public array $rows=[];function __construct($rows=[]){foreach($rows as $r)$this->rows[$r['id']]=$r;}
 function getall(){return array_values($this->rows);}function get($id){return $this->rows[$id]??[];}
 function select($k,$v){return array_values(array_filter($this->rows,fn($r)=>(string)($r[$k]??'')===(string)$v));}
 function insert(&$r){$r['id']=count($this->rows)+1;$this->rows[$r['id']]=$r;return $r['id'];}function update(&$r){$this->rows[$r['id']]=$r;}
}
class Controller {
 public array $session=['sec_shop_id'=>1,'sec_csrf'=>'csrf','sec_checkout_key'=>'token','sec_cart'=>['1:1'=>['id'=>1,'variant'=>1,'count'=>1]]],$post=['sec_csrf'=>'csrf','checkout_key'=>'token','nonce'=>'fake-nonce'],$assigned=[],$errors=[],$dbs=[],$dialogs=[],$invoked=[],$messages=[],$mail=[],$param=[];public int $charges=0,$customers=0,$cards=0;
 function __construct($paytype=2,$type=0){
  foreach(['accounts','categories','shops','products','orders','items','contracts','payments','runtime','outbox','assets','secrets'] as $t)$this->dbs['sec_'.$t]=new CheckoutMemoryDb;
  $this->dbs['email_format']=new CheckoutMemoryDb;
  $this->dbs['sec_runtime']->rows[1]=['id'=>1,'record_key'=>'precutover','live_enabled'=>1,'mail_enabled'=>1,'cron_enabled'=>0,'recurring_start_date'=>strtotime('first day of +2 months 00:00:00')];
  $this->dbs['sec_shops']->rows[1]=['id'=>1,'record_key'=>'old-shop','login_id'=>'shop-fixture','shopname'=>'fixture','status'=>1,'paytype'=>$paytype,'soryo'=>100,'mail'=>'shop@example.invalid'];
  $this->dbs['sec_products']->rows[1]=['id'=>1,'login_id'=>'shop-fixture','owner_account'=>1,'name'=>'fixture product','type'=>$type,'hyoji'=>0,'code1'=>'fixture option','price1'=>1000,'zeiritu'=>10,'teiki_getugaku'=>2000,'teiki_shokai'=>0,'zaiko_check'=>0];
  SimpleEcStore::configureTypes($this->types);$quote=SimpleEcStore::quote($this->dbs['sec_shops']->rows[1],$this->dbs['sec_products']->rows,$this->session['sec_cart']);
  $this->session['sec_checkout_confirmed']=['token'=>'token','signature'=>SimpleEcStore::quoteSignature($quote),'contact'=>['from_name'=>'fixture','name'=>'fixture','mail'=>'buyer@example.invalid','from_tel'=>'000','from_zipcode'=>'000','from_address'=>'test','from_furigana'=>'','tel'=>'000','zipcode'=>'000','address'=>'test','memo'=>'']];
 }
 public array $types=[0=>'物販',1=>'イベント',2=>'問い合わせ',3=>'見積依頼',4=>'定期購入'];function get_constant_array($key,$empty=false){return $this->types;}
 function set_check_login($v){}function get_setting(){return ['force_testmode'=>0,'square_application_id'=>'production-mock','square_access_token'=>'fake-token'];}function get_session($key){return $key==='testserver'?false:($this->session[$key]??null);}function set_session($k,$v){$this->session[$k]=$v;}function POST($key=null){return $key===null?$this->post:($this->post[$key]??null);}function db($table,$class){return $this->dbs[$table];}
 function assign($k,$v){$this->assigned[$k]=$v;}function create_ValueFormatter(){return new class{function format_date($v){return (string)$v;}};}function get_APP_URL(...$args){return 'https://example.invalid/';}function fetch_string($str){return 'mock body';}
 function show_notification_text($text){$this->messages[]=$text;}function res_error_message(...$args){$this->errors[]=$args;}function show_multi_dialog($id,...$args){$this->dialogs[]=$id;}function close_multi_dialog(...$args){}function invoke($fn,$args,$cls){$this->invoked[]=$fn;}
 function set_square(...$args){}function encrypt($v){return (string)$v;}function decrypt($v){return (string)$v;}function show_square_dialog(...$args){$this->dialogs[]='square';}function close_square_dialog(){}function get_square_callback_parameter_array(){return $this->param;}
 function square_regist_customer(...$args){$this->customers++;return 'mock-customer';}function square_regist_card(...$args){$this->cards++;return 'mock-card';}function square_payment(...$args){$this->charges++;$this->amount=$args[2];return true;}public int $amount=0;function square_get_payment_result(){return ['id'=>'mock-payment','status'=>'COMPLETED','amount_money'=>['amount'=>$this->amount,'currency'=>'JPY']];}function square_get_error(){return 'mock error';}
 function send_mail_prepared_format($target,$format,...$args){$this->mail[]=[$target,$format];}
}
function ok($v,$m){if(!$v)throw new \RuntimeException($m);}
$c=new Controller(0);$page=new simple_ec_public($c);$page->submit($c);$order=$c->dbs['sec_orders']->rows[1];ok(!$c->messages,'bank order completes');ok($order['status']===0&&$order['order_code']==='ORDER-1'&&$order['paytype']==='銀行振込（手動決済）','bank order retains unpaid state with real order number');ok(count($c->dbs['sec_items']->rows)===1&&$c->charges===0&&count($c->mail)===2,'bank order details and actual recipient notifications, no charge');ok($c->mail===[['buyer@example.invalid','sec_notice'],['shop@example.invalid','sec_notice']],'production notice format and recipients');
$c->session['sec_checkout_key']='token';$page->submit($c);ok(count($c->dbs['sec_orders']->rows)===1&&count($c->mail)===2,'bank replay no duplicate order or mail');
$c=new Controller(2);$page=new simple_ec_public($c);$page->submit($c);ok(!$c->messages&&$c->invoked===['pay']&&!$c->mail,'Square order waits for card without notifications');$c->param=['order_token'=>'1'];$page->pay_callback($c);ok(!$c->messages&&$c->charges===1&&$c->dbs['sec_orders']->rows[1]['status']===1&&count($c->mail)===2,'production callback completes actual public flow');$page->pay_callback($c);ok($c->charges===1&&$c->cards===1&&count($c->mail)===2,'public callback replay monetary and SMTP idempotency');
$c=new Controller(2,4);$page=new simple_ec_public($c);$page->submit($c);ok(!$c->messages&&$c->invoked===['register_card'],'subscription leads to card entry');$contract=$c->dbs['sec_contracts']->rows[1];ok((int)$contract['date_shoki_kessai']>=strtotime('first day of +2 months 00:00:00'),'production subscription respects configured start month');$c->param=['contract_token'=>'1'];$page->card_callback($c);ok(!$c->messages&&$c->dbs['sec_contracts']->rows[1]['status']===1,'subscription card registers and activates');ok($c->charges===0,'future configured subscription registration never charges immediately');
$c=new Controller(2);$c->dbs['sec_runtime']->rows[1]['live_enabled']=0;$page=new simple_ec_public($c);$page->submit($c);ok(!$c->dbs['sec_orders']->rows&&!$c->mail&&!$c->charges&&count($c->messages)===1,'stopped cutover switches prevent any production side effects');
$c=new Controller(2);$c->dbs['sec_orders']->rows[1]=['id'=>1,'record_key'=>'live:token','metadata_json'=>'{"checkout_state":"building"}','login_id'=>'shop-fixture','type'=>0,'gokei'=>1200,'status'=>0];$page=new simple_ec_public($c);$page->submit($c);ok(!$c->charges&&!$c->mail&&count($c->messages)===1,'interrupted incomplete checkout cannot proceed to payment');
$c=new Controller(0,1);$c->types=[0=>'物販'];$page=new simple_ec_public($c);$page->submit($c);ok(!$c->dbs['sec_orders']->rows&&!$c->charges&&count($c->messages)===1,'forged disabled event submit rejected without order/payment');
$c=new Controller(2);$page=new simple_ec_public($c);$page->submit($c);$c->types=[1=>'イベント'];$c->param=['order_token'=>'1'];$page->pay_callback($c);ok(!$c->charges&&!$c->mail,'pending callback cannot charge a now-disabled type');
$c=new Controller(2,4);$c->dbs['sec_runtime']->rows[1]['recurring_start_date']=0;$page=new simple_ec_public($c);$page->submit($c);ok(!$c->dbs['sec_orders']->rows&&!$c->charges,'missing recurring start configuration rejects before order creation');
echo "PASS: actual public checkout paths for bank transfer, Square, subscription, notification recipients, replay and cutover/partial-order guards (mock API/SMTP only)\n";
}

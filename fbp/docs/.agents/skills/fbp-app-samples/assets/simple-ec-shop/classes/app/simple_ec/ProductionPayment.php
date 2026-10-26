<?php
require_once __DIR__.'/Store.php';
require_once __DIR__.'/RecurringEngine.php';
require_once __DIR__.'/Mail.php';
require_once __DIR__.'/MailContent.php';

final class SimpleEcProductionPayment {
    /** Resolve the default seller location for the SDK; no payment is made here. */
    public static function configuration(array $shop,array $settings,?callable $resolve=null): array {
        if((int)($shop['paytype']??0)!==2)throw new RuntimeException('ショップのSquare連携が無効です。');
        $config=array_intersect_key($settings,array_flip(['square_application_id','square_access_token','square_location_id']));
        foreach(['square_application_id','square_access_token'] as $key)if(empty($config[$key]))throw new RuntimeException('共通設定のSquare本番認証情報が未登録です。');
        if(str_starts_with((string)$config['square_application_id'],'sandbox-'))throw new RuntimeException('Square本番用の認証情報を設定してください。');
        $resolve??=static function(string $token): string {
            if(!class_exists(\Square\SquareClient::class))require_once dirname(__DIR__,3).'/fbp/lib_ext/square/autoload.php';
            try{
                $client=new \Square\SquareClient(['accessToken'=>$token,'environment'=>\Square\Environment::PRODUCTION]);
                $response=$client->getMerchantsApi()->retrieveMerchant('me');
                if(!$response->isSuccess())throw new RuntimeException('Merchant lookup failed');
                return (string)$response->getResult()->getMerchant()->getMainLocationId();
            }catch(Throwable $e){throw new RuntimeException('Squareの既定店舗情報を取得できません。認証情報と接続状態を確認してください。');}
        };
        $location=(string)$resolve((string)$config['square_access_token']);
        if($location==='')throw new RuntimeException('Squareの既定店舗が見つかりません。');
        // Controller's Payments adapter omits location_id, so both SDK and backend use the default.
        if(!empty($config['square_location_id']) && $config['square_location_id']!==$location)throw new RuntimeException('Squareの設定店舗と既定店舗が一致しません。');
        $config['square_location_id']=$location;return $config;
    }
    public static function configure(Controller $ctl,array $db,array $shop,?callable $resolve=null): void {
        SimpleEcPaymentGuard::assertProduction($ctl,$db);
        static $cache=[];
        $settings=$ctl->get_setting();
        $key=hash('sha256',json_encode([$shop['paytype']??0,$settings['square_application_id']??'',$settings['square_access_token']??'',$settings['square_location_id']??'']));
        $config=$resolve?self::configuration($shop,$settings,$resolve):($cache[$key]??=self::configuration($shop,$settings));
        $ctl->set_square($config['square_application_id'],$config['square_access_token'],$config['square_location_id']);
    }
    public static function contractKey(array $contract): string {
        return 'live-contract:'.$contract['id'];
    }
    private static function saveSecret(array $db,string $key,string $owner,array $payload): void {
        $rows=$db['secrets']->select('record_key',$key);$r=$rows[0]??['record_key'=>$key,'login_id'=>$owner,'source_table'=>'live-card'];
        $r['payload']=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if(isset($r['id']))$db['secrets']->update($r);else $db['secrets']->insert($r);
    }
    /** Save the customer before registering a card; callback replay reuses that card. */
    public static function registerCard(Controller $ctl,array $db,string $key,string $owner,array $contact): array {
        SimpleEcPaymentGuard::assertProduction($ctl,$db);
        $rows=$db['secrets']->select('record_key',$key);$saved=json_decode($rows[0]['payload']??'{}',true,512,JSON_THROW_ON_ERROR);
        $nonce=$ctl->POST('nonce');if(!is_string($nonce)||$nonce==='')throw new RuntimeException('カード情報を再入力してください。');
        $nonceHash=hash('sha256',$nonce);
        if(!empty($saved['card_id']) && hash_equals((string)($saved['nonce_hash']??''),$nonceHash))return $saved;
        $customer=$saved['customer_id']??'';
        if(!$customer){
            $customer=$ctl->square_regist_customer((string)$contact['name'],(string)$contact['mail'],(string)($contact['address']??''));
            if(!$customer)throw new RuntimeException($ctl->square_get_error()?:'Square顧客登録に失敗しました。');
            $saved['customer_id']=$customer;self::saveSecret($db,$key,$owner,$saved);
        }
        $card=$ctl->square_regist_card($customer);
        if(!$card)throw new RuntimeException($ctl->square_get_error()?:'Squareカード登録に失敗しました。');
        $saved['card_id']=$card;$saved['nonce_hash']=$nonceHash;self::saveSecret($db,$key,$owner,$saved);return $saved;
    }
    public static function pay(Controller $ctl,array $db,array $order,?callable $resolve=null): array {
        SimpleEcTypes::requireType($ctl,(int)$order['type']);
        $shop=SimpleEcStore::shop($db,$order['login_id']);self::configure($ctl,$db,$shop,$resolve);
        if(!str_starts_with((string)$order['record_key'],'live:') || (int)$order['gokei']<=0 || (int)$order['type']===4 || !in_array((int)$order['status'],[0,1],true) || (json_decode($order['metadata_json']??'{}',true)['checkout_state']??'')!=='ready')throw new RuntimeException('新規の通常注文から決済してください。');
        $key=substr(hash('sha256','production-order:'.$order['record_key']),0,40);
        $rows=$db['payments']->select('idempotency_key',$key);$p=$rows[0]??[];
        if($p && ((int)$p['order_id']!==(int)$order['id'] || (int)$p['amount']!==(int)$order['gokei'] || $p['login_id']!==$order['login_id'] || $p['environment']!=='production'))throw new RuntimeException('決済履歴の照合が必要です。');
        if($p && (int)($p['status']??1)===2)throw new RuntimeException('返金済みの注文は再決済できません。');
        if(($p['state']??'')==='succeeded'){
            $order['status']=1;$order['paytype']='Square';$db['orders']->update($order);return $p;
        }
        if(in_array($p['state']??'',['unknown','failed'],true))throw new RuntimeException('前回の決済結果を確認中です。再課金せず管理者へお問い合わせください。');
        if(!$p){$p=['name'=>($order['from_name']??'')?:($order['name']??''),'mail'=>$order['mail'],'status'=>1,'environment'=>'production','login_id'=>$order['login_id'],'order_id'=>$order['id'],'period'=>'single','amount'=>(int)$order['gokei'],'state'=>'preparing','source'=>'Square通常決済','result'=>'準備中','idempotency_key'=>$key,'created_at'=>time()];$db['payments']->insert($p);}
        try{
            $ids=self::registerCard($ctl,$db,'live-order:'.$order['id'],$order['login_id'],['name'=>($order['from_name']??'')?:($order['name']??''),'mail'=>$order['mail'],'address'=>($order['from_address']??'')?:($order['address']??'')]);
            $p['state']='unknown';$p['result']='結果確認中';$db['payments']->update($p);
            if(!$ctl->square_payment($ids['customer_id'],$ids['card_id'],(int)$order['gokei'],'JPY',$key)){
                $result=$ctl->square_get_payment_result();if(!empty($result['errors']))$p['state']='failed';
                throw new RuntimeException($ctl->square_get_error()?:'決済結果の確認が必要です。');
            }
            $result=$ctl->square_get_payment_result();
            if(empty($result['id']) || ($result['status']??'')!=='COMPLETED' || (int)($result['amount_money']['amount']??-1)!==(int)$order['gokei'] || ($result['amount_money']['currency']??'')!=='JPY')throw new RuntimeException('決済結果の照合が必要です。');
            $p['status']=0;$p['state']='succeeded';$p['result']='決済成功';$p['payment_id']=$result['id'];$p['error']='';$db['payments']->update($p);
            $order['status']=1;$order['paytype']='Square';$db['orders']->update($order);return $p;
        }catch(Throwable $e){$p['error']=mb_strcut($e->getMessage(),0,1900,'UTF-8');$db['payments']->update($p);throw $e;}
    }
    public static function chargeSubscriptions(Controller $ctl,array $db,?int $onlyId=null): array {
        SimpleEcPaymentGuard::assertProduction($ctl,$db);
        SimpleEcTypes::requireType($ctl,4);
        $month=date('Y-m');
        $provider=static function(array $c,string $key)use($ctl,$db): array {
            self::configure($ctl,$db,SimpleEcStore::shop($db,$c['login_id']));
            $rows=$db['secrets']->select('record_key',self::contractKey($c));$ids=json_decode($rows[0]['payload']??'{}',true,512,JSON_THROW_ON_ERROR);
            if(empty($ids['customer_id'])||empty($ids['card_id']))throw new RuntimeException('契約のカード情報が不足しています。');
            if(!$ctl->square_payment($ids['customer_id'],$ids['card_id'],(int)$c['getugaku'],'JPY',$key))throw new RuntimeException($ctl->square_get_error()?:'決済結果を確認してください。');
            return $ctl->square_get_payment_result();
        };
        return SimpleEcRecurringEngine::process($db,$provider,$month,false,$onlyId,static fn(string $key)=>SimpleEcMail::send($ctl,$db,[$key]),static fn(array $c)=>SimpleEcMailContent::recurringError($ctl,SimpleEcStore::shop($db,$c['login_id']),$c),SimpleEcPaymentGuard::recurringStartMonth($db),static fn(array $c)=>(int)SimpleEcStore::shop($db,$c['login_id'])['paytype']===2);
    }
}

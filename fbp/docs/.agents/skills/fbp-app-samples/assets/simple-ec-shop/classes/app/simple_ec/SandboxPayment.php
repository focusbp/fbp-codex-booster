<?php
require_once __DIR__.'/Store.php';
require_once __DIR__.'/RecurringEngine.php';
require_once __DIR__.'/Mail.php';
require_once __DIR__.'/MailContent.php';
/** Only newly created rehearsal orders may use this sandbox-only integration. */
final class SimpleEcSandboxPayment {
    public static function configure(Controller $ctl): void {
        SimpleEcPaymentGuard::assertSandbox($ctl);
        if((int)($ctl->get_setting()['force_testmode']??0)!==1)throw new RuntimeException('本番環境では決済できません。');
        $s=$ctl->get_setting();
        foreach(['square_application_id','square_access_token','square_location_id'] as $key)if(empty($s[$key]))throw new RuntimeException('Square Sandbox用の認証情報が未登録です。');
        if(!str_starts_with((string)$s['square_application_id'],'sandbox-'))throw new RuntimeException('Sandbox用のApplication IDを設定してください。');
        // Never use production credentials in sandbox tests.
        $ctl->set_square($s['square_application_id'],$s['square_access_token'],$s['square_location_id']);
    }
    public static function chargeSubscriptions(Controller $ctl,array $db,?int $onlyId=null): array {
        SimpleEcTypes::requireType($ctl,4);
        self::configure($ctl);
        $provider=function(array $contract,string $key)use($ctl,$db): array {
            self::configure($ctl);
            if(!str_starts_with((string)$contract['record_key'],'preview:'))throw new RuntimeException('本番契約はSandboxで決済できません。');
            $shop=SimpleEcStore::shop($db,$contract['login_id']);if((int)$shop['paytype']!==2)throw new RuntimeException('ショップのSquare連携が無効です。');
            $rows=$db['secrets']->select('record_key','sandbox-contract:'.$contract['id']);$ids=json_decode($rows[0]['payload']??'{}',true);
            if(empty($ids['customer_id'])||empty($ids['card_id']))throw new RuntimeException('Sandboxカードが未登録です。');
            if(!$ctl->square_payment($ids['customer_id'],$ids['card_id'],(int)$contract['getugaku'],'JPY',$key))throw new RuntimeException($ctl->square_get_error()?:'Sandbox決済に失敗しました。');
            return $ctl->square_get_payment_result();
        };
        return SimpleEcRecurringEngine::process($db,$provider,date('Y-m'),true,$onlyId,static fn(string $key)=>SimpleEcMail::send($ctl,$db,[$key]),static fn(array $contract)=>SimpleEcMailContent::recurringError($ctl,SimpleEcStore::shop($db,$contract['login_id']),$contract));
    }
    public static function pay(Controller $ctl,array $db,array $order): array {
        SimpleEcTypes::requireType($ctl,(int)$order['type']);
        self::configure($ctl);
        if(!str_starts_with((string)$order['record_key'],'preview:') || (int)$order['gokei']<=0)throw new RuntimeException('確認用の新規注文だけ決済できます。');
        $key=substr(hash('sha256','sandbox-order:'.$order['record_key']),0,40);
        $existing=$db['payments']->select('idempotency_key',$key);$payment=$existing[0]??[];
        if(($payment['state']??'')==='sandbox_succeeded')return $payment;
        if(in_array(($payment['state']??''),['sandbox_unknown','sandbox_failed'],true))throw new RuntimeException('前回の決済結果を確認中です。再課金せず、管理者に確認してください。');
        if(!$payment){$payment=['name'=>($order['from_name']??'')?:($order['name']??''),'mail'=>$order['mail']??'','status'=>1,'environment'=>'sandbox','login_id'=>$order['login_id'],'order_id'=>$order['id'],'period'=>'sandbox','amount'=>(int)$order['gokei'],'state'=>'sandbox_preparing','source'=>'Sandbox','result'=>'Sandbox準備中','idempotency_key'=>$key,'created_at'=>time()];$db['payments']->insert($payment);}
        try {
        $secretKey='sandbox-order:'.$order['id'];$secretRows=$db['secrets']->select('record_key',$secretKey);$secret=$secretRows[0]??[];$ids=$secret?json_decode($secret['payload'],true):[];
        $customer=$ids['customer_id']??'';
        if(!$customer){$customer=$ctl->square_regist_customer(($order['from_name']??'')?:$order['name'],$order['mail'],($order['from_address']??'')?:$order['address']);if(!$customer)throw new RuntimeException($ctl->square_get_error()?:'Sandbox顧客登録に失敗しました。');}
        $card=$ctl->square_regist_card($customer);if(!$card)throw new RuntimeException($ctl->square_get_error()?:'Sandboxカード登録に失敗しました。');
        $payload=json_encode(['customer_id'=>$customer,'card_id'=>$card],JSON_UNESCAPED_UNICODE);
        if(!$secret){$secret=['record_key'=>$secretKey,'login_id'=>$order['login_id'],'source_table'=>'sandbox-order','source_id'=>$order['id'],'payload'=>$payload];$db['secrets']->insert($secret);}else{$secret['payload']=$payload;$db['secrets']->update($secret);}
        $payment['state']='sandbox_unknown';$payment['result']='Sandbox結果確認中';$db['payments']->update($payment);
            if(!$ctl->square_payment($customer,$card,(int)$order['gokei'],'JPY',$key)){
                $result=$ctl->square_get_payment_result();$payment['error']=$ctl->square_get_error()?:'Sandbox決済に失敗しました。';
                // An explicit provider error is retryable; transport uncertainty is not.
                $payment['state']=!empty($result['errors'])?'sandbox_failed':'sandbox_unknown';$payment['result']=SimpleEcStore::paymentLabel($payment['state']);$db['payments']->update($payment);throw new RuntimeException($payment['error']);
            }
            $result=$ctl->square_get_payment_result();
            if(empty($result['id']) || ($result['status']??'')!=='COMPLETED' || (int)($result['amount_money']['amount']??-1)!==(int)$order['gokei'])throw new RuntimeException('Sandbox決済結果の照合が必要です。');
            $payment['status']=0;$payment['state']='sandbox_succeeded';$payment['result']='Sandbox成功';$payment['payment_id']=$result['id'];$payment['error']='';$db['payments']->update($payment);
            $order['status']=1;$order['paytype']='Square Sandbox（本番課金なし）';$db['orders']->update($order);
            return $payment;
        }catch(Throwable $e){if($payment['state']!=='sandbox_succeeded')$payment['status']=1;$payment['error']=mb_strcut($e->getMessage(),0,1900,'UTF-8');$db['payments']->update($payment);throw new RuntimeException($e->getMessage());}
    }
}

<?php
/** Sandbox-only order payment. Durable unknown state prevents blind retries. */
class feature_sample_payment {
    public static function configure(Controller $ctl):void {
        $s=$ctl->get_setting();
        if((int)($s['force_testmode']??0)!==1||!$ctl->get_session('testserver'))throw new RuntimeException('Square Sandbox専用のサンプルです。本番環境では決済できません。');
        foreach(['square_application_id','square_access_token','square_location_id'] as $k)if(empty($s[$k]))throw new RuntimeException('Square Sandbox設定が未登録です。外部連携設定へ登録してください。');
        if(!str_starts_with((string)$s['square_application_id'],'sandbox-'))throw new RuntimeException('Sandbox用のApplication IDを登録してください。');
        $ctl->set_square($s['square_application_id'],$s['square_access_token'],$s['square_location_id']);
    }
    public static function pay(Controller $ctl,$db,int $id):array {
        self::configure($ctl);$order=$db->get($id);
        if(!$order||(int)$order['total']<=0)throw new RuntimeException('注文金額を確認してください。');
        if(($order['payment_state']??'')==='paid')return $order;
        if(($order['payment_state']??'')!=='pending')throw new RuntimeException('前回の決済結果を管理側で確認してください。再課金は行いません。');
        // Persist before the first provider side effect. Even a crash cannot trigger a blind second attempt.
        $order['payment_state']='processing';$db->update($order);
        try{
            $customer=$ctl->square_regist_customer($order['name']!==''?$order['name']:'サンプル購入者',$order['email'],$order['address']);
            if(!$customer)throw new RuntimeException('Square Sandbox顧客登録に失敗しました。');
            $card=$ctl->square_regist_card($customer);if(!$card)throw new RuntimeException('Square Sandboxカード登録に失敗しました。');
            $order['payment_state']='unknown';$db->update($order);
            $key=substr(hash('sha256','feature-sample:'.$order['request_key']),0,40);
            if(!$ctl->square_payment($customer,$card,(int)$order['total'],'JPY',$key))throw new RuntimeException('Square Sandbox決済に失敗しました。管理側で結果を確認してください。');
            $result=$ctl->square_get_payment_result();
            if(empty($result['id'])||($result['status']??'')!=='COMPLETED'||(int)($result['amount_money']['amount']??-1)!==(int)$order['total']||($result['amount_money']['currency']??'')!=='JPY')throw new RuntimeException('Square Sandbox決済結果の照合が必要です。');
            $order['payment_state']='paid';$order['payment_id']=$result['id'];$db->update($order);return $order;
        }catch(Throwable $e){$order['payment_state']='unknown';$db->update($order);throw $e;}
    }
}

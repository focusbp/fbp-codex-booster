<?php
require_once __DIR__.'/SandboxPayment.php';
require_once __DIR__.'/ProductionPayment.php';

/** The caller opens all Store DBs before reading/authorizing the payment. */
final class SimpleEcRefund {
    public static function assertPaid(array $p): void {
        if((int)($p['status']??1)===2)throw new RuntimeException('すでに返金済みです。');
        if((int)($p['status']??1)!==0 || !in_array($p['state']??'',['succeeded','sandbox_succeeded'],true))throw new RuntimeException('エラーの決済は返金できません。');
        if(empty($p['payment_id']) || (int)$p['amount']<=0)throw new RuntimeException('返金に必要な決済情報がありません。');
    }

    public static function squareProvider(Controller $ctl,array $p,array $db=[]): callable {
        if(($p['environment']??'')==='sandbox')SimpleEcSandboxPayment::configure($ctl);
        elseif(($p['environment']??'')==='production' && $db)SimpleEcProductionPayment::configure($ctl,$db,SimpleEcStore::shop($db,$p['login_id']));
        else throw new RuntimeException('返金する決済の環境を確認してください。');
        return static function(string $action,array $p)use($ctl): array {
            if($action==='payment')return $ctl->square_retrieve_payment($p['payment_id']);
            $ok=$action==='retrieve'
                ?$ctl->square_retrieve_refund($p['refund_id'])
                :$ctl->square_refund_payment($p['payment_id'],(int)$p['amount'],$p['refund_key'],'JPY','管理画面からの全額返金');
            if(!$ok)throw new RuntimeException($ctl->square_get_error()?:'Squareの返金結果を取得できませんでした。');
            return $ctl->square_get_refund_result();
        };
    }

    /** Re-read under the FFM lock; every retry uses the same persisted key. */
    public static function execute(array $db,int $id,callable $provider): array {
        $p=$db['payments']->get($id);
        if(!$p)throw new RuntimeException('決済履歴が見つかりません。');
        if((int)($p['status']??1)===2)self::completeOrder($db,$p);
        self::assertPaid($p);
        if((int)($p['order_id']??0)>0){
            $order=$db['orders']->get((int)$p['order_id']);
            if(!$order || $order['login_id']!==$p['login_id'])throw new RuntimeException('元の受注を確認できません。');
        }
        if(in_array($p['refund_state']??'',['FAILED','REJECTED'],true))throw new RuntimeException('前回の返金に失敗しています。Squareの状態を確認してください。');
        $retry=!empty($p['refund_key']);
        try {
            if(!empty($p['refund_id'])) {
                $refund=$provider('retrieve',$p);
            } else {
                $payment=$provider('payment',$p);
                if(($payment['id']??'')!==$p['payment_id'] || ($payment['status']??'')!=='COMPLETED'
                    || (int)($payment['amount_money']['amount']??-1)!==(int)$p['amount']
                    || ($payment['amount_money']['currency']??'')!=='JPY')throw new RuntimeException('Squareの決済情報と一致しません。');
                // A previous request may have completed before the local result was saved.
                // Replay its key to retrieve that refund instead of creating another one.
                if(!$retry && (int)($payment['refunded_money']['amount']??0)>0)throw new RuntimeException('Squareですでに返金処理されています。状態を確認してください。');
                if(!$retry)$p['refund_key']=bin2hex(random_bytes(20));
                $p['refund_state']='UNKNOWN';$p['refund_error']='';$db['payments']->update($p);
                $refund=$provider('refund',$p);
            }
            if(empty($refund['id']) || ($refund['payment_id']??'')!==$p['payment_id']
                || (int)($refund['amount_money']['amount']??-1)!==(int)$p['amount']
                || ($refund['amount_money']['currency']??'')!=='JPY'
                || (!empty($p['refund_id']) && $p['refund_id']!==$refund['id'])
                || !in_array($refund['status']??'',['COMPLETED','PENDING','FAILED','REJECTED'],true))throw new RuntimeException('返金結果の照合が必要です。再実行時は同じ返金の状態を確認します。');
            $p['refund_id']=$refund['id'];$p['refund_state']=$refund['status'];$p['refund_error']='';
            if($refund['status']==='COMPLETED'){$p['status']=2;$p['refunded_at']=time();}
            if(in_array($refund['status'],['FAILED','REJECTED'],true))$p['refund_error']='Squareで返金が拒否・失敗しました。';
            $db['payments']->update($p);
            if((int)$p['status']===2)self::completeOrder($db,$p);
            return $p;
        }catch(Throwable $e){
            $p['refund_error']=mb_strcut($e->getMessage(),0,1900,'UTF-8');$db['payments']->update($p);
            throw $e;
        }
    }

    public static function completeOrder(array $db,array $p): void {
        if((int)($p['status']??1)!==2 || (int)($p['order_id']??0)<=0)return;
        $order=$db['orders']->get((int)$p['order_id']);
        if(!$order || $order['login_id']!==$p['login_id'])throw new RuntimeException('元の受注を確認できません。');
        $order['status']=4;$db['orders']->update($order);
    }
}

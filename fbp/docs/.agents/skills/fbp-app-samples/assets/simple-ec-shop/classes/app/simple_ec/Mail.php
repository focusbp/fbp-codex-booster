<?php
require_once __DIR__.'/PaymentGuard.php';
require_once __DIR__.'/Store.php';

final class SimpleEcMail {
    public static function send(Controller $ctl,array $db,array $keys): int {
        // Production uses each queued recipient; Sandbox retains its configured test recipients.
        try {
            if(!SimpleEcPaymentGuard::enabled($db,'mail_enabled'))return 0;
            $setting=$ctl->get_setting();$production=!SimpleEcPaymentGuard::isSandbox($ctl);
            if($production)SimpleEcPaymentGuard::assertProduction($ctl,$db,'mail_enabled');

            return self::deliver($db,$keys,static function(array $notice)use($ctl,$setting,$db,$production): void {
                $target=self::recipient($notice,$setting,$db,$production);
                if(!filter_var($target,FILTER_VALIDATE_EMAIL))throw new RuntimeException('通知先メールアドレスを設定してください。');
                $ctl->assign('notice_subject',$notice['subject']);$ctl->assign('notice_body',$notice['body']);$ctl->assign('original_recipient',$notice['recipient']);
                $ctl->send_mail_prepared_format($target,$production?'sec_notice':'sec_test_notice',null,'',null,['throw_on_error'=>true]);
            },$production);
        }catch(Throwable $e){
            // SMTP must never turn a completed payment into a failed checkout.
            return 0;
        }
    }

    public static function recipient(array $notice,array $setting,array $db,bool $production=false): string {
        if($production)return trim((string)($notice['recipient']??''));
        return trim((string)($setting['smtp_email_test']??''));
    }

    /** Store DBs (including email_format) are pre-opened; no new locks during SMTP. */
    public static function deliver(array $db,array $keys,callable $transport,bool $production=false): int {
        $prefix=$production?'':'テスト';
        $sent=0;
        foreach(array_unique($keys) as $key){
            foreach($db['outbox']->select('event_key',$key) as $notice){
                if($notice['state']!==$prefix.'送信待ち')continue;
                if(str_starts_with($key,'preview:') || str_starts_with($key,'live:')){
                    $parts=explode(':',$key);$orders=$db['orders']->select('record_key',$parts[0].':'.$parts[1]);
                    if(!$orders)continue;
                    $order=$orders[0];$manual= ($order['paytype']??'')==='銀行振込（手動決済）' && (int)$order['status']===0;
                    if((int)$order['status']!==1 && !$manual)continue;
                }
                // Persist before sending: callback replay or interruption cannot resend.
                $notice['state']=$prefix.'送信中';$db['outbox']->update($notice);
                try{$transport($notice);$notice['state']=$prefix.'送信済み';$sent++;}
                catch(Throwable $e){$notice['state']=$prefix.'送信結果の確認が必要';}
                $db['outbox']->update($notice);
            }
        }
        return $sent;
    }
}

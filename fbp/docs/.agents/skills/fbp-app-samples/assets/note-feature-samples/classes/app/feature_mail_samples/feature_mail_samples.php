<?php
/** Excel mail examples: transport is delegated to the framework. */
require_once dirname(__DIR__,3).'/fbp/lib/ExcelGrid.php';
class feature_mail_samples {
    const SEND='feature_sample_mail_send';
    const RECEIVE='feature_sample_mail_receive';
    const LOG='feature_sample_mail_send_log';
    private function guard(Controller $ctl):void {if(!$ctl->is_app_admin())throw new RuntimeException('管理者のみ実行できます。');}
    private function stores(Controller $ctl):void {
        $ctl->db('setting','setting');$ctl->db('email_format','email_format');$ctl->db('settings','excel_grid');$ctl->db('states','excel_grid');
        $ctl->db(self::SEND);$ctl->db(self::RECEIVE);$ctl->db(self::LOG);
    }
    private function token(Controller $ctl,string $kind):string {
        $token=bin2hex(random_bytes(24));$ctl->set_session('feature_mail_'.$kind,['token'=>$token,'expires'=>time()+1800]);return $token;
    }
    private function pending(Controller $ctl,string $kind):array {
        $p=$ctl->get_session('feature_mail_'.$kind);$t=$ctl->POST('token');
        if(!is_array($p)||!is_string($t)||!hash_equals($p['token'],$t)||$p['expires']<time())throw new RuntimeException('画面を開き直してください。');return $p;
    }
    public static function recipients(array $rows):array {
        $recipients=[];$duplicates=0;$empty=0;
        foreach($rows as $row){$email=trim((string)($row['b']??''));if($email===''){++$empty;continue;}
            if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('行ID '.(int)$row['id'].' のメールアドレスを修正してください。');
            $key=strtolower($email);if(isset($recipients[$key])){++$duplicates;continue;}
            $recipients[$key]=['name'=>(string)($row['a']??''),'email'=>$email];
        }
        return ['recipients'=>array_values($recipients),'duplicates'=>$duplicates,'empty'=>$empty];
    }
    private function targets(Controller $ctl):array {return self::recipients($ctl->db(self::SEND)->getall('excel_sort',SORT_ASC));}
    function send_form(Controller $ctl):void {
        $this->guard($ctl);$this->stores($ctl);$ctl->assign('token',$this->token($ctl,'send'));
        $ctl->show_multi_dialog('feature-mail-send','send.tpl','メール送信',700);
    }
    function send_confirm(Controller $ctl):void {
        $this->guard($ctl);$this->stores($ctl);
        try{
            $p=$this->pending($ctl,'send');$targets=$this->targets($ctl);if(!$targets['recipients'])throw new RuntimeException('ノートに送信先のメールアドレスを登録してください。');
            $subject=$ctl->POST('subject');$body=$ctl->POST('body');
            if(!is_string($subject)||trim($subject)===''||strlen($subject)>255||preg_match('/[\r\n\x00]/',$subject)){$ctl->res_error_message('subject','件名は255バイト以内で入力してください。');return;}
            if(!is_string($body)||trim($body)===''||strlen($body)>3000){$ctl->res_error_message('body','本文は3,000バイト以内で入力してください。');return;}
            $p=array_replace($p,$targets,['subject'=>trim($subject),'body'=>$body,'confirmation'=>bin2hex(random_bytes(24))]);$ctl->set_session('feature_mail_send',$p);
            $ctl->assign('mail',$p);$ctl->show_multi_dialog('feature-mail-send-confirm','send_confirm.tpl','送信内容の確認',700);
        }catch(Throwable $e){$ctl->res_error_message('token',$e->getMessage());}
    }
    function send_exe(Controller $ctl):void {
        $this->guard($ctl);$this->stores($ctl);
        try{
            $p=$this->pending($ctl,'send');if(!is_string($ctl->POST('confirmation'))||!isset($p['confirmation'])||!hash_equals($p['confirmation'],$ctl->POST('confirmation')))throw new RuntimeException('送信内容を確認してください。');
            if($this->targets($ctl)['recipients']!==$p['recipients'])throw new RuntimeException('宛先が変更されました。送信内容を確認し直してください。');
            $log=$ctl->db(self::LOG);$sent=0;
            // Preflight every previous attempt before sending any new recipient.
            foreach($p['recipients'] as $recipient){$key=hash('sha256',$p['token'].':'.strtolower($recipient['email']));$prior=$log->filter('request_key',$key,true);if($prior&&$prior[0]['status']!=='sent')throw new RuntimeException('前回の送信結果を確認できません。再送せず送信ログを確認してください。');}
            foreach($p['recipients'] as $recipient){
                $key=hash('sha256',$p['token'].':'.strtolower($recipient['email']));if($log->filter('request_key',$key,true))continue;
                $record=['request_key'=>$key,'status'=>'processing','to_email'=>$recipient['email'],'created_at'=>time()];$id=$log->insert($record);
                try{
                    $ctl->assign('mail_name',$recipient['name']);$ctl->assign('mail_subject',$p['subject']);$ctl->assign('mail_body',$p['body']);
                    $ctl->send_mail_prepared_format($recipient['email'],'feature_sample_excel_mail',null,'',null,['throw_on_error'=>true]);
                    $log->update(['id'=>$id,'status'=>'sent']);++$sent;
                }catch(Throwable $e){$log->update(['id'=>$id,'status'=>'unknown']);throw new RuntimeException($sent.'件送信済みです。続く宛先の送信結果を確認できないため停止しました。再送せず送信ログを確認してください。');}
            }
            $ctl->close_multi_dialog('feature-mail-send-confirm');$ctl->close_multi_dialog('feature-mail-send');$ctl->show_notification_text($sent?'メールを'.$sent.'件送信しました。':'この操作のメールはすべて送信済みです。');
        }catch(Throwable $e){$ctl->res_error_message('token',$e->getMessage());}
    }
    function receive_form(Controller $ctl):void {
        $this->guard($ctl);$ctl->assign('token',$this->token($ctl,'receive'));$ctl->show_multi_dialog('feature-mail-receive','receive.tpl','メール受信',600);
    }
    public function import(Controller $ctl):int {
        $this->guard($ctl);$this->stores($ctl);$db=$ctl->db(self::RECEIVE);$count=0;
        foreach($ctl->receive_mail(20) as $mail){
            $key=hash('sha256',$mail['id']);
            if(!$db->filter('receipt_key',$key,true)){
                $date=strtotime((string)$mail['date']);if($date===false)throw new RuntimeException('日付を判定できないメールがあります。');
                $row=['a'=>(string)$date,'b'=>(string)$mail['subject'],'c'=>(string)$mail['text'],'receipt_key'=>$key];
                if(strlen($row['b'])>255||strlen($row['c'])>3000)throw new RuntimeException('タイトル255バイトまたは内容3,000バイトを超えるメールがあります。保存・受信確定はしていません。');
                $max=0;foreach($db->getall() as $r)$max=max($max,(int)($r['excel_sort']??0));$row['excel_sort']=$max+1;$db->insert($row);++$count;
            }
            $ctl->confirm_received_mail($mail['id']);
        }
        return $count;
    }
    function receive_exe(Controller $ctl):void {
        $this->guard($ctl);
        try{$this->pending($ctl,'receive');$count=$this->import($ctl);$ctl->close_multi_dialog('feature-mail-receive');$ctl->show_notification_text($count.'件を取り込みました。');$ctl->reload_work_area();}
        catch(Throwable $e){$ctl->res_error_message('token','受信できませんでした。受信設定・保存上限・日付を確認してください。既に保存したメールは再実行しても重複しません。');}
    }
}

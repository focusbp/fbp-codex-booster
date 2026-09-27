<?php
/** Anonymous write-only intake: confirmation contents stay in the session, never in hidden inputs. */
class sample_public_intake {
    private const SESSION='sample_public_intake';
    function __construct(Controller $ctl){$ctl->set_check_login(false);$ctl->assign('viewport_public','width=device-width, initial-scale=1.0, viewport-fit=cover');}
    private function pending(Controller $ctl,bool $confirmation=false):?array {
        $p=$ctl->get_session(self::SESSION);$token=$ctl->POST('token');
        $expected=is_array($p)?($p[$confirmation?'confirmation_token':'token']??null):null;
        return is_string($expected)&&is_string($token)&&hash_equals($expected,$token)&&$p['expires']>=time()?$p:null;
    }
    function page(Controller $ctl):void {
        $token=bin2hex(random_bytes(24));
        $ctl->set_session(self::SESSION,['token'=>$token,'expires'=>time()+1800,'row'=>null]);
        $ctl->assign('token',$token);$ctl->assign('page_title','お問い合わせ');
        $ctl->show_public_pages('form.tpl','head.tpl',null,null,['css_mode'=>'minimal']);
    }
    function confirm(Controller $ctl):void {
        $p=$this->pending($ctl);
        if(!$p||!empty($p['completed'])){$ctl->res_error_message('token','受付画面を開き直してください。');return;}
        $row=[];$invalid=false;
        foreach(['name'=>100,'email'=>254,'message'=>1000] as $key=>$max){
            $value=$ctl->POST($key);
            if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max){$ctl->res_error_message($key,'1〜'.$max.'文字で入力してください。');$invalid=true;}
            elseif(strlen(trim($value))>$max*3){$ctl->res_error_message($key,'入力内容が長すぎます。短くしてください。');$invalid=true;}
            else $row[$key]=trim($value);
        }
        if(isset($row['email'])&&!filter_var($row['email'],FILTER_VALIDATE_EMAIL)){$ctl->res_error_message('email','メールアドレスを確認してください。');$invalid=true;}
        if($invalid)return;
        $p['row']=$row;$p['confirmation_token']=bin2hex(random_bytes(24));$ctl->set_session(self::SESSION,$p);
        $ctl->assign('row',$row);$ctl->assign('token',$p['confirmation_token']);
        $ctl->show_multi_dialog('sample-intake-confirm','confirm.tpl','入力内容の確認',600);
    }
    function save(Controller $ctl):void {
        $p=$this->pending($ctl,true);
        if(!$p||!is_array($p['row'])){$ctl->res_error_message('token','入力内容を確認し直してください。');return;}
        $db=$ctl->db('sample_intakes');$key=hash('sha256',$p['token']);
        $existing=$db->select('request_key',$key,true);
        if(!$existing){
            $row=$p['row']+['request_key'=>$key,'received_at'=>time(),'status'=>'0'];
            // Lock remains held from duplicate check through insertion. No notifications or external calls.
            $id=(int)$db->insert($row);if($id<=0)throw new RuntimeException('Intake was not saved.');
        }
        $p['completed']=true;$ctl->set_session(self::SESSION,$p);$ctl->set_session('sample_intake_completed',true);
        // Retain the grant until expiry: a lost response can be retried without another insertion.
        $ctl->close_multi_dialog('sample-intake-confirm');$ctl->res_redirect($ctl->get_APP_URL('sample_public_intake','complete'));
    }
    function complete(Controller $ctl):void {
        $ctl->assign('completed',(bool)$ctl->get_session('sample_intake_completed'));
        $ctl->assign('page_title','受付結果');$ctl->assign('page_url',$ctl->get_APP_URL('sample_public_intake','page'));
        $ctl->show_public_pages('complete.tpl','head.tpl',null,null,['css_mode'=>'minimal']);
    }
}

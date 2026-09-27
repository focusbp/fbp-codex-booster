<?php
/** Two one-way transitions. FFM holds the write lock through read/compare/update. */
class sample_case_transition implements CodegenActionInterface {
    private const SESSION='sample_case_transition';
    private function allowed(Controller $ctl):bool {
        if($ctl->get_session('login')&&$ctl->is_app_admin())return true;
        $ctl->show_notification_text('この操作を行う権限がありません。');return false;
    }
    private function id(Controller $ctl):int {
        $value=$ctl->POST('id');if(!is_string($value))return 0;
        $decoded=$ctl->decrypt($value);return ctype_digit((string)$decoded)?(int)$decoded:0;
    }
    function run(Controller $ctl):void {
        if(!$this->allowed($ctl))return;
        $id=$this->id($ctl);$row=$ctl->db('sample_cases')->get($id);
        $status=(string)($row['status']??'');
        if(!$row||!in_array($status,['0','1','2'],true)||$status==='2'){$ctl->show_notification_text('状態を変更できる案件がありません。');return;}
        $labels=$ctl->get_constant_array('sample_case_status',false);
        $next=$status==='0'?'1':'2';$token=bin2hex(random_bytes(24));
        $ctl->set_session(self::SESSION,['token'=>$token,'id'=>$id,'version'=>(int)$row['version'],'status'=>$status,'next'=>$next,'expires'=>time()+1800]);
        $ctl->assign('case_title',$row['title']);$ctl->assign('current_label',$labels[$status]);$ctl->assign('next_label',$labels[$next]);$ctl->assign('token',$token);
        $ctl->show_multi_dialog('sample-case-transition','confirm.tpl','状態を変更',600);
    }
    function save(Controller $ctl):void {
        if(!$this->allowed($ctl))return;
        $pending=$ctl->get_session(self::SESSION);$token=$ctl->POST('token');
        if(!is_array($pending)||!is_string($token)||!hash_equals($pending['token'],$token)||$pending['expires']<time()){$ctl->res_error_message('token','確認画面を開き直してください。');return;}
        $note=$ctl->POST('note');
        if(!is_string($note)||trim($note)===''||mb_strlen(trim($note))>300){$ctl->res_error_message('note','変更理由を1〜300文字で入力してください。');return;}
        $db=$ctl->db('sample_cases');$row=$db->get($pending['id']);
        if(!$row||(int)$row['version']!==$pending['version']||(string)$row['status']!==$pending['status']){$ctl->res_error_message('token','他の操作で更新されています。一覧から開き直してください。');return;}
        $labels=$ctl->get_constant_array('sample_case_status',false);
        $entry=date('Y-m-d H:i:s').' '.$labels[$pending['status']].' → '.$labels[$pending['next']].' / 担当ID: '.(int)$ctl->get_login_user_id().' / '.trim($note);
        $history=trim((string)($row['change_history']??'')."\n".$entry);
        if(strlen($history)>6000){$ctl->res_error_message('note','変更履歴が保存可能な長さを超えています。');return;}
        $row['status']=$pending['next'];$row['version']=(int)$row['version']+1;$row['change_history']=$history;
        // One row contains both state and history; no cross-table partial commit.
        $db->update($row);$ctl->set_session(self::SESSION,null);
        $ctl->close_multi_dialog('sample-case-transition');$ctl->reload_work_area();$ctl->show_notification_text('状態を変更しました。');
    }
}

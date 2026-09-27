<?php
class sample_cases_post_action implements CodegenActionInterface {
    function run(Controller $ctl):void {
        if(!$ctl->get_session('login')||!$ctl->is_app_admin()||$ctl->POST('_post_action_from')!=='add')return;
        $id=(int)$ctl->decrypt_post('id');$db=$ctl->db('sample_cases');$row=$db->get($id);
        // This hook can also be called directly. Initialize only a genuinely blank state,
        // never reset an existing workflow/history based on a client-supplied action name.
        if(!$row||($row['status']??'')!==''||(int)($row['version']??0)!==0||($row['change_history']??'')!=='')return;
        $row['status']='0';$row['version']=0;$row['change_history']='';$db->update($row);
        $ctl->reload_work_area();
    }
}

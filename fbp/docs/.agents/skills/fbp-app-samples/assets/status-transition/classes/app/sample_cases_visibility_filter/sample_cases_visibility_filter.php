<?php
class sample_cases_visibility_filter {
    function can_access(Controller $ctl,string $function):bool {
        if(!$ctl->get_session('login')||!$ctl->is_app_admin()||$function==='duplicate')return false;
        // Hidden fields are not authorization: reject forged writes through Standard Screen too.
        if(in_array($function,['add_exe','edit_exe'],true))foreach(['status','version','change_history'] as $field)if(array_key_exists($field,$ctl->POST()))return false;
        return true;
    }
}

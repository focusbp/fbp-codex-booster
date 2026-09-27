<?php
class sample_intakes_visibility_filter {
    function can_access(Controller $ctl,string $function):bool {
        return $ctl->get_session('login')&&$ctl->is_app_admin()
            &&in_array($function,['menu','page','rows','search','edit','edit_exe','reload'],true)
            &&($function!=='edit_exe'||!array_intersect(['request_key','received_at','name','email','message'],array_keys($ctl->POST())));
    }
}

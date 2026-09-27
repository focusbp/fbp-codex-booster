<?php
class sample_contacts_visibility_filter {
    function can_access(Controller $ctl,string $function):bool {
        return (bool)$ctl->get_session('login') && $ctl->is_app_admin();
    }
}

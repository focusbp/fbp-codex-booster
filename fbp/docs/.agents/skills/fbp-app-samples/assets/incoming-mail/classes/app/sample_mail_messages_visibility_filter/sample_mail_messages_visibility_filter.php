<?php
class sample_mail_messages_visibility_filter {
    function can_access(Controller $ctl, string $function): bool {
        if (!$ctl->get_session('login') || !$ctl->is_app_admin()) return false;
        if (in_array($function, ['duplicate', 'delete', 'delete_exe'], true)) return false;
        if (in_array($function, ['add', 'add_exe', 'edit', 'edit_exe'], true)) return false;
        return true;
    }
}

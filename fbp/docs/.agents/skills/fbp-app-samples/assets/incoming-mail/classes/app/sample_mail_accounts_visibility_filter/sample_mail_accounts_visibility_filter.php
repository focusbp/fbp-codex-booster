<?php
class sample_mail_accounts_visibility_filter {
    function can_access(Controller $ctl, string $function): bool {
        if (!$ctl->get_session('login') || !$ctl->is_app_admin()) return false;
        if (in_array($function, ['duplicate', 'delete', 'delete_exe'], true)) return false;
        if (in_array($function, ['add_exe', 'edit_exe'], true)) {
            foreach (array_keys($ctl->POST()) as $key) {
                if (str_starts_with($key, 'receive_') || in_array($key, ['last_received_at', 'last_error'], true)) return false;
            }
        }
        return true;
    }
}

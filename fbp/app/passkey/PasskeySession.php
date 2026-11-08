<?php
require_once __DIR__.'/PasskeyService.php';
require_once __DIR__.'/PasskeyAccess.php';
/** Additional revocation check; null preserves all framework authorization rules. */
final class PasskeySession {
    public static function authorize(Controller $ctl) {
        return PasskeyAccess::run(fn()=>self::checkSession($ctl));
    }
    private static function checkSession(Controller $ctl) {
        if (!$ctl->get_session('login')) return null;
        if ($ctl->get_session('passkey_authentication_method') === 'onetimecode'
            && (int)$ctl->get_session('onetimecode_session_expires') <= time()) {
            PasskeyService::clearAuth($ctl);
            $ctl->res_redirect($ctl->get_APP_URL('login','page'));
            return false;
        }
        // Open both participants before reading; no business-policy evaluation
        // or unrelated key/rate/settings DB is needed to validate this session.
        $users=$ctl->db('user','user');
        $identities=$ctl->db('identity','passkey');
        $user=$users->management_session_user();
        $valid=is_array($user) && !empty($user['id']) && (int)$user['status']===0;
        if ($valid) {
            $rows=$identities->select('user_id',(int)$user['id'],true);
            $identity=count($rows)===1 ? array_values($rows)[0] : null;
            $valid=$identity!==null && hash_equals($identity['epoch'],(string)$ctl->get_session('passkey_epoch'));
        }
        if (!$valid) {
            PasskeyService::clearAuth($ctl);
            $ctl->res_redirect($ctl->get_APP_URL('login','page'));
            return false;
        }
        return null;
    }
}

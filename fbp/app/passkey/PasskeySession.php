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
        $repository=new PasskeyRepository($ctl);
        $user=$repository->users->get((int)$ctl->get_session('user_id'));
        $valid=is_array($user) && !empty($user['id']) && (int)$user['status']===0;
        if ($valid) {
            $identity=$repository->identity((int)$user['id']);
            $valid=hash_equals($identity['epoch'],(string)$ctl->get_session('passkey_epoch'));
        }
        if (!$valid) {
            PasskeyService::clearAuth($ctl);
            $ctl->res_redirect($ctl->get_APP_URL('login','page'));
            return false;
        }
        return null;
    }
}
